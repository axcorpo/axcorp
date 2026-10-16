<?php

namespace common\models;

use DateTime;
use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "{{%user_has_notification}}".
 *
 * @property int $user_id
 * @property int $notification_id
 * @property int $seen Indicates if the notification was seen by the user.
 * @property string $updated_at
 *
 * @property Notification $notification
 * @property User $user
 */
class UserHasNotification extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%user_has_notification}}';
	}

	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'TimestampBehavior' => [
				'class' => TimestampBehavior::class,
				'createdAtAttribute' => false,
				'value' => (new DateTime)->format('Y-m-d H:i:s'),
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['user_id', 'notification_id'], 'required'],
			[['user_id', 'notification_id', 'seen'], 'integer'],
			[['updated_at'], 'safe'],
			[['updated_at'], 'default'],
			[['user_id', 'notification_id'], 'unique', 'targetAttribute' => ['user_id', 'notification_id']],
			[['notification_id'], 'exist', 'skipOnError' => true, 'targetClass' => Notification::class, 'targetAttribute' => ['notification_id' => 'id']],
			[['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'user_id' => Yii::t('label', 'User ID'),
			'notification_id' => Yii::t('label', 'Notification ID'),
			'seen' => Yii::t('label', 'Seen'),
			'updated_at' => Yii::t('label', 'Updated At'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getNotification()
	{
		return $this->hasOne(Notification::class, ['id' => 'notification_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUser()
	{
		return $this->hasOne(User::class, ['id' => 'user_id']);
	}
}
