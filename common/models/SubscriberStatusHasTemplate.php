<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%subscriber_status_has_template}}".
 *
 * @property int $status_id
 * @property int $template_id
 *
 * @property SubscriberStatus $subscriberStatus
 * @property Template $template
 */
class SubscriberStatusHasTemplate extends UuidActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%subscriber_status_has_template}}';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['status_id', 'template_id'], 'required'],
			[['status_id', 'template_id'], 'integer'],
			[['status_id', 'template_id'], 'unique', 'targetAttribute' => ['status_id', 'template_id']],
			[['status_id'], 'exist', 'skipOnError' => true, 'targetClass' => SubscriberStatus::class, 'targetAttribute' => ['status_id' => 'id']],
			[['template_id'], 'exist', 'skipOnError' => true, 'targetClass' => Template::class, 'targetAttribute' => ['template_id' => 'id']],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'status_id' => Yii::t('label', 'Status ID'),
			'template_id' => Yii::t('label', 'Template ID'),
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
	public function getTemplate()
	{
		return $this->hasOne(Template::class, ['id' => 'template_id']);
	}
}
