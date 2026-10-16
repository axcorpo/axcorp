<?php

namespace backend\modules\helpdesk\models;

use common\models\SupportTicket;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;
use yii\web\UploadedFile;

class SupportTicketForm extends SupportTicket
{
	const SCENARIO_UPDATE = 'update';

	/**
	 * @var UploadedFile[] The attachment files.
	 */
	public $attachmentFiles;


	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return ArrayHelper::merge(parent::rules(), [
			[['support_ticket_status_id', 'support_ticket_priority_id', 'support_ticket_department_id', 'subject', 'content'], 'required'],
			[['subject', 'content'], 'trim'],
			[['attachmentFiles'], 'file', 'extensions' => Yii::$app->params['file.extensions'], 'mimeTypes' => Yii::$app->params['file.mimeTypes'], 'maxSize' => Yii::$app->settings->get('maxFileSize'), 'maxFiles' => 5, 'skipOnEmpty' => true],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'support_ticket_status_id' => Yii::t('label', 'Status'),
			'support_ticket_priority_id' => Yii::t('label', 'Priority'),
			'support_ticket_department_id' => Yii::t('label', 'Department'),
			'content' => Yii::t('label', 'Message'),
			'attachmentFiles' => Yii::t('label', 'Attachments'),
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return ArrayHelper::merge(Model::scenarios(), [
			self::SCENARIO_UPDATE => ['support_ticket_status_id', 'support_ticket_priority_id', 'support_ticket_department_id', 'subject', 'attachmentFiles'],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function afterFind()
	{
		parent::afterFind();

		// Expose the FKs as canonical UUID strings so the dropdowns (whose option
		// keys are UUID strings) can match the selected values; beforeSave converts back.
		$this->support_ticket_status_id = $this->uuidOf('support_ticket_status_id') ?: null;
		$this->support_ticket_priority_id = $this->uuidOf('support_ticket_priority_id') ?: null;
		$this->support_ticket_department_id = $this->uuidOf('support_ticket_department_id') ?: null;
	}

	/**
	 * {@inheritdoc}
	 *
	 * The file validator in [[rules()]] only bites if the attribute is populated before
	 * validation runs; without this, saveFiles() would pull the raw upload straight from
	 * the request and the rule would never have been applied.
	 */
	public function load($data, $formName = null)
	{
		$result = parent::load($data, $formName);
		if (!empty($data)) {
			$this->attachmentFiles = UploadedFile::getInstances($this, 'attachmentFiles');
		}
		return $result;
	}

	/**
	 * Saves the files.
	 *
	 * @return bool
	 */
	protected function saveFiles()
	{
		try {
			if (!($files = UploadedFile::getInstances($this, 'attachmentFiles'))) {
				return true;
			}

			$serializedFiles = [];
			$dirPath = Yii::getAlias("@uploads/support-ticket/{$this->uuid}");
			FileHelper::createDirectory($dirPath);

			foreach ($files as $file) {
				$fileName = StringHelper::truncate(implode('_', array_filter([
						Inflector::slug($file->baseName),
						Yii::$app->security->generateRandomString(8),
					])), 255 - (mb_strlen($file->extension) + 1), '') . ".{$file->extension}";

				if (!$file->saveAs("{$dirPath}/{$fileName}")) {
					throw new \Exception();
				}

				$serializedFiles[] = $fileName;
			}

			$this->setSerializedValue('attachment', $serializedFiles);
			if (!$this->updateAttributes(['attachment'])) {
				throw new \Exception();
			}
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Saves the model.
	 *
	 * @return bool|\yii\db\ActiveRecord|self
	 */
	public function saveModel()
	{
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->saveFiles()) {
				throw new \Exception();
			}
			$dbTransaction->commit();
			return $this;
		} catch(\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}
}
