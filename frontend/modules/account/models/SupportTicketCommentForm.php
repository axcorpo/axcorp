<?php

namespace frontend\modules\account\models;

use common\models\SupportTicketComment;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;
use yii\web\UploadedFile;

class SupportTicketCommentForm extends SupportTicketComment
{
	/**
	 * @var UploadedFile[] The attachment files, at most three images per reply.
	 */
	public $attachmentFiles;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->status = static::STATUS_ACTIVE;
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return ArrayHelper::merge(parent::rules(), [
			[['content'], 'trim'],
			// Same image allow-list as the ticket itself, checked against the real content,
			// but a reply carries at most three files.
			[['attachmentFiles'], 'file', 'extensions' => Yii::$app->params['image.extensions'], 'mimeTypes' => Yii::$app->params['image.mimeTypes'], 'maxSize' => Yii::$app->settings->get('maxFileSize'), 'maxFiles' => 3, 'skipOnEmpty' => true],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'attachmentFiles' => Yii::t('label', 'Attachments'),
			'content' => Yii::t('label', 'Message'),
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return Model::scenarios();
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
	 * Saves the files next to the ticket's own attachments and records their names on
	 * the comment, as a serialized list like the ticket does.
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
			$dirPath = Yii::getAlias("@uploads/support-ticket/{$this->uuidOf('support_ticket_id')}");
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
	 * @inheritdoc
	 */
	public function save($runValidation = true, $attributeNames = null)
	{
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			if (!parent::save($runValidation, $attributeNames)) {
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
