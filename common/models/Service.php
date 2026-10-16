<?php

namespace common\models;

use tws\helpers\Url;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii2tech\ar\position\PositionBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%service}}".
 *
 * @property int $id
 * @property string $image
 * @property string $icon
 * @property string $video
 * @property string $color
 * @property int $featured
 * @property int $views
 * @property int $sort_order
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property ServiceCategoryHasService[] $serviceCategoryHasServices
 * @property ServiceCategory[] $serviceCategories
 * @property ServiceTranslation[] $serviceTranslations
 * @property ServiceTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 */
class Service extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%service}}';
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
			[['featured', 'views', 'sort_order', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['status'], 'required'],
			[['image', 'icon', 'video', 'color'], 'string', 'max' => 255],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'image' => Yii::t('label', 'Image'),
			'icon' => Yii::t('label', 'Icon'),
			'video' => Yii::t('label', 'Video'),
			'color' => Yii::t('label', 'Color'),
			'featured' => Yii::t('label', 'Featured'),
			'views' => Yii::t('label', 'Views'),
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
	public function getServiceCategoryHasServices()
	{
		return $this->hasMany(ServiceCategoryHasService::class, ['service_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getServiceCategories()
	{
		return $this->hasMany(ServiceCategory::class, ['id' => 'service_category_id'])->viaTable('{{%service_category_has_service}}', ['service_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getServiceTranslations()
	{
		return $this->hasMany(ServiceTranslation::class, ['service_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param null|string $language
	 * @return null|ServiceTranslation
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->serviceTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%service_translation}}', ['service_id' => 'id']);
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
	 * @param bool $scheme
	 * @return string
	 */
	public function getImageUrl($scheme = false)
	{
		return Url::to("@uploads/service/{$this->uuid}/{$this->image}", $scheme);
	}

	/**
	 * Gets the videoEmbedUrl.
	 *
	 * @param string $scheme
	 * @return null|string
	 */
	public function getVideoEmbedUrl($scheme = '')
	{
		if (!$this->video) {
			return null;
		}
		$url = \yii\helpers\Url::to($this->video, $scheme);

		if (strpos($url, 'youtube.com') !== false && strpos($url, 'watch?v=') !== false) {
			$url = str_replace('watch?v=', 'embed/', $url);
		}

		return $url;
	}

	/**
	 * Finds the featured services.
	 *
	 * @param int $limit The limit of the results.
	 * @return array|\yii\db\ActiveRecord[]|static[]|null
	 */
	public static function findFeaturedServices($limit = 6)
	{
		return static::find()
			->alias('s')
			->joinWith([
				'serviceTranslations st' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'st.language_id' => Yii::$app->language,
						'st.deleted' => ServiceTranslation::NO,
					]);
				},
			])
			->andWhere([
				's.featured' => static::YES,
				's.status' => static::STATUS_ACTIVE,
				's.deleted' => static::NO,
			])
			->orderBy(new Expression('[[s.sort_order]] IS NULL'))
			->addOrderBy(['s.sort_order' => SORT_ASC])
			->limit($limit)
			->all();
	}

	/**
	 * Provides active records by filter criteria.
	 *
	 * @param null|int $category
	 * @param null|string $tag
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public static function provideServices($category = null, $tag = null)
	{
		$query = static::find()
			->alias('s')
			->joinWith([
				'serviceTranslations st' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'st.language_id' => Yii::$app->language,
						'st.deleted' => ServiceTranslation::NO,
					]);
				},
			])
			->andWhere([
				's.status' => static::STATUS_ACTIVE,
				's.deleted' => static::NO,
			])
			->orderBy(new Expression('[[s.sort_order]] IS NULL'))
			->addOrderBy(['s.sort_order' => SORT_ASC]);

		if (!empty($category)) {
			$query
				->joinWith(['serviceCategories c'])
				->andFilterWhere(['c.id' => $category]);
		}
		if (!empty($tag)) {
			$query
				->andWhere(new Expression('FIND_IN_SET(:tag, [[st.keywords]])'))
				->addParams(['tag' => $tag]);
		}

		return $query;
	}
}
