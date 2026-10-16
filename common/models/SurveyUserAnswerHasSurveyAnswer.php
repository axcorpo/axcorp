<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%survey_user_answer_has_survey_answer}}".
 *
 * @property int $survey_user_answer_id
 * @property int $survey_answer_id
 *
 * @property SurveyAnswer $surveyAnswer
 * @property SurveyUserAnswer $surveyUserAnswer
 */
class SurveyUserAnswerHasSurveyAnswer extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%survey_user_answer_has_survey_answer}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['survey_user_answer_id', 'survey_answer_id'], 'required'],
			[['survey_user_answer_id', 'survey_answer_id'], 'integer'],
			[['survey_user_answer_id', 'survey_answer_id'], 'unique', 'targetAttribute' => ['survey_user_answer_id', 'survey_answer_id']],
			[['survey_answer_id'], 'exist', 'skipOnError' => true, 'targetClass' => SurveyAnswer::class, 'targetAttribute' => ['survey_answer_id' => 'id']],
			[['survey_user_answer_id'], 'exist', 'skipOnError' => true, 'targetClass' => SurveyUserAnswer::class, 'targetAttribute' => ['survey_user_answer_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'survey_user_answer_id' => Yii::t('label', 'Survey User Answer ID'),
			'survey_answer_id' => Yii::t('label', 'Survey Answer ID'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSurveyAnswer()
	{
		return $this->hasOne(SurveyAnswer::class, ['id' => 'survey_answer_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSurveyUserAnswer()
	{
		return $this->hasOne(SurveyUserAnswer::class, ['id' => 'survey_user_answer_id']);
	}
}
