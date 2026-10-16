<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%tutorial_category_translation}}".
 *
 * @property int $tutorial_category_id
 * @property string $language_id
 * @property string $title
 * @property int $deleted
 *
 * @property Language $language
 * @property TutorialCategory $tutorialCategory
 */
class TutorialCategoryTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%tutorial_category_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['tutorial_category_id', 'language_id', 'title'], 'required'],
			[['tutorial_category_id', 'deleted'], 'integer'],
			[['content'], 'string'],
			[['language_id'], 'string', 'max' => 5],
			[['title'], 'string', 'max' => 255],
			[['tutorial_category_id', 'language_id'], 'unique', 'targetAttribute' => ['tutorial_category_id', 'language_id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
			[['tutorial_category_id'], 'exist', 'skipOnError' => true, 'targetClass' => TutorialCategory::class, 'targetAttribute' => ['tutorial_category_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'tutorial_category_id' => Yii::t('label', 'Tutorial Category ID'),
			'language_id' => Yii::t('label', 'Language ID'),
			'title' => Yii::t('label', 'Title'),
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
	public function getTutorialCategory()
	{
		return $this->hasOne(TutorialCategory::class, ['id' => 'tutorial_category_id']);
	}
}
