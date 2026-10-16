<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%marketing_campaign_translation}}".
 *
 * @property int $marketing_campaign_id
 * @property string $language_id
 * @property string $name
 * @property string $subject
 * @property string $content
 * @property int $deleted
 *
 * @property Language $language
 * @property MarketingCampaign $marketingCampaign
 */
class MarketingCampaignTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%marketing_campaign_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['marketing_campaign_id', 'language_id', 'name'], 'required'],
			[['marketing_campaign_id', 'deleted'], 'integer'],
			[['content'], 'string'],
			[['language_id'], 'string', 'max' => 5],
			[['name', 'subject'], 'string', 'max' => 255],
			[['marketing_campaign_id', 'language_id'], 'unique', 'targetAttribute' => ['marketing_campaign_id', 'language_id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
			[['marketing_campaign_id'], 'exist', 'skipOnError' => true, 'targetClass' => MarketingCampaign::class, 'targetAttribute' => ['marketing_campaign_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'marketing_campaign_id' => Yii::t('label', 'Marketing Campaign ID'),
			'language_id' => Yii::t('label', 'Language ID'),
			'name' => Yii::t('label', 'Name'),
			'subject' => Yii::t('label', 'Subject'),
			'content' => Yii::t('label', 'Content'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguage()
	{
		return $this->hasOne(Language::class, ['language_id' => 'language_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getMarketingCampaign()
	{
		return $this->hasOne(MarketingCampaign::class, ['id' => 'marketing_campaign_id']);
	}
}
