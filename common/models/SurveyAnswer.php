<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\helpers\ArrayHelper;
use tws\helpers\Url;
use yii2tech\ar\position\PositionBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%survey_answer}}".
 *
 * @property int $id
 * @property int $survey_question_id
 * @property int $type
 * @property string $image
 * @property int $sort_order
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property SurveyQuestion $surveyQuestion
 * @property SurveyAnswerHasTag[] $surveyAnswerHasTags
 * @property Tag[] $tags
 * @property SurveyAnswerTranslation[] $surveyAnswerTranslations
 * @property SurveyAnswerTranslation $translation
 * @property Language[] $languages
 * @property SurveyUserAnswerHasSurveyAnswer[] $surveyUserAnswerHasSurveyAnswers
 * @property SurveyUserAnswer[] $surveyUserAnswers
 * @property User $creator
 * @property User $updater
 */
class SurveyAnswer extends UuidActiveRecord
{
	const TYPE_TEXT = 1;
	const TYPE_IMAGE = 2;

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%survey_answer}}';
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
				'groupAttributes' => ['survey_question_id'],
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
			[['survey_question_id', 'status'], 'required'],
			[['survey_question_id', 'type', 'sort_order', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['image'], 'string', 'max' => 255],
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
			'type' => Yii::t('label', 'Type'),
			'image' => Yii::t('label', 'Image'),
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
	public function getSurveyQuestion()
	{
		return $this->hasOne(SurveyQuestion::class, ['id' => 'survey_question_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSurveyAnswerHasTags()
	{
		return $this->hasMany(SurveyAnswerHasTag::class, ['survey_answer_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getTags()
	{
		return $this->hasMany(Tag::class, ['id' => 'tag_id'])->viaTable('{{%survey_answer_has_tag}}', ['survey_answer_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSurveyAnswerTranslations()
	{
		return $this->hasMany(SurveyAnswerTranslation::class, ['survey_answer_id' => 'id']);
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
		return ArrayHelper::index($this->surveyAnswerTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%survey_answer_translation}}', ['survey_answer_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSurveyUserAnswerHasSurveyAnswers()
	{
		return $this->hasMany(SurveyUserAnswerHasSurveyAnswer::class, ['survey_answer_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getSurveyUserAnswers()
	{
		return $this->hasMany(SurveyUserAnswer::class, ['id' => 'survey_user_answer_id'])->viaTable('{{%survey_user_answer_has_survey_answer}}', ['survey_answer_id' => 'id']);
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

	/**
	 * Gets the imageUrl.
	 *
	 * @param bool $scheme
	 * @return string
	 */
	public function getImageUrl($scheme = false)
	{
		return Url::to("@uploads/survey/{$this->surveyQuestion->uuidOf('survey_id')}/question/{$this->uuidOf('survey_question_id')}/{$this->image}", $scheme);
	}

	/**
	 * Model type labels.
	 *
	 * @return array
	 */
	public static function getTypeLabels()
	{
		return [
			self::TYPE_TEXT => Yii::t('label', 'Text'),
			self::TYPE_IMAGE => Yii::t('label', 'Image'),
		];
	}
}
