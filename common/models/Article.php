<?php

namespace common\models;

use tws\helpers\Url;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\Exception;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%article}}".
 *
 * @property int $id
 * @property string $image
 * @property string $icon
 * @property string $video
 * @property string $source
 * @property int $featured
 * @property int $views
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property ArticleCategoryHasArticle[] $articleCategoryHasArticles
 * @property ArticleCategory[] $articleCategories
 * @property ArticleTranslation[] $articleTranslations
 * @property ArticleTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 *
 * @property string|null $imageUrl
 */
class Article extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%article}}';
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
			[['featured', 'views', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['status'], 'required'],
			[['image', 'icon', 'video', 'source'], 'string', 'max' => 255],
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
			'source' => Yii::t('label', 'Source'),
			'featured' => Yii::t('label', 'Featured'),
			'views' => Yii::t('label', 'Views'),
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
	public function getArticleCategoryHasArticles()
	{
		return $this->hasMany(ArticleCategoryHasArticle::class, ['article_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getArticleCategories()
	{
		return $this->hasMany(ArticleCategory::class, ['id' => 'article_category_id'])->viaTable('{{%article_category_has_article}}', ['article_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getArticleTranslations()
	{
		return $this->hasMany(ArticleTranslation::class, ['article_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param null|string $language
	 * @return null|ArticleTranslation
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->articleTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%article_translation}}', ['article_id' => 'id']);
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
		return Url::to("@uploads/article/{$this->uuid}/{$this->image}", $scheme);
	}

	/**
	 * Gets the videoEmbedUrl.
	 *
	 * @param bool|string $scheme
	 * @return null|string
	 */
	public function getVideoEmbedUrl($scheme = '')
	{
		if (!$this->video) {
			return null;
		}
		$url = Url::to($this->video, $scheme);

		if (strpos($url, 'youtube.com') !== false && strpos($url, 'watch?v=') !== false) {
			$url = str_replace('watch?v=', 'embed/', $url);
		}

		return $url;
	}

	/**
	 * Gets the previous article.
	 *
	 * @return array|static|\yii\db\ActiveRecord|null
	 */
	public function getPrevArticle()
	{
		return static::findSiblingArticle($this, 'prev');
	}

	/**
	 * Gets the next article.
	 *
	 * @return array|static|\yii\db\ActiveRecord|null
	 */
	public function getNextArticle()
	{
		return static::findSiblingArticle($this, 'next');
	}

	/**
	 * Finds the next article starting from a specific model.
	 *
	 * @param static $model
	 * @param string $direction
	 * @return array|\yii\db\ActiveRecord|static|null
	 */
	public static function findSiblingArticle($model, $direction = 'next')
	{
		$query = static::find()
			->alias('a')
			->joinWith([
				'articleTranslations at' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'at.language_id' => Yii::$app->language,
						'at.deleted' => ArticleTranslation::NO,
					]);
				},
			])
			->andWhere([
				'a.status' => static::STATUS_ACTIVE,
				'a.deleted' => static::NO,
			]);

		if ($direction === 'prev') {
			$query->orderBy(['a.created_at' => SORT_DESC]);
			$query->andFilterWhere(['<', 'a.created_at', $model->created_at]);
		} elseif ($direction === 'next') {
			$query->orderBy(['a.created_at' => SORT_ASC]);
			$query->andFilterWhere(['>', 'a.created_at', $model->created_at]);
		}

		return $query->limit(1)->one();
	}

	/**
	 * Finds the latest articles.
	 *
	 * @param int $limit The limit of the results.
	 * @return array|\yii\db\ActiveRecord|static[]|null
	 */
	public static function findLatestArticles($limit = 10)
	{
		return static::find()
			->alias('a')
			->joinWith([
				'articleTranslations at' => function (ActiveQuery $query) {
					$query->andOnCondition(['at.language_id' => Yii::$app->language]);
				},
			])
			->andWhere([
				'a.status' => static::STATUS_ACTIVE,
				'a.deleted' => static::NO,
			])
			->orderBy([
				'created_at' => SORT_DESC,
			])
			->limit($limit)
			->all();
	}

	/**
	 * Finds all active records for sitemap.
	 *
	 * @return static[]|array
	 */
	public static function findSitemapArticles()
	{
		return static::find()
			->alias('a')
			->joinWith([
				'articleTranslations at',
			])
			->where([
				'a.status' => static::STATUS_ACTIVE,
				'a.deleted' => static::NO,
			])
			->indexBy('id')
			->all();
	}

	/**
	 * Provides active records for RSS Feed.
	 *
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public static function provideRssArticles()
	{
		return static::find()
			->alias('a')
			->joinWith([
				'articleTranslations at' => function (ActiveQuery $query) {
					$query->andOnCondition(['at.language_id' => Yii::$app->language]);
				},
			])
			->where([
				'a.status' => static::STATUS_ACTIVE,
				'a.deleted' => static::NO,
			])
			->orderBy([
				'a.created_at' => SORT_DESC,
			]);
	}

	/**
	 * Provides active records by filter criteria.
	 *
	 * @param null|int $category
	 * @param null|string $tag
	 * @param null|int $year
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public static function provideArticles($category = null, $tag = null, $year = null)
	{
		$query = static::find()
			->alias('a')
			->joinWith([
				'articleTranslations at' => function (ActiveQuery $query) {
					$query->andOnCondition(['at.language_id' => Yii::$app->language]);
				},
			])
			->where([
				'a.status' => static::STATUS_ACTIVE,
				'a.deleted' => static::NO,
			])
			->orderBy([
				'a.created_at' => SORT_DESC,
			]);

		if (!empty($category)) {
			$query
				->joinWith(['articleCategories c'])
				->andFilterWhere(['c.id' => $category]);
		}
		if (!empty($tag)) {
			$query
				->andWhere(new Expression('FIND_IN_SET(:tag, [[at.keywords]])'))
				->addParams(['tag' => $tag]);
		}
		if (!empty($year)) {
			$query->andWhere(['LIKE', 'a.created_at', $year]);
		}

		return $query;
	}

	/**
	 * Counts articles by year.
	 *
	 * @return array|null
	 */
	public static function countArticlesByYear()
	{
		try {
			return static::find()
				->select([
					"DATE_FORMAT([[created_at]], '%Y') AS [[year]]",
					"COUNT([[id]]) AS [[total]]",
				])
				->andWhere([
					'status' => static::STATUS_ACTIVE,
					'deleted' => static::NO,
				])
				->groupBy(new Expression("DATE_FORMAT([[created_at]], '%Y')"))
				->createCommand()
				->queryAll();
		} catch (Exception $e) {
			return [];
		}
	}
}
