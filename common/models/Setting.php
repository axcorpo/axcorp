<?php

namespace common\models;

use DateTime;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\caching\TagDependency;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%setting}}".
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $type
 * @property string $setting
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property User $user
 * @property User $creator
 * @property User $updater
 */
class Setting extends UuidActiveRecord
{
	const TYPE_APP = 1;
	const TYPE_USER = 2;

	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%setting}}';
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
			[['user_id', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['setting', 'status'], 'required'],
			[['setting'], 'string'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['name', 'type'], 'string', 'max' => 255],
			[['name'], 'unique'],
			[['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'user_id' => Yii::t('label', 'User ID'),
			'name' => Yii::t('label', 'Name'),
			'type' => Yii::t('label', 'Type'),
			'setting' => Yii::t('label', 'Setting'),
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
	public function getUser()
	{
		return $this->hasOne(User::class, ['id' => 'user_id']);
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
	 * Model type labels.
	 *
	 * @return array
	 */
	public static function getTypeLabels()
	{
		return [
			self::TYPE_APP => Yii::t('label', 'App'),
			self::TYPE_USER => Yii::t('label', 'User'),
		];
	}

	/**
	 * Finds the application settings.
	 *
	 * @return array
	 */
	public static function findAppSettings()
	{
		try {
			return static::getDb()->cache(function ($db) {
				$result = [];
				$appSettings = static::find()
					->where([
						'type' => self::TYPE_APP,
						'status' => self::STATUS_ACTIVE,
						'deleted' => self::NO,
					])
					->asArray()
					->all();

				foreach ($appSettings as $appSetting) {
					$result[$appSetting['name']] = @unserialize($appSetting['setting']);
				}

				return $result;
			}, 0, new TagDependency(['tags' => __FUNCTION__]));
		} catch (\Exception $e) {
			return [];
		} catch (\Throwable $e) {
			return [];
		}
	}
}
