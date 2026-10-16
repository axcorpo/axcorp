<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%subscription_feature}}".
 *
 * @property int $id
 * @property int $parent_id
 * @property int $subscription_id
 * @property int $feature_id
 * @property string $name
 * @property string $value
 * @property string $price
 * @property string $currency
 * @property int $renewable
 * @property int $type
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $deleted
 *
 * @property Feature $feature
 * @property Subscription $subscription
 * @property SubscriptionFeature $parent
 * @property SubscriptionFeature[] $subscriptionFeatures
 * @property WorkspaceHasSubscriptionFeature[] $workspaceHasSubscriptionFeatures
 * @property Workspace[] $workspaces
 * @property User $creator
 * @property User $updater
 *
 * @property string $formattedName
 * @property string $formattedValue
 */
class SubscriptionFeature extends UuidActiveRecord
{
	const TYPE_PACKAGE_FEATURE = 1;
	const TYPE_ADDON_FEATURE = 2;

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%subscription_feature}}';
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
			[['parent_id', 'subscription_id', 'feature_id', 'renewable', 'type', 'created_by', 'updated_by', 'deleted'], 'integer'],
			[['subscription_id', 'name'], 'required'],
			[['value'], 'string'],
			[['price'], 'number'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['name'], 'string', 'max' => 255],
			[['currency'], 'string', 'max' => 3],
			[['feature_id'], 'exist', 'skipOnError' => true, 'targetClass' => Feature::class, 'targetAttribute' => ['feature_id' => 'id']],
			[['subscription_id'], 'exist', 'skipOnError' => true, 'targetClass' => Subscription::class, 'targetAttribute' => ['subscription_id' => 'id']],
			[['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => SubscriptionFeature::class, 'targetAttribute' => ['parent_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'parent_id' => Yii::t('label', 'Parent ID'),
			'subscription_id' => Yii::t('label', 'Subscription ID'),
			'feature_id' => Yii::t('label', 'Feature ID'),
			'name' => Yii::t('label', 'Name'),
			'value' => Yii::t('label', 'Value'),
			'price' => Yii::t('label', 'Price'),
			'currency' => Yii::t('label', 'Currency'),
			'renewable' => Yii::t('label', 'Renewable'),
			'type' => Yii::t('label', 'Type'),
			'created_by' => Yii::t('label', 'Created By'),
			'updated_by' => Yii::t('label', 'Updated By'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getFeature()
	{
		return $this->hasOne(Feature::class, ['id' => 'feature_id']);
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
	public function getParent()
	{
		return $this->hasOne(SubscriptionFeature::class, ['id' => 'parent_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriptionFeatures()
	{
		return $this->hasMany(SubscriptionFeature::class, ['parent_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getWorkspaceHasSubscriptionFeatures()
	{
		return $this->hasMany(WorkspaceHasSubscriptionFeature::class, ['subscription_feature_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getWorkspaces()
	{
		return $this->hasMany(Workspace::class, ['id' => 'workspace_id'])->viaTable('{{%workspace_has_subscription_feature}}', ['subscription_feature_id' => 'id']);
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
	 * Gets the formatted name.
	 *
	 * @return string
	 */
	public function getFormattedName()
	{
		$name[] = Feature::getFeatureLabels()[$this->name];

		if ($this->renewable) {
			$name[] = (' / ' . ScheduledTask::getCycleLabels()[ScheduledTask::CYCLE_MONTH]);
		}

		return implode('', $name);
	}

	/**
	 * Gets the formatted value that contains the renewable flag.
	 *
	 * @return int|string
	 */
	public function getFormattedValue()
	{
		if (!$this->value) {
			return 0;
		}

		if ($this->renewable) {
			return "{$this->value} / " . mb_strtolower(ScheduledTask::getCycleLabels()[ScheduledTask::CYCLE_MONTH]);
		}

		return $this->value;
	}
}
