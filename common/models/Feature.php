<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%feature}}".
 *
 * @property int $id
 * @property int $parent_id
 * @property int $feature_module_id
 * @property string $external_id
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
 * @property int $status
 * @property int $deleted
 *
 * @property Feature $parent
 * @property Feature[] $features
 * @property FeatureModule $featureModule
 * @property PackageFeature[] $packageFeatures
 * @property SubscriptionFeature[] $subscriptionFeatures
 * @property Price[] $prices
 * @property User $creator
 * @property User $updater
 *
 * @property string $formattedName
 */
class Feature extends UuidActiveRecord
{
	const TYPE_BOOLEAN = 1;

	const WORKSPACES = 'workspaces';
	const USERS = 'users';

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%feature}}';
	}

	/**
	 * {@inheritdoc}
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
			[['parent_id', 'feature_module_id', 'renewable', 'type', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['name', 'status'], 'required'],
			[['value'], 'string'],
			[['price'], 'number'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['external_id', 'name'], 'string', 'max' => 255],
			[['currency'], 'string', 'max' => 3],
			[['name'], 'unique'],
			[['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => Feature::class, 'targetAttribute' => ['parent_id' => 'id']],
			[['feature_module_id'], 'exist', 'skipOnError' => true, 'targetClass' => FeatureModule::class, 'targetAttribute' => ['feature_module_id' => 'id']],
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
			'feature_module_id' => Yii::t('label', 'Feature Module ID'),
			'external_id' => Yii::t('label', 'External ID'),
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
			'status' => Yii::t('label', 'Status'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getParent()
	{
		return $this->hasOne(Feature::class, ['id' => 'parent_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getFeatures()
	{
		return $this->hasMany(Feature::class, ['parent_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getFeatureModule()
	{
		return $this->hasOne(FeatureModule::class, ['id' => 'feature_module_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getPackageFeatures()
	{
		return $this->hasMany(PackageFeature::class, ['feature_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriptionFeatures()
	{
		return $this->hasMany(SubscriptionFeature::class, ['feature_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getPrices()
	{
		return $this->hasMany(Price::class, ['feature_id' => 'id']);
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
		$name[] = static::getFeatureLabels()[$this->name];

		if ($this->renewable) {
			$name[] = (' / ' . ScheduledTask::getCycleLabels()[ScheduledTask::CYCLE_MONTH]);
		}

		return implode('', $name);
	}

	/**
	 * Model feature labels.
	 *
	 * @param bool $groupByModule Flag that indicates if features should be grouped by module.
	 * @return array
	 */
	public static function getFeatureLabels($groupByModule = false)
	{
		$general = [
			self::WORKSPACES => Yii::t('label', 'Workspaces'),
			self::USERS => Yii::t('label', 'Users'),
		];

		if ($groupByModule === true) {
			return [
				FeatureModule::GENERAL => $general,
			];
		}
		return $general;
	}

	/**
	 * Finds all active records.
	 *
	 * @return array|\yii\db\ActiveRecord[]|static[]
	 */
	public static function findAllFeatures()
	{
		return static::find()
			->andWhere([
				'status' => static::STATUS_ACTIVE,
				'deleted' => static::NO,
			])
			->indexBy('name')
			->all();
	}
}
