<?php

namespace backend\modules\tutorial\models;

use common\models\Language;
use common\models\TutorialCategory;
use common\models\TutorialCategoryTranslation;
use Yii;
use yii\base\InvalidCallException;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use common\helpers\Inflector;
use common\helpers\StringHelper;
use common\components\ApplicationBootstrap;

class TutorialCategoryForm extends TutorialCategory
{
	/**
	 * @var array The multilingual title of the tutorial category.
	 */
	public $title = [];

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
			[['title'], 'required', 'when' => function () {
				return empty($this->title[Yii::$app->language]);
			}, 'whenClient' => 'function (attribute, value) {
				return attribute.$form.find("[name*=\"[title][' . Yii::$app->language . ']\"]").val() == "";
			}'],
			[['title'], 'each', 'rule' => ['trim']],
            [['translator'], 'safe'],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
            'parent_id' => Yii::t('label', 'Parent Category'),
			'title' => Yii::t('label', 'Title'),
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

		$this->title = ArrayHelper::map($this->tutorialCategoryTranslations, 'language_id', 'title');
		// Expose the FK as a canonical UUID string so the parent dropdown (whose option
		// keys are UUID strings) can match the selected value; beforeSave converts back.
		$this->parent_id = $this->uuidOf('parent_id') ?: null;
	}

    /**
     * Saves the translations.
     *
     * @return bool
     */
    protected function saveTutorialCategoryTranslations()
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
                $tutorialCategoryTranslation = TutorialCategoryTranslation::findOne([
                    'tutorial_category_id' => $this->id,
                    'language_id' => $language->language_id,
                ]);
                if (!$tutorialCategoryTranslation) {
                    $tutorialCategoryTranslation = new TutorialCategoryTranslation();
                    $tutorialCategoryTranslation->tutorial_category_id = $this->id;
                    $tutorialCategoryTranslation->language_id = $language->language_id;
                }

                if (!empty($this->translator) && $language->language_id != Yii::$app->language && in_array($defaultLanguage->language, $languages) && in_array($language->language, $languages)) {
                    $source = $defaultLanguage->language;
                    $target = $language->language;
                }

                $tutorialCategoryTranslation->title = StringHelper::truncate($this->title[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->title[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, $this->title[Yii::$app->language])['data']['translations'][0]['translatedText'] : null), 240, '');
                if ($tutorialCategoryTranslation->title) {
                    $this->link('tutorialCategoryTranslations', $tutorialCategoryTranslation);
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
			if (!parent::save($runValidation, $attributeNames)) {
				throw new \Exception();
			}
			if (!$this->saveTutorialCategoryTranslations()) {
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
