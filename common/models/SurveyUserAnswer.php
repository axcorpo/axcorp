<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "{{%survey_user_answer}}".
 *
 * @property int $id
 * @property int $survey_question_id
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property SurveyQuestion $surveyQuestion
 * @property SurveyUserAnswerHasSurveyAnswer[] $surveyUserAnswerHasSurveyAnswers
 * @property SurveyAnswer[] $surveyAnswers
 * @property SurveyUserAnswerTranslation[] $surveyUserAnswerTranslations
 * @property SurveyUserAnswerTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 */
class SurveyUserAnswer extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%survey_user_answer}}';
	}

	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'BlameableBehavior' => [
				'class' => BlameableBehavior::class,
			],
			'TimestampBehavior' => [
				'class' => TimestampBehavior::class,
				'value' => (new \DateTime)->format('Y-m-d H:i:s'),
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['survey_question_id', 'status'], 'required'],
			[['survey_question_id', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['survey_question_id'], 'exist', 'skipOnError' => true, 'targetClass' => SurveyQuestion::class, 'targetAttribute' => ['survey_question_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'survey_question_id' => Yii::t('label', 'Survey Question ID'),
			'created_by' => Yii::t('label', 'Created By'),
			'updated_by' => Yii::t('label', 'Updated By'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
			'status' => Yii::t('label', 'Status'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSurveyQuestion()
	{
		return $this->hasOne(SurveyQuestion::class, ['id' => 'survey_question_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSurveyUserAnswerHasSurveyAnswers()
	{
		return $this->hasMany(SurveyUserAnswerHasSurveyAnswer::class, ['survey_user_answer_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getSurveyAnswers()
	{
		return $this->hasMany(SurveyAnswer::class, ['id' => 'survey_answer_id'])->viaTable('{{%survey_user_answer_has_survey_answer}}', ['survey_user_answer_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSurveyUserAnswerTranslations()
	{
		return $this->hasMany(SurveyUserAnswerTranslation::class, ['survey_user_answer_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param string|null $language
	 * @return null|SurveyUserAnswerTranslation
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->surveyUserAnswerTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%survey_user_answer_translation}}', ['survey_user_answer_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCreator()
	{
		return $this->hasOne(User::class, ['id' => 'created_by']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUpdater()
	{
		return $this->hasOne(User::class, ['id' => 'updated_by']);
	}
}
