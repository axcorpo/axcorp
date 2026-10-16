<?php

namespace common\models;

use common\helpers\HtmlSanitizer;
use tws\helpers\Url;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%support_ticket_comment}}".
 *
 * @property int $id
 * @property int $support_ticket_id
 * @property string $content
 * @property string $attachment
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property SupportTicket $supportTicket
 * @property User $creator
 * @property User $updater
 */
class SupportTicketComment extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%support_ticket_comment}}';
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
			[['support_ticket_id', 'content', 'status'], 'required'],
			[['support_ticket_id', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['content'], 'string'],
			// Written by any registered customer and printed as HTML in the helpdesk
			// screens an administrator reads, so it is reduced to the editor's own
			// formatting on the way in. The views purify again on the way out, which
			// is what covers whatever is already stored.
			[['content'], 'filter', 'filter' => [HtmlSanitizer::class, 'richText'], 'skipOnArray' => true],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['attachment'], 'string', 'max' => 255],
			[['support_ticket_id'], 'exist', 'skipOnError' => true, 'targetClass' => SupportTicket::class, 'targetAttribute' => ['support_ticket_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'support_ticket_id' => Yii::t('label', 'Support Ticket ID'),
			'content' => Yii::t('label', 'Content'),
			'attachment' => Yii::t('label', 'Attachment'),
			'created_by' => Yii::t('label', 'Created By'),
			'updated_by' => Yii::t('label', 'Updated By'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
			'status' => Yii::t('label', 'Status'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSupportTicket()
	{
		return $this->hasOne(SupportTicket::class, ['id' => 'support_ticket_id']);
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
	 * The comment's attachments, stored next to the ticket's own files.
	 *
	 * @param bool $scheme
	 * @param bool $withDetails
	 * @return array
	 */
	public function getAttachments($scheme = false, $withDetails = false)
	{
		$attachments = [];

		foreach ((array) $this->getUnserializedValue('attachment', []) as $attachment) {
			$attachmentPath = "@uploads/support-ticket/{$this->uuidOf('support_ticket_id')}/{$attachment}";
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
