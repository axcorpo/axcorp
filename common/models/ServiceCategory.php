<?php

namespace common\models;

use tws\helpers\Url;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\caching\TagDependency;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii2tech\ar\position\PositionBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%service_category}}".
 *
 * @property int $id
 * @property int $parent_id
 * @property string $image
 * @property string $icon
 * @property string $color
 * @property int $featured
 * @property int $sort_order
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property ServiceCategory $parent
 * @property ServiceCategory[] $serviceCategories
 * @property ServiceCategoryHasService[] $serviceCategoryHasServices
 * @property Service[] $services
 * @property ServiceCategoryTranslation[] $serviceCategoryTranslations
 * @property ServiceCategoryTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 */
class ServiceCategory extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%service_category}}';
	}

	/**
	 * @inheritdoc
	 * @throws \Exception
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
			'PositionBehavior' => [
				'class' => PositionBehavior::class,
				'positionAttribute' => 'sort_order',
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
			[['parent_id', 'featured', 'sort_order', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['status'], 'required'],
			[['image', 'icon', 'color'], 'string', 'max' => 255],
			[['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => ServiceCategory::class, 'targetAttribute' => ['parent_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'parent_id' => Yii::t('label', 'Parent ID'),
			'image' => Yii::t('label', 'Image'),
			'icon' => Yii::t('label', 'Icon'),
			'color' => Yii::t('label', 'Color'),
			'featured' => Yii::t('label', 'Featured'),
			'sort_order' => Yii::t('label', 'Sort Order'),
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
	public function getParent()
	{
		return $this->hasOne(ServiceCategory::class, ['id' => 'parent_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getServiceCategories()
	{
		return $this->hasMany(ServiceCategory::class, ['parent_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getServiceCategoryHasServices()
	{
		return $this->hasMany(ServiceCategoryHasService::class, ['service_category_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getServices()
	{
		return $this->hasMany(Service::class, ['id' => 'service_id'])->viaTable('{{%service_category_has_service}}', ['service_category_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getServiceCategoryTranslations()
	{
		return $this->hasMany(ServiceCategoryTranslation::class, ['service_category_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param null|string $language
	 * @return null|ServiceCategoryTranslation
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->serviceCategoryTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%service_category_translation}}', ['service_category_id' => 'id']);
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
	 * Gets the imageUrl.
	 *
	 * @return string
	 */
	public function getImageUrl()
	{
		return Url::to("@uploads/service-category/{$this->uuid}/{$this->image}");
	}

	/**
	 * Finds all active records.
	 *
	 * @return static[]|array
	 */
	public static function findAllServiceCategories()
	{
		try {
			return static::getDb()->cache(function ($db) {
				return static::find()
					->alias('sc')
					->joinWith([
						'serviceCategoryTranslations sct' => function (ActiveQuery $query) {
							$query->andOnCondition([
								'sct.language_id' => Yii::$app->language,
								'sct.deleted' => ServiceCategoryTranslation::NO,
							]);
						},
					])
					->andWhere([
						'sc.status' => static::STATUS_ACTIVE,
						'sc.deleted' => static::NO,
					])
					->orderBy(new Expression('[[sc.sort_order]] IS NULL'))
					->addOrderBy(['sc.sort_order' => SORT_ASC])
					->addOrderBy(['sct.title' => SORT_ASC])
					->indexBy('id')
					->all();
			}, 0, new TagDependency(['tags' => __FUNCTION__]));
		} catch (\Throwable $e) {
			return [];
		}
	}

	/**
	 * Finds model by slug.
	 *
	 * @param string $slug
	 * @return array|\yii\db\ActiveRecord|static|null
	 */
	public static function findServiceCategoryBySlug($slug)
	{
		return static::find()
			->alias('sc')
			->joinWith([
				'serviceCategoryTranslations sct' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'sct.language_id' => Yii::$app->language,
						'sct.deleted' => ServiceCategoryTranslation::NO,
					]);
				},
			])
			->andWhere([
				'sc.status' => static::STATUS_ACTIVE,
				'sc.deleted' => static::NO,
				'sct.slug' => $slug,
			])
			->limit(1)
			->one();
	}
}
