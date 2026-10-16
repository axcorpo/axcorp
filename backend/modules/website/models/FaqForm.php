<?php

namespace backend\modules\website\models;

use common\components\ApplicationBootstrap;
use common\helpers\StringHelper;
use common\models\Language;
use common\models\Survey;
use common\models\SurveyAnswer;
use common\models\SurveyAnswerTranslation;
use common\models\SurveyQuestion;
use common\models\SurveyQuestionTranslation;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class FaqForm extends SurveyQuestion
{
	/**
	 * @var array The multilingual content of the survey question.
	 */
	public $content = [];

	/**
	 * @var array The multilingual answer of the survey question.
	 */
	public $answer = [];

    /**
     * @var array Enable google translator.
     */
    public $translator = [];


	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->status = static::STATUS_ACTIVE;
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return ArrayHelper::merge(parent::rules(), [
			[['content'], 'required', 'when' => function ($model) {
				return empty($model->content[Yii::$app->language]);
			}, 'whenClient' => 'function (attribute, value) {
				return attribute.$form.find("[name*=\"[content][' . Yii::$app->language . ']\"]").val() == "";
			}'],
			[['answer'], 'required', 'when' => function ($model) {
				return empty($model->answer[Yii::$app->language]);
			}, 'whenClient' => 'function (attribute, value) {
				return attribute.$form.find("[name*=\"[answer][' . Yii::$app->language . ']\"]").val() == "";
			}'],
			[['content', 'answer'], 'each', 'rule' => ['trim']],
			[['content', 'answer'], 'default', 'value' => null],
            [['translator'], 'safe'],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'content' => Yii::t('label', 'Question'),
			'answer' => Yii::t('label', 'Answer'),
            'translator' => Yii::t('label', 'Translator'),
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return Model::scenarios();
	}

	/**
	 * @inheritdoc
	 */
	public function afterFind()
	{
		parent::afterFind();

		$this->content = ArrayHelper::map($this->surveyQuestionTranslations, 'language_id', 'content');

		/** @var SurveyAnswer $answer */
		if ($answer = $this->getSurveyAnswers()->active()->deleted(false)->limit(1)->one()) {
			$this->answer = ArrayHelper::map($answer->surveyAnswerTranslations, 'language_id', 'content');
		}
	}

	/**
	 * Saves the survey.
	 *
	 * @return bool
	 */
	protected function saveSurvey()
	{
		try {
			$survey = Survey::findOne([
				'type' => Survey::TYPE_FAQ,
				'default' => Survey::YES,
				'deleted' => Survey::NO,
			]);
			if (!$survey) {
				$survey = new Survey();
				$survey->type = Survey::TYPE_FAQ;
				$survey->default = Survey::YES;
				$survey->status = Survey::STATUS_ACTIVE;
				if (!$survey->save()) {
					throw new \Exception($survey->getErrorSummary(false)[0]);
				}
			}
			$this->survey_id = $survey->id;

			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
	}

    /**
     * Saves the translations.
     *
     * @return bool
     */
    protected function saveSurveyQuestionTranslations()
    {
        try {
            $languages = [];
            if (!empty($this->translator)) {
            	try {
            		$languages = ArrayHelper::getColumn(Yii::$app->translate->discover()['data']['languages'], 'language');
            	} catch (\Throwable $e) {
            		Yii::error($e, __METHOD__);
            	}
            }
            $defaultLanguage = Language::findOne(['language_id' => Yii::$app->language]);

            $source = $target = null;

            foreach (Language::findAllLanguages() as $language) {
                $surveyQuestionTranslation = SurveyQuestionTranslation::findOne([
                    'survey_question_id' => $this->id,
                    'language_id' => $language->language_id,
                ]);
                if (!$surveyQuestionTranslation) {
                    $surveyQuestionTranslation = new SurveyQuestionTranslation();
                    $surveyQuestionTranslation->survey_question_id = $this->id;
                    $surveyQuestionTranslation->language_id = $language->language_id;
                }

                if (!empty($this->translator) && $language->language_id != Yii::$app->language && in_array($defaultLanguage->language, $languages) && in_array($language->language, $languages)) {
                    $source = $defaultLanguage->language;
                    $target = $language->language;
                }
                $parts = StringHelper::splitString($this->content[$defaultLanguage->language_id], 5000);
                $translations = [];
                foreach ($parts as $part) {
                    $translation = html_entity_decode($part);
                    $translation = $source && $target ? Yii::$app->translate->translate($source, $target, $translation)['data']['translations'][0]['translatedText'] : null;
                    $translation = html_entity_decode($translation);
                    $translation = ApplicationBootstrap::recursiveStripTags($translation);
                    $translation = str_replace(['& nbsp;', '&nbsp;', '< / p>'], [' ', ' ', ''], $translation);
                    $translations[] = $translation;
                }
                $surveyQuestionTranslation->content = $this->content[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->content[$language->language_id] : (!empty($translations) ? implode('.', $translations) : null);
                if ($surveyQuestionTranslation->content) {
                    $this->link('surveyQuestionTranslations', $surveyQuestionTranslation);
                }
            }
            return true;
        } catch (\Exception $e) {
            $this->addError('', $e->getMessage());
            return false;
        }
    }

	/**
	 * Saves the answer.
	 *
	 * @return bool
	 */
	protected function saveSurveyQuestionAnswers()
	{
		try {
			$surveyAnswer = SurveyAnswer::findOne([
				'survey_question_id' => $this->id,
				'deleted' => SurveyAnswer::NO,
			]);
			if (!$surveyAnswer) {
				$surveyAnswer = new SurveyAnswer();
				$surveyAnswer->survey_question_id = $this->id;
				$surveyAnswer->type = SurveyAnswer::TYPE_TEXT;
				$surveyAnswer->status = SurveyAnswer::STATUS_ACTIVE;
				if (!$surveyAnswer->save()) {
					throw new \Exception($surveyAnswer->getErrorSummary(false)[0]);
				}
			}

            $languages = ArrayHelper::getColumn(Yii::$app->translate->discover()['data']['languages'], 'language');
            $defaultLanguage = Language::findOne(['language_id' => Yii::$app->language]);

            foreach (Language::findAllLanguages() as $language) {
                $surveyAnswerTranslation = SurveyAnswerTranslation::findOne([
                    'survey_answer_id' => $surveyAnswer->id,
                    'language_id' => $language->language_id,
                ]);
                if (!$surveyAnswerTranslation) {
                    $surveyAnswerTranslation = new SurveyAnswerTranslation();
                    $surveyAnswerTranslation->language_id = $language->language_id;
                }

                if (!empty($this->translator) && $language->language_id != Yii::$app->language && in_array($defaultLanguage->language, $languages) && in_array($language->language, $languages)) {
                    $source = $defaultLanguage->language;
                    $target = $language->language;
                }
                $parts = StringHelper::splitString($this->answer[$defaultLanguage->language_id], 5000);
                $translations = [];
                foreach ($parts as $part) {
                    $translation = html_entity_decode($part);
                    $translation = $source && $target ? Yii::$app->translate->translate($source, $target, $translation)['data']['translations'][0]['translatedText'] : null;
                    $translation = html_entity_decode($translation);
                    $translation = ApplicationBootstrap::recursiveStripTags($translation);
                    $translation = str_replace(['& nbsp;', '&nbsp;', '< / p>'], [' ', ' ', ''], $translation);
                    $translations[] = $translation;
                }
                $surveyAnswerTranslation->content = $this->answer[$language->language_id] ?: (!empty($translations) ? implode('.', $translations) : null);
                if ($surveyAnswerTranslation->content) {
                    $surveyAnswer->link('surveyAnswerTranslations', $surveyAnswerTranslation);
                }
            }

			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function save($runValidation = true, $attributeNames = null)
	{
		$transaction = static::getDb()->beginTransaction();
		try {
			if (!$this->saveSurvey()) {
				throw new \Exception();
			}
			if (!parent::save($runValidation, $attributeNames)) {
				throw new \Exception();
			}
			if (!$this->saveSurveyQuestionTranslations()) {
				throw new \Exception();
			}
			if (!$this->saveSurveyQuestionAnswers()) {
				throw new \Exception();
			}
			$transaction->commit();
			return true;
		} catch(\Exception $e) {
			$transaction->rollBack();
			Yii::error(['message' => $e->getMessage(), 'modelErrors' => $this->errors, 'exception' => (string) $e], __METHOD__);
			return false;
		}
	}
}
