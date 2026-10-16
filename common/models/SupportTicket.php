<?php

namespace common\models;

use common\helpers\HtmlSanitizer;
use tws\helpers\Url;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%support_ticket}}".
 *
 * @property int $id
 * @property int $user_id
 * @property int $support_ticket_department_id
 * @property int $support_ticket_priority_id
 * @property int $support_ticket_status_id
 * @property int $subscription_id
 * @property string $series
 * @property int $number
 * @property string $subject
 * @property string $content
 * @property string $attachment
 * @property int $type
 * @property int $seen
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $deleted
 *
 * @property Subscription $subscription
 * @property SupportTicketDepartment $supportTicketDepartment
 * @property SupportTicketPriority $supportTicketPriority
 * @property SupportTicketStatus $supportTicketStatus
 * @property User $user
 * @property SupportTicketComment[] $supportTicketComments
 * @property User $creator
 * @property User $updater
 */
class SupportTicket extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%support_ticket}}';
	}

	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'BlameableBehavior' => [
				'class' => BlameableBehavior::class,
			],
			'TimestampBehavior' => [
				'class' => TimestampBehavior::class,
				'value' => (new \DateTime)->format('Y-m-d H:i:s'),
			],
			'SoftDeleteBehavior' => [
				'class' => SoftDeleteBehavior::class,
				'softDeleteAttributeValues' => [
					'deleted' => static::YES,
				],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['user_id', 'content'], 'required'],
			[['user_id', 'support_ticket_department_id', 'support_ticket_priority_id', 'support_ticket_status_id', 'subscription_id', 'number', 'type', 'seen', 'created_by', 'updated_by', 'deleted'], 'integer'],
			[['content'], 'string'],
			// Written by any registered customer and printed as HTML in the helpdesk
			// screens an administrator reads, so it is reduced to the editor's own
			// formatting on the way in. The views purify again on the way out, which
			// is what covers whatever is already stored.
			[['content'], 'filter', 'filter' => [HtmlSanitizer::class, 'richText'], 'skipOnArray' => true],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['series', 'subject', 'attachment'], 'string', 'max' => 255],
			[['subscription_id'], 'exist', 'skipOnError' => true, 'targetClass' => Subscription::class, 'targetAttribute' => ['subscription_id' => 'id']],
			[['support_ticket_department_id'], 'exist', 'skipOnError' => true, 'targetClass' => SupportTicketDepartment::class, 'targetAttribute' => ['support_ticket_department_id' => 'id']],
			[['support_ticket_priority_id'], 'exist', 'skipOnError' => true, 'targetClass' => SupportTicketPriority::class, 'targetAttribute' => ['support_ticket_priority_id' => 'id']],
			[['support_ticket_status_id'], 'exist', 'skipOnError' => true, 'targetClass' => SupportTicketStatus::class, 'targetAttribute' => ['support_ticket_status_id' => 'id']],
			[['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'user_id' => Yii::t('label', 'User ID'),
			'support_ticket_department_id' => Yii::t('label', 'Support Ticket Department ID'),
			'support_ticket_priority_id' => Yii::t('label', 'Support Ticket Priority ID'),
			'support_ticket_status_id' => Yii::t('label', 'Support Ticket Status ID'),
			'subscription_id' => Yii::t('label', 'Subscription ID'),
			'series' => Yii::t('label', 'Series'),
			'number' => Yii::t('label', 'Number'),
			'subject' => Yii::t('label', 'Subject'),
			'content' => Yii::t('label', 'Content'),
			'attachment' => Yii::t('label', 'Attachment'),
			'type' => Yii::t('label', 'Type'),
			'seen' => Yii::t('label', 'Seen'),
			'created_by' => Yii::t('label', 'Created By'),
			'updated_by' => Yii::t('label', 'Updated By'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @inheritdoc
	 */
	public function beforeSave($insert)
	{
		if ($this->getIsNewRecord()) {
//			if (empty($this->series)) {
//				$this->series = 'SPT';
//			}
			if (empty($this->number)) {
				$this->number = static::find()->where(['series' => $this->series])->max('number') + 1;
			}
		}
		return parent::beforeSave($insert);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscription()
	{
		return $this->hasOne(Subscription::class, ['id' => 'subscription_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSupportTicketDepartment()
	{
		return $this->hasOne(SupportTicketDepartment::class, ['id' => 'support_ticket_department_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSupportTicketPriority()
	{
		return $this->hasOne(SupportTicketPriority::class, ['id' => 'support_ticket_priority_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSupportTicketStatus()
	{
		return $this->hasOne(SupportTicketStatus::class, ['id' => 'support_ticket_status_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUser()
	{
		return $this->hasOne(User::class, ['id' => 'user_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSupportTicketComments()
	{
		return $this->hasMany(SupportTicketComment::class, ['support_ticket_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCreator()
	{
		return $this->hasOne(User::class, ['id' => 'created_by']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUpdater()
	{
		return $this->hasOne(User::class, ['id' => 'updated_by']);
	}

	/**
	 * Gets the zero filled documentSeriesNumber.
	 *
	 * @return string
	 */
	public function getDocumentSeriesNumber()
	{
		return trim($this->series . ' ' . str_pad($this->number, 5, 0, STR_PAD_LEFT));
	}

	/**
	 * Gets the attachments list.
	 *
	 * @param bool $scheme
	 * @param bool $withDetails Flag that indicates if attachment details should be returned.
	 * @return array
	 */
	public function getAttachments($scheme = false, $withDetails = false)
	{
		$attachments = [];

		foreach ($this->getUnserializedValue('attachment', []) as $attachment) {
			$attachmentPath = "@uploads/support-ticket/{$this->uuid}/{$attachment}";
			if ($withDetails === true) {
				$attachments[] = [
					'name' => $attachment,
					'size' => is_file(Yii::getAlias($attachmentPath)) ? filesize(Yii::getAlias($attachmentPath)) : 0,
					'url' => Url::to($attachmentPath, $scheme),
				];
			} else {
				$attachments[] = Url::to($attachmentPath, $scheme);
			}
		}

		return $attachments;
	}
}
