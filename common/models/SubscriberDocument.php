<?php

namespace common\models;

use tws\behaviors\DateTimeBehavior;
use Yii;
use tws\helpers\Url;

/**
 * This is the model class for table "{{%subscriber_document}}".
 *
 * @property int $id
 * @property int $subscriber_id
 * @property int $status_id
 * @property int $template_id
 * @property string $file
 * @property string $secondary_file
 * @property string $tertiary_file
 * @property string $issue_date
 * @property string $expiry_date
 * @property int $deleted
 *
 * @property Subscriber $subscriber
 * @property SubscriberStatus $subscriberStatus
 * @property Template $template
 *
 * @property string|null $fileUrl
 * @property string|null $secondaryFileUrl
 * @property string|null $tertiaryFileUrl
 */
class SubscriberDocument extends UuidActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%subscriber_document}}';
	}

	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			[
				'class' => DateTimeBehavior::class,
				'attributes' => ['issue_date', 'expiry_date'],
			],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['subscriber_id'], 'required'],
			[['subscriber_id', 'status_id', 'template_id', 'deleted'], 'integer'],
			[['issue_date', 'expiry_date'], 'safe'],
			[['issue_date', 'expiry_date'], 'default'],
			[['file', 'secondary_file', 'tertiary_file'], 'string', 'max' => 255],
			[['subscriber_id'], 'exist', 'skipOnError' => true, 'targetClass' => Subscriber::class, 'targetAttribute' => ['subscriber_id' => 'id']],
			[['status_id'], 'exist', 'skipOnError' => true, 'targetClass' => SubscriberStatus::class, 'targetAttribute' => ['status_id' => 'id']],
			[['template_id'], 'exist', 'skipOnError' => true, 'targetClass' => Template::class, 'targetAttribute' => ['template_id' => 'id']],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'subscriber_id' => Yii::t('label', 'Subscriber ID'),
			'status_id' => Yii::t('label', 'Subscriber Status ID'),
			'template_id' => Yii::t('label', 'Template ID'),
			'file' => Yii::t('label', 'File'),
			'secondary_file' => Yii::t('label', 'Secondary File'),
			'tertiary_file' => Yii::t('label', 'Tertiary File'),
			'issue_date' => Yii::t('label', 'Issue Date'),
			'expiry_date' => Yii::t('label', 'Expiry Date'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getInput()
	{
		return $this->hasOne(Subscriber::class, ['id' => 'subscriber_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriberStatus()
	{
		return $this->hasOne(SubscriberStatus::class, ['id' => 'status_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getTemplate()
	{
		return $this->hasOne(Template::class, ['id' => 'template_id']);
	}

	/**
	 * Gets the fileUrl.
	 *
	 * @param bool $scheme
	 * @return string
	 */
	public function getFileUrl($scheme = false)
	{
		return Url::to("@uploads/subscriber/{$this->uuidOf('subscriber_id')}/{$this->file}", $scheme);
	}

	/**
	 * Gets the secondaryFileUrl.
	 *
	 * @param bool $scheme
	 * @return string
	 */
	public function getSecondaryFileUrl($scheme = false)
	{
		return Url::to("@uploads/subscriber/{$this->uuidOf('subscriber_id')}/{$this->secondary_file}", $scheme);
	}

	/**
	 * Gets the tertiaryFileUrl.
	 *
	 * @param bool $scheme
	 * @return string
	 */
	public function getTertiaryFileUrl($scheme = false)
	{
		return Url::to("@uploads/subscriber/{$this->uuidOf('subscriber_id')}/{$this->tertiary_file}", $scheme);
	}
}
