<?php

namespace common\models;

use tws\behaviors\DateTimeBehavior;
use DateTime;
use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "{{%marketing_campaign_has_recipient}}".
 *
 * @property int $marketing_campaign_id
 * @property int $marketing_recipient_id
 * @property string $data
 * @property string $created_at
 * @property string $sent_at
 *
 * @property MarketingCampaign $marketingCampaign
 * @property MarketingRecipient $marketingRecipient
 */
class MarketingCampaignHasRecipient extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%marketing_campaign_has_recipient}}';
	}

	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'TimestampBehavior' => [
				'class' => TimestampBehavior::class,
				'updatedAtAttribute' => false,
				'value' => (new DateTime)->format('Y-m-d H:i:s'),
			],
			'DateTimeBehavior' => [
				'class' => DateTimeBehavior::class,
				'attributes' => ['sent_at'],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['marketing_campaign_id', 'marketing_recipient_id'], 'required'],
			[['marketing_campaign_id', 'marketing_recipient_id'], 'integer'],
			[['data'], 'string'],
			[['created_at', 'sent_at'], 'safe'],
			[['created_at', 'sent_at'], 'default'],
			[['marketing_campaign_id', 'marketing_recipient_id'], 'unique', 'targetAttribute' => ['marketing_campaign_id', 'marketing_recipient_id']],
			[['marketing_campaign_id'], 'exist', 'skipOnError' => true, 'targetClass' => MarketingCampaign::class, 'targetAttribute' => ['marketing_campaign_id' => 'id']],
			[['marketing_recipient_id'], 'exist', 'skipOnError' => true, 'targetClass' => MarketingRecipient::class, 'targetAttribute' => ['marketing_recipient_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'marketing_campaign_id' => Yii::t('label', 'Marketing Campaign ID'),
			'marketing_recipient_id' => Yii::t('label', 'Marketing Recipient ID'),
			'data' => Yii::t('label', 'Data'),
			'created_at' => Yii::t('label', 'Created At'),
			'sent_at' => Yii::t('label', 'Sent At'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getMarketingCampaign()
	{
		return $this->hasOne(MarketingCampaign::class, ['id' => 'marketing_campaign_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getMarketingRecipient()
	{
		return $this->hasOne(MarketingRecipient::class, ['id' => 'marketing_recipient_id']);
	}
}
