<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\caching\TagDependency;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "{{%subscriber_status_category}}".
 *
 * @property int $id
 * @property int $type
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property SubscriberStatus[] $subscriberStatuses
 * @property SubscriberStatusCategoryTranslation[] $subscriberStatusCategoryTranslations
 * @property SubscriberStatusCategoryTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 */
class SubscriberStatusCategory extends UuidActiveRecord
{
	const TYPE_TRIAL = 1;
	const TYPE_PAYER = 2;

	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%subscriber_status_category}}';
	}

	/**
	 * @inheritdoc
	 * @throws \Exception
	 */
	public function behaviors()
	{
		return [
			[
				'class' => BlameableBehavior::class,
			],
			[
				'class' => TimestampBehavior::class,
				'value' => (new \DateTime)->format('Y-m-d H:i:s'),
			],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['type', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['type', 'status'], 'required'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'type' => Yii::t('label', 'Type'),
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
	public function getSubscriberStatuses()
	{
		return $this->hasMany(SubscriberStatus::class, ['status_category_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriberStatusCategoryTranslations()
	{
		return $this->hasMany(SubscriberStatusCategoryTranslation::class, ['status_category_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param string|null $language
	 * @return mixed
	 */
	public function getTranslation($language = null)
	{
		$language = $language ?: Yii::$app->language;

		return ArrayHelper::index($this->subscriberStatusCategoryTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%subscriber_status_category_translation}}', ['status_category_id' => 'id']);
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
			self::TYPE_TRIAL => Yii::t('label', 'Trial'),
			self::TYPE_PAYER => Yii::t('label', 'Payer'),
		];
	}

	/**
	 * Finds all active records.
	 *
	 * @return mixed
	 */
	public static function findAllSubscriberStatusCategories()
	{
		try {
			return static::getDb()->cache(function ($db) {
				return static::find()
					->alias('sc')
					->joinWith([
						'subscriberStatusCategoryTranslations sct' => function (ActiveQuery $query) {
							$query->andOnCondition(['sct.language_id' => Yii::$app->language]);
						},
					])
					->where([
						'sc.status' => self::STATUS_ACTIVE,
						'sc.deleted' => self::NO,
					])
					->orderBy(['sct.name' => SORT_ASC])
					->indexBy('id')
					->all();
			}, 0, new TagDependency(['tags' => __FUNCTION__]));
		} catch (\Exception $e) {
			return [];
		} catch (\Throwable $e) {
			return [];
		}
	}
}
