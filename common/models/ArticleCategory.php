<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\caching\TagDependency;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%article_category}}".
 *
 * @property int $id
 * @property int $parent_id
 * @property string $image
 * @property string $icon
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property ArticleCategory $parent
 * @property ArticleCategory[] $articleCategories
 * @property ArticleCategoryHasArticle[] $articleCategoryHasArticles
 * @property Article[] $articles
 * @property ArticleCategoryTranslation[] $articleCategoryTranslations
 * @property ArticleCategoryTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 */
class ArticleCategory extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%article_category}}';
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
			[['parent_id', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['status'], 'required'],
			[['image', 'icon'], 'string', 'max' => 255],
			[['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => ArticleCategory::class, 'targetAttribute' => ['parent_id' => 'id']],
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
		return $this->hasOne(ArticleCategory::class, ['id' => 'parent_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getArticleCategories()
	{
		return $this->hasMany(ArticleCategory::class, ['parent_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getArticleCategoryHasArticles()
	{
		return $this->hasMany(ArticleCategoryHasArticle::class, ['article_category_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getArticles()
	{
		return $this->hasMany(Article::class, ['id' => 'article_id'])->viaTable('{{%article_category_has_article}}', ['article_category_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getArticleCategoryTranslations()
	{
		return $this->hasMany(ArticleCategoryTranslation::class, ['article_category_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param null|string $language
	 * @return null|ArticleCategoryTranslation
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->articleCategoryTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%article_category_translation}}', ['article_category_id' => 'id']);
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
	 * Finds all active records.
	 *
	 * @return static[]|array
	 */
	public static function findAllArticleCategories()
	{
		try {
			return static::getDb()->cache(function ($db) {
				return static::find()
					->alias('ac')
					->joinWith([
						'articleCategoryTranslations act' => function (ActiveQuery $query) {
							$query->andOnCondition(['act.language_id' => Yii::$app->language]);
						},
					])
					->where([
						'ac.status' => static::STATUS_ACTIVE,
						'ac.deleted' => static::NO,
					])
					->orderBy(['act.title' => SORT_ASC])
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
	public static function findArticleCategoryBySlug($slug)
	{
		return static::find()
			->alias('ac')
			->joinWith([
				'articleCategoryTranslations act' => function (ActiveQuery $query) {
					$query->andOnCondition(['act.language_id' => Yii::$app->language]);
				},
			])
			->where([
				'ac.status' => static::STATUS_ACTIVE,
				'ac.deleted' => static::NO,
				'act.slug' => $slug,
			])
			->limit(1)
			->one();
	}

	/**
	 * Finds all active records for sitemap.
	 *
	 * @return static[]|array
	 */
	public static function findSitemapArticleCategories()
	{
		return static::find()
			->alias('ac')
			->joinWith([
				'articleCategoryTranslations act',
			])
			->where([
				'ac.status' => static::STATUS_ACTIVE,
				'ac.deleted' => static::NO,
			])
			->indexBy('id')
			->all();
	}
}
