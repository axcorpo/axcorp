<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\helpers\ArrayHelper;
use yii2tech\ar\position\PositionBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%survey_question}}".
 *
 * @property int $id
 * @property int $survey_id
 * @property int $multiple_answers
 * @property int $custom_answer
 * @property int $featured
 * @property int $sort_order
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property SurveyAnswer[] $surveyAnswers
 * @property Survey $survey
 * @property SurveyQuestionTranslation[] $surveyQuestionTranslations
 * @property SurveyQuestionTranslation $translation
 * @property Language[] $languages
 * @property SurveyUserAnswer[] $surveyUserAnswers
 * @property User $creator
 * @property User $updater
 */
class SurveyQuestion extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%survey_question}}';
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
			'PositionBehavior' => [
				'class' => PositionBehavior::class,
				'positionAttribute' => 'sort_order',
				'groupAttributes' => ['survey_id'],
			],
			'SoftDeleteBehavior' => [
				'class' => SoftDeleteBehavior::class,
				'softDeleteAttributeValues' => [
					'deleted' => static::YES,
				],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['survey_id', 'status'], 'required'],
			[['survey_id', 'multiple_answers', 'custom_answer', 'featured', 'sort_order', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['survey_id'], 'exist', 'skipOnError' => true, 'targetClass' => Survey::class, 'targetAttribute' => ['survey_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'survey_id' => Yii::t('label', 'Survey ID'),
			'multiple_answers' => Yii::t('label', 'Multiple Answers'),
			'custom_answer' => Yii::t('label', 'Custom Answer'),
			'featured' => Yii::t('label', 'Featured'),
			'sort_order' => Yii::t('label', 'Sort Order'),
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
	public function getSurveyAnswers()
	{
		return $this->hasMany(SurveyAnswer::class, ['survey_question_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSurvey()
	{
		return $this->hasOne(Survey::class, ['id' => 'survey_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSurveyQuestionTranslations()
	{
		return $this->hasMany(SurveyQuestionTranslation::class, ['survey_question_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param string|null $language
	 * @return mixed
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->surveyQuestionTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%survey_question_translation}}', ['survey_question_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSurveyUserAnswers()
	{
		return $this->hasMany(SurveyUserAnswer::class, ['survey_question_id' => 'id']);
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
