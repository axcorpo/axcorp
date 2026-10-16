<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%service_category_has_service}}".
 *
 * @property int $service_category_id
 * @property int $service_id
 *
 * @property Service $service
 * @property ServiceCategory $serviceCategory
 */
class ServiceCategoryHasService extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%service_category_has_service}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['service_category_id', 'service_id'], 'required'],
			[['service_category_id', 'service_id'], 'integer'],
			[['service_category_id', 'service_id'], 'unique', 'targetAttribute' => ['service_category_id', 'service_id']],
			[['service_id'], 'exist', 'skipOnError' => true, 'targetClass' => Service::class, 'targetAttribute' => ['service_id' => 'id']],
			[['service_category_id'], 'exist', 'skipOnError' => true, 'targetClass' => ServiceCategory::class, 'targetAttribute' => ['service_category_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'service_category_id' => Yii::t('label', 'Service Category ID'),
			'service_id' => Yii::t('label', 'Service ID'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getService()
	{
		return $this->hasOne(Service::class, ['id' => 'service_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getServiceCategory()
	{
		return $this->hasOne(ServiceCategory::class, ['id' => 'service_category_id']);
	}
}
