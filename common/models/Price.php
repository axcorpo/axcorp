<?php

namespace common\models;

use Yii;
use tws\behaviors\DefaultBehavior;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii2tech\ar\position\PositionBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%price}}".
 *
 * @property int $id
 * @property int $package_id
 * @property int $feature_id
 * @property string $external_id
 * @property string $price
 * @property string $currency
 * @property int $billing_period
 * @property string $billing_cycle
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
 * @property Feature $feature
 * @property Package $package
 * @property User $creator
 * @property User $updater
 */
class Price extends UuidActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%price}}';
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
//			'DefaultBehavior' => [
//				'class' => DefaultBehavior::class,
//				'ensureDefaultValue' => true,
//			],
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
			[['package_id', 'feature_id', 'billing_period', 'default', 'type', 'sort_order', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['price'], 'number'],
			[['created_at', 'updated_at'], 'safe'],
			[['status'], 'required'],
			[['external_id', 'billing_cycle'], 'string', 'max' => 255],
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
			'external_id' => Yii::t('label', 'External ID'),
			'price' => Yii::t('label', 'Price'),
			'currency' => Yii::t('label', 'Currency'),
			'billing_period' => Yii::t('label', 'Billing Period'),
			'billing_cycle' => Yii::t('label', 'Billing Cycle'),
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
	 * @return \yii\db\ActiveQuery
	 */
	public function getFeature()
	{
		return $this->hasOne(Feature::class, ['id' => 'feature_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery
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
}
