<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%subscriber_status_has_category}}".
 *
 * @property int $status_id
 * @property int $status_category_id
 *
 * @property SubscriberStatus $subscriberStatus
 * @property SubscriberStatusCategory $subscriberStatusCategory
 */
class SubscriberStatusHasCategory extends UuidActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%subscriber_status_has_category}}';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['status_id', 'status_category_id'], 'required'],
			[['status_id', 'status_category_id'], 'integer'],
			[['status_id', 'status_category_id'], 'unique', 'targetAttribute' => ['status_id', 'status_category_id']],
			[['status_id'], 'exist', 'skipOnError' => true, 'targetClass' => SubscriberStatus::class, 'targetAttribute' => ['status_id' => 'id']],
			[['status_category_id'], 'exist', 'skipOnError' => true, 'targetClass' => SubscriberStatusCategory::class, 'targetAttribute' => ['status_category_id' => 'id']],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'status_id' => Yii::t('label', 'Subscriber Status ID'),
			'status_category_id' => Yii::t('label', 'Subscriber Status Category ID'),
		];
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
	public function getSubscriberStatusCategory()
	{
		return $this->hasOne(SubscriberStatusCategory::class, ['id' => 'status_category_id']);
	}
}
