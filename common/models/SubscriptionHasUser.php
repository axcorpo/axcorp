<?php

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "{{%subscription_has_user}}".
 *
 * @property int $subscription_id
 * @property int $user_id
 * @property string $token
 * @property string $created_at
 * @property string $updated_at
 *
 * @property Subscription $subscription
 * @property User $user
 */
class SubscriptionHasUser extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%subscription_has_user}}';
	}

	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'TimestampBehavior' => [
				'class' => TimestampBehavior::class,
				'value' => (new \DateTime)->format('Y-m-d H:i:s'),
				'updatedAtAttribute' => false,
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['subscription_id', 'user_id'], 'required'],
			[['subscription_id', 'user_id'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['token'], 'string', 'max' => 255],
			[['subscription_id', 'user_id'], 'unique', 'targetAttribute' => ['subscription_id', 'user_id']],
			[['subscription_id'], 'exist', 'skipOnError' => true, 'targetClass' => Subscription::class, 'targetAttribute' => ['subscription_id' => 'id']],
			[['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'subscription_id' => Yii::t('label', 'Subscription ID'),
			'user_id' => Yii::t('label', 'User ID'),
			'token' => Yii::t('label', 'Token'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
		];
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
	public function getUser()
	{
		return $this->hasOne(User::class, ['id' => 'user_id']);
	}
}
