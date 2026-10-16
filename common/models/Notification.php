<?php

namespace common\models;

use DateTime;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%notification}}".
 *
 * @property int $id
 * @property string $title
 * @property string $message
 * @property string $data
 * @property int $type
 * @property string $icon
 * @property string $color
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $deleted
 *
 * @property UserHasNotification[] $userHasNotifications
 * @property User[] $users
 * @property User $creator
 * @property User $updater
 */
class Notification extends UuidActiveRecord
{
	const TYPE_APP = 1;
	const TYPE_USER = 2;

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%notification}}';
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
				'value' => (new DateTime)->format('Y-m-d H:i:s'),
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
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['title'], 'required'],
			[['message', 'data'], 'string'],
			[['type', 'created_by', 'updated_by', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['title', 'icon', 'color'], 'string', 'max' => 255],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'title' => Yii::t('label', 'Title'),
			'message' => Yii::t('label', 'Message'),
			'data' => Yii::t('label', 'Data'),
			'type' => Yii::t('label', 'Type'),
			'icon' => Yii::t('label', 'Icon'),
			'color' => Yii::t('label', 'Color'),
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
	public function getUserHasNotifications()
	{
		return $this->hasMany(UserHasNotification::class, ['notification_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUsers()
	{
		return $this->hasMany(User::class, ['id' => 'user_id'])->viaTable('{{%user_has_notification}}', ['notification_id' => 'id']);
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
	 * Gets the title formatted as HTML with icon and color.
	 *
	 * @return string
	 */
	public function getFormattedTitle()
	{
		$title = [$this->title];

		if ($this->icon) {
			$icon = Html::tag('span', null, ['class' => $this->icon]);
			if ($this->color) {
				$icon = Html::tag('span', $icon, [
					'class' => 'label label-shadow',
					'style' => ['background-color' => $this->color],
				]);
			}
			array_unshift($title, $icon);
		}

		return implode(' ', $title);
	}

	/**
	 * Truncate the message to a specific length of characters.
	 *
	 * @param int $length
	 * @return string
	 */
	public function getMessageExcerpt($length = 120)
	{
		return StringHelper::truncate(strip_tags($this->message), $length);
	}

	/**
	 * Marks the Notification as seen.
	 *
	 * @param null|int $userId
	 */
	public function markAsSeen($userId = null)
	{
		if ($userId === null) {
			$userId = Yii::$app->user->id;
		}
		/** @var UserHasNotification $userHasNotification */
		if ($userHasNotification = $this->getUserHasNotifications()->andWhere(['user_id' => $userId])->one()) {
			$userHasNotification->seen = self::YES;
			$userHasNotification->save();
		}
	}

	/**
	 * Model type labels.
	 *
	 * @return array
	 */
	public static function getTypeLabels()
	{
		return [
			self::TYPE_APP => Yii::t('label', 'Application'),
			self::TYPE_USER => Yii::t('label', 'User'),
		];
	}

	/**
	 * Finds all unseen Notification models for the current user.
	 *
	 * @return array|\yii\db\ActiveRecord[]|self[]
	 */
	public static function findAllUnseen()
	{
		return static::find()
			->alias('n')
			->joinWith([
				'userHasNotifications uhn',
			], false)
			->andWhere([
				'n.deleted' => static::NO,
				'uhn.user_id' => Yii::$app->user->id,
				'uhn.seen' => static::NO,
			])
			->groupBy(['n.id'])
			->orderBy(['n.created_at' => SORT_DESC])
			->all();
	}

	/**
	 * Creates a new notification.
	 *
	 * @param array $attributes The model attributes.
	 * @param array|null|bool $targetedUsers A false means that no user is targeted.
	 * @return \yii\db\ActiveRecord|static|null
	 */
	public static function create($attributes, $targetedUsers = null)
	{
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			$model = new static();
			// No status column on this table: a default for it made load()/validate()
			// throw on an unknown property and every notification was silently dropped.
			$attributes = array_merge([
				'type' => static::TYPE_APP,
				'icon' => 'fa fa-info',
			], $attributes);

			if ($model->load($attributes, '') && $model->save()) {
				if ($targetedUsers !== false) {
					if (!is_array($targetedUsers)) {
						$targetedUsers = (array) $targetedUsers;
					}
					if (empty($targetedUsers)) {
						$targetedUsers = array_keys(User::findAllNotificationUsers());
					}
					$targetedUsers = array_map(function ($item) use ($model) {
						return [$item, $model->id];
					}, $targetedUsers);

					static::getDb()->createCommand()->batchInsert(
						UserHasNotification::tableName(),
						['user_id', 'notification_id'],
						$targetedUsers
					)->execute();
				}
				$dbTransaction->commit();
				return $model;
			}
			throw new \Exception();
		} catch (\Exception $e) {
			$dbTransaction->rollBack();
			return null;
		}
	}
}
