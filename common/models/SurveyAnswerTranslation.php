<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%survey_answer_translation}}".
 *
 * @property int $survey_answer_id
 * @property string $language_id
 * @property string $content
 * @property int $deleted
 *
 * @property Language $language
 * @property SurveyAnswer $surveyAnswer
 */
class SurveyAnswerTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%survey_answer_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['survey_answer_id', 'language_id', 'content'], 'required'],
			[['survey_answer_id', 'deleted'], 'integer'],
			[['content'], 'string'],
			[['language_id'], 'string', 'max' => 5],
			[['survey_answer_id', 'language_id'], 'unique', 'targetAttribute' => ['survey_answer_id', 'language_id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
			[['survey_answer_id'], 'exist', 'skipOnError' => true, 'targetClass' => SurveyAnswer::class, 'targetAttribute' => ['survey_answer_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'survey_answer_id' => Yii::t('label', 'Survey Answer ID'),
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
	public function getSurveyAnswer()
	{
		return $this->hasOne(SurveyAnswer::class, ['id' => 'survey_answer_id']);
	}
}
