<?php

namespace common\models;

use tws\behaviors\DefaultBehavior;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%carousel}}".
 *
 * @property int $id
 * @property string $config
 * @property int $default
 * @property int $type
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property CarouselItem[] $carouselItems
 * @property CarouselTranslation[] $carouselTranslations
 * @property CarouselTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 */
class Carousel extends UuidActiveRecord
{
	const TYPE_MAIN = 1;
	const TYPE_MARKETPLACE = 2;
	const TYPE_WORKSPACE = 9;

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%carousel}}';
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
			'DefaultBehavior' => [
				'class' => DefaultBehavior::class,
				'groupAttributes' => ['type'],
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
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['config'], 'string'],
			[['default', 'type', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['status'], 'required'],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'config' => Yii::t('label', 'Config'),
			'default' => Yii::t('label', 'Default'),
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
	public function getCarouselItems()
	{
		return $this->hasMany(CarouselItem::class, ['carousel_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCarouselTranslations()
	{
		return $this->hasMany(CarouselTranslation::class, ['carousel_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param string|null $language
	 * @return mixed
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->carouselTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%carousel_translation}}', ['carousel_id' => 'id']);
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
	 * Gets the unserialized config data.
	 *
	 * @return array
	 */
	public function getConfigData()
	{
		return @unserialize($this->config) ?: [];
	}

	/**
	 * Model type labels.
	 *
	 * @return array
	 */
	public static function getTypeLabels()
	{
		return [
			static::TYPE_MAIN => Yii::t('label', 'Main'),
			static::TYPE_MARKETPLACE => Yii::t('label', 'Marketplace'),
			static::TYPE_WORKSPACE => Yii::t('label', 'Workspace'),
		];
	}

	/**
	 * Gets the ordered carousel items.
	 *
	 * @return array|\yii\db\ActiveRecord[]|CarouselItem[]
	 */
	public function getOrderedCarouselItems()
	{
		return $this->getCarouselItems()
			->where([
				'status' => CarouselItem::STATUS_ACTIVE,
				'deleted' => CarouselItem::NO,
			])
			->orderBy(new Expression('[[sort_order]] IS NULL'))
			->addOrderBy(['sort_order' => SORT_ASC])
			->all();
	}

	/**
	 * Gets the carousel items.
	 *
	 * @param null|int $deleted The deleted state of the record.
	 * @return array
	 */
	public function getSortableCarouselItems($deleted = null)
	{
		$items = [];
		/** @var CarouselItem[] $carouselItems */
		$carouselItems = $this->getCarouselItems()
			->where([
				'deleted' => $deleted ?: CarouselItem::NO,
			])
			->orderBy(new Expression('[[sort_order]] IS NULL'))
			->addOrderBy(['sort_order' => SORT_ASC])
			->all();

		foreach ($carouselItems as $carouselItem) {
			$items[] = [
				// Canonical UUID string, never the raw BINARY(16) bytes: this value is
				// rendered into the sortable widget's data-id attribute and posted back.
				'id' => $carouselItem->uuid,
				'content' => $carouselItem->translation->title,
				'model' => $carouselItem,
			];
		}

		return $items;
	}

	/**
	 * Gets the model available short codes.
	 *
	 * @return array
	 */
	public static function getShortCodeItems()
	{
		$models = static::find()
			->alias('c')
			->joinWith([
				'carouselTranslations ct' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'ct.language_id' => Yii::$app->language,
						'ct.deleted' => CarouselTranslation::NO,
					]);
				},
			])
			->where([
				'c.status' => self::STATUS_ACTIVE,
				'c.deleted' => self::NO,
			])
			->all();

		return ArrayHelper::map($models, function (self $model) {
			// Canonical UUID string, never the raw BINARY(16) bytes
			return '{{CAROUSEL id="' . $model->uuid . '"}}';
		}, 'translation.name');
	}

	/**
	 * Finds the default record or fallback to the first record.
	 *
	 * @param string|null $type The type of the carousel.
	 * @param bool $fallbackToFirst Indicates if the first record should be returned when a default one does not exist.
	 * @return array|\yii\db\ActiveRecord|self|null
	 */
	public static function findDefaultCarousel($type, $fallbackToFirst = false)
	{
		$query = static::find()
			->andWhere([
				'type' => $type,
				'status' => self::STATUS_ACTIVE,
				'deleted' => self::NO,
			])
			->limit(1);

		// The default filter goes on a copy: andWhere() changes the query in place, so the
		// fallback below would otherwise still ask for a default record.
		if (!($model = (clone $query)->andWhere(['default' => self::YES])->one())) {
			if ($fallbackToFirst === true) {
				$model = $query->one();
			}
		}

		return $model;
	}
}
