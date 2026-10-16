<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%article_category_translation}}".
 *
 * @property int $article_category_id
 * @property string $language_id
 * @property string $title
 * @property string $slug
 * @property string $keywords
 * @property string $description
 * @property string $content
 * @property int $deleted
 *
 * @property ArticleCategory $articleCategory
 * @property Language $language
 */
class ArticleCategoryTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%article_category_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['article_category_id', 'language_id', 'title'], 'required'],
			[['article_category_id', 'deleted'], 'integer'],
			[['keywords', 'description', 'content'], 'string'],
			[['language_id'], 'string', 'max' => 5],
			[['title', 'slug'], 'string', 'max' => 255],
			[['article_category_id', 'language_id'], 'unique', 'targetAttribute' => ['article_category_id', 'language_id']],
			[['article_category_id'], 'exist', 'skipOnError' => true, 'targetClass' => ArticleCategory::class, 'targetAttribute' => ['article_category_id' => 'id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'article_category_id' => Yii::t('label', 'Article Category ID'),
			'language_id' => Yii::t('label', 'Language ID'),
			'title' => Yii::t('label', 'Title'),
			'slug' => Yii::t('label', 'Slug'),
			'keywords' => Yii::t('label', 'Keywords'),
			'description' => Yii::t('label', 'Description'),
			'content' => Yii::t('label', 'Content'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getArticleCategory()
	{
		return $this->hasOne(ArticleCategory::class, ['id' => 'article_category_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguage()
	{
		return $this->hasOne(Language::class, ['language_id' => 'language_id']);
	}

	/**
	 * Gets the keywords as array.
	 *
	 * @param string $delimiter
	 * @return array
	 */
	public function getKeywordsList($delimiter = ',')
	{
		return $this->keywords ? explode($delimiter, $this->keywords) : [];
	}
}
