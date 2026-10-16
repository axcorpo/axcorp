<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%page_translation}}".
 *
 * @property int $page_id
 * @property string $language_id
 * @property string $title
 * @property string $slug
 * @property string $keywords
 * @property string $description
 * @property string $content
 * @property int $deleted
 *
 * @property Language $language
 * @property Page $page
 */
class PageTranslation extends UuidActiveRecord
{
	const NO = 0;
	const YES = 1;

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%page_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['page_id', 'language_id', 'title', 'slug'], 'required'],
			[['page_id', 'deleted'], 'integer'],
			[['content', 'keywords', 'description'], 'string'],
			[['language_id'], 'string', 'max' => 5],
			[['title', 'slug'], 'string', 'max' => 255],
			[['page_id', 'language_id'], 'unique', 'targetAttribute' => ['page_id', 'language_id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
			[['page_id'], 'exist', 'skipOnError' => true, 'targetClass' => Page::class, 'targetAttribute' => ['page_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'page_id' => Yii::t('label', 'Page ID'),
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
	public function getLanguage()
	{
		return $this->hasOne(Language::class, ['language_id' => 'language_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getPage()
	{
		return $this->hasOne(Page::class, ['id' => 'page_id']);
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
