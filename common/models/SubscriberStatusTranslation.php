<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%subscriber_status_translation}}".
 *
 * @property int $id
 * @property int $status_id
 * @property string $language_id
 * @property string $name
 * @property int $deleted
 *
 * @property Language $language
 * @property SubscriberStatus $subscriberStatus
 */
class SubscriberStatusTranslation extends UuidActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%subscriber_status_translation}}';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['status_id', 'language_id', 'name'], 'required'],
			[['status_id', 'deleted'], 'integer'],
			[['language_id'], 'string', 'max' => 5],
			[['name'], 'string', 'max' => 255],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
			[['status_id'], 'exist', 'skipOnError' => true, 'targetClass' => SubscriberStatus::class, 'targetAttribute' => ['status_id' => 'id']],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'status_id' => Yii::t('label', 'Repair Status ID'),
			'language_id' => Yii::t('label', 'Language ID'),
			'name' => Yii::t('label', 'Name'),
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
	public function getSubscriberStatus()
	{
		return $this->hasOne(SubscriberStatus::class, ['id' => 'status_id']);
	}
}
