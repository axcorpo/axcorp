<?php

namespace common\models;

use tws\behaviors\DateTimeBehavior;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\helpers\ArrayHelper;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%marketing_campaign}}".
 *
 * @property int $id
 * @property int $type
 * @property int $variant
 * @property int $frequency
 * @property string $cycle
 * @property string $event
 * @property string $start_at
 * @property string $end_at
 * @property string $data
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property MarketingCampaignHasRecipient[] $marketingCampaignHasRecipients
 * @property MarketingRecipient[] $marketingRecipients
 * @property MarketingCampaignTranslation[] $marketingCampaignTranslations
 * @property MarketingCampaignTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 */
class MarketingCampaign extends UuidActiveRecord
{
	const STATUS_INACTIVE = 0;
	const STATUS_ACTIVE = 1;
	const STATUS_PAUSED = 2;
	const STATUS_COMPLETE = 3;

	const TYPE_DIRECT = 1;
	const TYPE_FOLLOW_UP = 2;

	const VARIANT_EMAIL = 1;
	const VARIANT_SMS = 2;

	const MKTEVENT_AFTER_ACCOUNT_ACTIVATION = 'afterAccountActivation';

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%marketing_campaign}}';
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
			'DateTimeBehavior' => [
				'class' => DateTimeBehavior::class,
				'attributes' => ['start_at', 'end_at'],
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
			[['type', 'variant', 'status'], 'required'],
			[['type', 'variant', 'frequency', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['start_at', 'end_at', 'created_at', 'updated_at'], 'safe'],
			[['start_at', 'end_at', 'created_at', 'updated_at'], 'default'],
			[['data'], 'string'],
			[['cycle', 'event'], 'string', 'max' => 255],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'type' => Yii::t('label', 'Type'),
			'variant' => Yii::t('label', 'Variant'),
			'frequency' => Yii::t('label', 'Frequency'),
			'cycle' => Yii::t('label', 'Cycle'),
			'event' => Yii::t('label', 'Event'),
			'start_at' => Yii::t('label', 'Start At'),
			'end_at' => Yii::t('label', 'End At'),
			'data' => Yii::t('label', 'Data'),
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
	public function getMarketingCampaignHasRecipients()
	{
		return $this->hasMany(MarketingCampaignHasRecipient::class, ['marketing_campaign_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getMarketingRecipients()
	{
		return $this->hasMany(MarketingRecipient::class, ['id' => 'marketing_recipient_id'])->viaTable('{{%marketing_campaign_has_recipient}}', ['marketing_campaign_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getMarketingCampaignTranslations()
	{
		return $this->hasMany(MarketingCampaignTranslation::class, ['marketing_campaign_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param null|string $language
	 * @return null|MarketingCampaignTranslation
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->marketingCampaignTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%marketing_campaign_translation}}', ['marketing_campaign_id' => 'id']);
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
	 * Model event labels.
	 *
	 * @return array
	 */
	public static function getEventLabels()
	{
		return [
			self::MKTEVENT_AFTER_ACCOUNT_ACTIVATION => Yii::t('label', 'After Account Activation'),
		];
	}

	/**
	 * Adds recipients for follow-up marketing campaigns.
	 *
	 * @param array $params Parameters like recipients and their custom data.
	 * @param array $conditions Query conditions for filtering marketing campaign models.
	 * @return bool
	 */
	public static function addFollowupRecipients($params, $conditions = [])
	{
		try {
			$recipients = (array) $params['recipients'];
			$data = (array) $params['data'];

			// Find all follow up marketing campaigns filtered by custom conditions
			$models = static::find()
				->andFilterWhere($conditions)
				->andWhere([
					'type' => static::TYPE_FOLLOW_UP,
					'status' => static::STATUS_ACTIVE,
					'deleted' => static::NO,
				]);

			/** @var static $model */
			foreach ($models->each(20) as $model) {
				// Find all eligible marketing campaign recipients
				$searchModel = new MarketingRecipientSearchForm();
				$searchModel->setAttributes((array) $model->getUnserializedValue('data')['filters']);
				$searchModel->getQuery()->andWhere(['mr.id' => $recipients]);
				if (!($eligibleMarketingRecipients = $searchModel->search())) {
					continue;
				}

				// Add eligible recipients for current marketing campaign
				foreach ($eligibleMarketingRecipients as $marketingRecipient) {
					$marketingCampaignHasRecipient = MarketingCampaignHasRecipient::findOne([
						'marketing_campaign_id' => $model->id,
						'marketing_recipient_id' => $marketingRecipient->id,
					]);
					if (!$marketingCampaignHasRecipient) {
						$marketingCampaignHasRecipient = new MarketingCampaignHasRecipient();
						$marketingCampaignHasRecipient->marketing_campaign_id = $model->id;
						$marketingCampaignHasRecipient->marketing_recipient_id = $marketingRecipient->id;
					}
					$marketingCampaignHasRecipient->data = !empty($data) ? @serialize($data) : null;
					$marketingCampaignHasRecipient->created_at = (new \DateTime)->format('Y-m-d H:i:s');
					$marketingCampaignHasRecipient->sent_at = null;
					if (!$marketingCampaignHasRecipient->save()) {
						throw new \Exception();
					}
				}
			}
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}
}
