<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%service_category_translation}}".
 *
 * @property int $service_category_id
 * @property string $language_id
 * @property string $title
 * @property string $slug
 * @property string $keywords
 * @property string $description
 * @property string $content
 * @property int $deleted
 *
 * @property Language $language
 * @property ServiceCategory $serviceCategory
 */
class ServiceCategoryTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%service_category_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['service_category_id', 'language_id', 'title'], 'required'],
			[['service_category_id', 'deleted'], 'integer'],
			[['content', 'keywords', 'description'], 'string'],
			[['language_id'], 'string', 'max' => 5],
			[['title', 'slug'], 'string', 'max' => 255],
			[['service_category_id', 'language_id'], 'unique', 'targetAttribute' => ['service_category_id', 'language_id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
			[['service_category_id'], 'exist', 'skipOnError' => true, 'targetClass' => ServiceCategory::class, 'targetAttribute' => ['service_category_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'service_category_id' => Yii::t('label', 'Service Category ID'),
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
	public function getServiceCategory()
	{
		return $this->hasOne(ServiceCategory::class, ['id' => 'service_category_id']);
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
