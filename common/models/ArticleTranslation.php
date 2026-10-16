<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%article_translation}}".
 *
 * @property int $article_id
 * @property string $language_id
 * @property string $title
 * @property string $slug
 * @property string $keywords
 * @property string $description
 * @property string $content
 * @property int $deleted
 *
 * @property Article $article
 * @property Language $language
 */
class ArticleTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%article_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['article_id', 'language_id', 'title'], 'required'],
			[['article_id', 'deleted'], 'integer'],
			[['keywords', 'description', 'content'], 'string'],
			[['language_id'], 'string', 'max' => 5],
			[['title', 'slug'], 'string', 'max' => 255],
			[['article_id', 'language_id'], 'unique', 'targetAttribute' => ['article_id', 'language_id']],
			[['article_id'], 'exist', 'skipOnError' => true, 'targetClass' => Article::class, 'targetAttribute' => ['article_id' => 'id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'article_id' => Yii::t('label', 'Article ID'),
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
	public function getArticle()
	{
		return $this->hasOne(Article::class, ['id' => 'article_id']);
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
