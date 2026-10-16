<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%survey_question_translation}}".
 *
 * @property int $survey_question_id
 * @property string $language_id
 * @property string $content
 * @property int $deleted
 *
 * @property Language $language
 * @property SurveyQuestion $surveyQuestion
 */
class SurveyQuestionTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%survey_question_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['survey_question_id', 'language_id', 'content'], 'required'],
			[['survey_question_id', 'deleted'], 'integer'],
			[['content'], 'string'],
			[['language_id'], 'string', 'max' => 5],
			[['survey_question_id', 'language_id'], 'unique', 'targetAttribute' => ['survey_question_id', 'language_id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
			[['survey_question_id'], 'exist', 'skipOnError' => true, 'targetClass' => SurveyQuestion::class, 'targetAttribute' => ['survey_question_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'survey_question_id' => Yii::t('label', 'Survey Question ID'),
			'language_id' => Yii::t('label', 'Language ID'),
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
	public function getSurveyQuestion()
	{
		return $this->hasOne(SurveyQuestion::class, ['id' => 'survey_question_id']);
	}
}
