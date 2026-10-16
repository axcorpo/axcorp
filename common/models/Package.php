<?php

namespace common\models;

use tws\behaviors\DefaultBehavior;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\caching\TagDependency;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii2tech\ar\position\PositionBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%package}}".
 *
 * @property int $id
 * @property int $parent_id
 * @property string $external_id
 * @property int $trial_period
 * @property string $trial_cycle
 * @property int $billing_period
 * @property string $billing_cycle
 * @property string $price
 * @property string $currency
 * @property int $default
 * @property int $type
 * @property int $sort_order
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property Package $parent
 * @property Package[] $packages
 * @property PackageFeature[] $packageFeatures
 * @property PackageTranslation[] $packageTranslations
 * @property PackageTranslation $translation
 * @property Language[] $languages
 * @property Subscription[] $subscriptions
 * @property Price[] $prices
 * @property User $creator
 * @property User $updater
 */
class Package extends UuidActiveRecord
{
	const TYPE_FREE = 1;
	const TYPE_STANDARD = 2;
	const TYPE_CUSTOM = 3;

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%package}}';
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
			'PositionBehavior' => [
				'class' => PositionBehavior::class,
				'positionAttribute' => 'sort_order',
			],
            'DefaultBehavior' => [
                'class' => DefaultBehavior::class,
                'ensureDefaultValue' => true,
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
			[['parent_id', 'trial_period', 'billing_period', 'default', 'type', 'sort_order', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['price'], 'number'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['status'], 'required'],
			[['external_id', 'trial_cycle', 'billing_cycle'], 'string', 'max' => 255],
			[['currency'], 'string', 'max' => 3],
			[['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => Package::class, 'targetAttribute' => ['parent_id' => 'id']],
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
			'external_id' => Yii::t('label', 'External ID'),
			'trial_period' => Yii::t('label', 'Trial Period'),
			'trial_cycle' => Yii::t('label', 'Trial Cycle'),
			'billing_period' => Yii::t('label', 'Billing Period'),
			'billing_cycle' => Yii::t('label', 'Billing Cycle'),
			'price' => Yii::t('label', 'Price'),
			'currency' => Yii::t('label', 'Currency'),
			'default' => Yii::t('label', 'Default'),
			'type' => Yii::t('label', 'Type'),
			'sort_order' => Yii::t('label', 'Sort Order'),
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
		return $this->hasOne(Package::class, ['id' => 'parent_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getPackages()
	{
		return $this->hasMany(Package::class, ['parent_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getPackageFeatures()
	{
		return $this->hasMany(PackageFeature::class, ['package_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getPackageTranslations()
	{
		return $this->hasMany(PackageTranslation::class, ['package_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param string|null $language
	 * @return PackageTranslation|null
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->packageTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%package_translation}}', ['package_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getPrices()
	{
		return $this->hasMany(Price::class, ['package_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriptions()
	{
		return $this->hasMany(Subscription::class, ['package_id' => 'id']);
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
	 * Gets the formatted billing cycle.
	 *
	 * @return string
	 */
	public function getFormattedTrialPeriod()
	{
		return implode(' ', array_filter([
			$this->trial_period,
			mb_strtolower(self::getCycleLabels($this->trial_period > 1 ? 'plural' : null)[$this->trial_cycle])
		]));
	}

	/**
	 * Gets the formatted billing cycle.
	 *
	 * @return string
	 */
	public function getFormattedBillingCycle()
	{
		return self::formatBillingCycle($this->billing_period, $this->billing_cycle);
	}

	/**
	 * Model type labels.
	 *
	 * @return array
	 */
	public static function getTypeLabels()
	{
		return [
			self::TYPE_FREE => Yii::t('label', 'Free'),
			self::TYPE_STANDARD => Yii::t('label', 'Standard'),
			self::TYPE_CUSTOM => Yii::t('label', 'Custom'),
		];
	}

	/**
	 * Model cycle labels.
	 *
	 * @param null|string $mode
	 * @return array
	 */
	public static function getCycleLabels($mode = null)
	{
		$cycleLabels = ScheduledTask::getCycleLabels($mode);
		unset(
			$cycleLabels[ScheduledTask::CYCLE_MINUTE],
			$cycleLabels[ScheduledTask::CYCLE_HOUR]
		);
		return $cycleLabels;
	}

	/**
	 * Formats the billing cycle text.
	 *
	 * @param int $period
	 * @param string $cycle
	 * @return string
	 */
	public static function formatBillingCycle($period, $cycle)
	{
		$str = '';

		if ($period == 1) {
			$str = Yii::t('common', 'In each {0}', [mb_strtolower(self::getCycleLabels()[$cycle])]);
		} elseif ($period > 1) {
			$str = Yii::t('common', 'At each {0} {1}', [$period, mb_strtolower(self::getCycleLabels('plural')[$cycle])]);
		}

		return $str;
	}

	/**
	 * Finds models by type.
	 *
	 * @param int|array $type
	 * @return \yii\db\ActiveRecord[]|array|static[]
	 */
	public static function findPackagesByType($type)
	{
		return static::find()
			->alias('p')
			->joinWith([
				'packageTranslations pt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'pt.language_id' => Yii::$app->language,
						'pt.deleted' => PackageTranslation::NO,
					]);
				},
			])
			->andWhere([
				'p.type' => $type,
				'p.status' => static::STATUS_ACTIVE,
				'p.deleted' => static::NO,
			])
			->addOrderBy(new Expression('[[p.sort_order]] IS NULL'))
			->addOrderBy(['p.sort_order' => SORT_ASC])
			->indexBy('id')
			->all();
	}

	/**
	 * Finds paid packages.
	 *
	 * @return \yii\db\ActiveRecord[]|array|static[]
	 */
	public static function findPaidPackages()
	{
		return static::find()
			->alias('p')
			->joinWith([
				'packageTranslations pt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'pt.language_id' => Yii::$app->language,
						'pt.deleted' => PackageTranslation::NO,
					]);
				},
			])
			->andWhere(['!=', 'p.type', static::TYPE_FREE])
			->andWhere([
				'p.status' => static::STATUS_ACTIVE,
				'p.deleted' => static::NO,
			])
			->addOrderBy(new Expression('[[p.sort_order]] IS NULL'))
			->addOrderBy(['p.sort_order' => SORT_ASC])
			->indexBy('id')
			->all();
	}

	/**
	 * Finds the free package.
	 *
	 * @return \yii\db\ActiveRecord|array|static
	 */
	public static function findFreePackage()
	{
		return static::find()
			->andWhere([
				'type' => static::TYPE_FREE,
				'status' => static::STATUS_ACTIVE,
				'deleted' => static::NO,
			])
			->limit(1)
			->one();
	}

    /**
     * Finds the default package.
     *
     * @return \yii\db\ActiveRecord|array|static
     */
    public static function findDefaultPackage()
    {
        return static::find()
            ->andWhere([
                'default' => static::YES,
                'status' => static::STATUS_ACTIVE,
                'deleted' => static::NO,
            ])
            ->limit(1)
            ->one();
    }
}
