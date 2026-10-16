<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%package_feature}}".
 *
 * @property int $id
 * @property int $package_id
 * @property int $feature_id
 * @property string $name
 * @property string $value
 * @property string $price
 *
 * @property int $renewable
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $deleted
 *
 * @property Feature $feature
 * @property Package $package
 * @property User $creator
 * @property User $updater
 *
 * @property string $formattedName
 * @property string $formattedValue
 */
class PackageFeature extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%package_feature}}';
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
			[['package_id', 'name'], 'required'],
			[['package_id', 'feature_id', 'renewable', 'created_by', 'updated_by', 'deleted'], 'integer'],
			[['value'], 'string'],
			[['price'], 'number'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['name'], 'string', 'max' => 255],
			[['currency'], 'string', 'max' => 3],
			[['feature_id'], 'exist', 'skipOnError' => true, 'targetClass' => Feature::class, 'targetAttribute' => ['feature_id' => 'id']],
			[['package_id'], 'exist', 'skipOnError' => true, 'targetClass' => Package::class, 'targetAttribute' => ['package_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'package_id' => Yii::t('label', 'Package ID'),
			'feature_id' => Yii::t('label', 'Feature ID'),
			'name' => Yii::t('label', 'Name'),
			'value' => Yii::t('label', 'Value'),
			'price' => Yii::t('label', 'Price'),
			'currency' => Yii::t('label', 'Currency'),
			'renewable' => Yii::t('label', 'Renewable'),
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
	public function getPackage()
	{
		return $this->hasOne(Package::class, ['id' => 'package_id']);
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
	 * @return string
	 */
	public function getFormattedValue()
	{
		$value = [$this->value];

		if ($this->renewable) {
			$value[] = " / " . mb_strtolower(ScheduledTask::getCycleLabels()[ScheduledTask::CYCLE_MONTH]);
		}

		return implode('', $value);
	}
}
