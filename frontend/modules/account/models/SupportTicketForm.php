<?php

namespace frontend\modules\account\models;

use common\models\Subscriber;
use common\models\Subscription;
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
			[['subscription_id'], 'exist', 'skipOnError' => true, 'targetClass' => Subscription::class, 'targetAttribute' => ['subscription_id' => 'id'], 'filter' => function ($query) {
				// Only the signed-in account's own subscriptions.
				$query->andWhere(['subscriber_id' => Subscriber::find()->select('id')->where(['user_id' => Yii::$app->user->id])]);
			}],
			// Images only, at most five: the same allow-list the avatars use, checked
			// against the real content and not just the file name.
			[['attachmentFiles'], 'file', 'extensions' => Yii::$app->params['image.extensions'], 'mimeTypes' => Yii::$app->params['image.mimeTypes'], 'maxSize' => Yii::$app->settings->get('maxFileSize'), 'maxFiles' => 5, 'skipOnEmpty' => true],
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
			'subscription_id' => Yii::t('label', 'Subscription'),
			'content' => Yii::t('label', 'Message'),
			'attachmentFiles' => Yii::t('label', 'Attachments'),
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
	 * Only what the form renders: the ticket fields on create, the status on update. The
	 * inherited rules would otherwise accept user_id, created_by, deleted and the like.
	 */
	public function safeAttributes()
	{
		$fields = $this->isNewRecord
			? ['support_ticket_department_id', 'support_ticket_priority_id', 'subscription_id', 'subject', 'content']
			: ['support_ticket_status_id'];
		return array_values(array_intersect(parent::safeAttributes(), $fields));
	}

	/**
	 * @inheritdoc
	 *
	 * Expose the BINARY(16) FKs as canonical UUID strings so the widgets (whose option
	 * keys are UUID strings) can match the selected values; beforeSave / UuidActiveQuery
	 * convert back to bytes on the save path.
	 */
	public function afterFind()
	{
		parent::afterFind();

		$this->support_ticket_department_id = $this->uuidOf('support_ticket_department_id') ?: null;
		$this->support_ticket_priority_id = $this->uuidOf('support_ticket_priority_id') ?: null;
		$this->support_ticket_status_id = $this->uuidOf('support_ticket_status_id') ?: null;
		$this->subscription_id = $this->uuidOf('subscription_id') ?: null;
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
	 * Sends a notification.
	 *
	 * @param null|\common\models\SupportTicketComment $supportTicketCommentModel
	 * @return bool
	 */
	public function sendNotification($supportTicketCommentModel = null)
	{
		try {
			return Yii::$app->mailer->compose([
					'html' => '@account/views/support-ticket/email-notification-html',
					'text' => '@account/views/support-ticket/email-notification-text',
				], [
					'model' => $this,
					'supportTicketCommentModel' => $supportTicketCommentModel,
				])
				->setTo(Yii::$app->settings->get('email', 'contact'))
				->setSubject("[" . Yii::$app->name . " #{$this->getDocumentSeriesNumber()}]: " . Yii::t('common', 'Support ticket from {0}', [$this->creator->fullName]))
				->send();
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
