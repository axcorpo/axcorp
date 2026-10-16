<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%article_category_has_article}}".
 *
 * @property int $article_category_id
 * @property int $article_id
 *
 * @property Article $article
 * @property ArticleCategory $articleCategory
 */
class ArticleCategoryHasArticle extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%article_category_has_article}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['article_category_id', 'article_id'], 'required'],
			[['article_category_id', 'article_id'], 'integer'],
			[['article_category_id', 'article_id'], 'unique', 'targetAttribute' => ['article_category_id', 'article_id']],
			[['article_id'], 'exist', 'skipOnError' => true, 'targetClass' => Article::class, 'targetAttribute' => ['article_id' => 'id']],
			[['article_category_id'], 'exist', 'skipOnError' => true, 'targetClass' => ArticleCategory::class, 'targetAttribute' => ['article_category_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'article_category_id' => Yii::t('label', 'Article Category ID'),
			'article_id' => Yii::t('label', 'Article ID'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getArticle()
	{
		return $this->hasOne(Article::class, ['id' => 'article_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getArticleCategory()
	{
		return $this->hasOne(ArticleCategory::class, ['id' => 'article_category_id']);
	}
}
