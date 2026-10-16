<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%survey_user_answer_translation}}".
 *
 * @property int $survey_user_answer_id
 * @property string $language_id
 * @property string $content
 * @property int $deleted
 *
 * @property Language $language
 * @property SurveyUserAnswer $surveyUserAnswer
 */
class SurveyUserAnswerTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%survey_user_answer_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['survey_user_answer_id', 'language_id', 'content'], 'required'],
			[['survey_user_answer_id', 'deleted'], 'integer'],
			[['content'], 'string'],
			[['language_id'], 'string', 'max' => 5],
			[['survey_user_answer_id', 'language_id'], 'unique', 'targetAttribute' => ['survey_user_answer_id', 'language_id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
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
	public function getSurveyUserAnswer()
	{
		return $this->hasOne(SurveyUserAnswer::class, ['id' => 'survey_user_answer_id']);
	}
}
