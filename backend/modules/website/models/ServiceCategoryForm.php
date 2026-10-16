<?php

namespace backend\modules\website\models;

use common\components\ApplicationBootstrap;
use common\helpers\StringHelper;
use common\models\Language;
use common\models\ServiceCategory;
use common\models\ServiceCategoryTranslation;
use Yii;
use yii\base\InvalidCallException;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;

class ServiceCategoryForm extends ServiceCategory
{
	/**
	 * @var array The multilingual title of the service category.
	 */
	public $title = [];

	/**
	 * @var array The multilingual keywords of the service category.
	 */
	public $keywords = [];

	/**
	 * @var array The multilingual description of the service category.
	 */
	public $description = [];

	/**
	 * @var array The multilingual content of the service category.
	 */
	public $content = [];

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
			[['title', 'keywords', 'description', 'content'], 'each', 'rule' => ['trim']],
			[['keywords', 'description', 'content'], 'each', 'rule' => ['default', 'value' => null]],
            [['translator'], 'safe'],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'title' => Yii::t('label', 'Title'),
			'keywords' => Yii::t('label', 'Keywords'),
			'description' => Yii::t('label', 'Description'),
			'content' => Yii::t('label', 'Content'),
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

		$this->title = ArrayHelper::map($this->serviceCategoryTranslations, 'language_id', 'title');
		$this->keywords = ArrayHelper::map($this->serviceCategoryTranslations, 'language_id', 'keywordsList');
		$this->description = ArrayHelper::map($this->serviceCategoryTranslations, 'language_id', 'description');
		$this->content = ArrayHelper::map($this->serviceCategoryTranslations, 'language_id', 'content');
	}

    /**
     * Saves the translations.
     *
     * @return bool
     */
    protected function saveServiceCategoryTranslations()
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
                $serviceCategoryTranslation = ServiceCategoryTranslation::findOne([
                    'service_category_id' => $this->id,
                    'language_id' => $language->language_id,
                ]);
                if (!$serviceCategoryTranslation) {
                    $serviceCategoryTranslation = new ServiceCategoryTranslation();
                    $serviceCategoryTranslation->service_category_id = $this->id;
                    $serviceCategoryTranslation->language_id = $language->language_id;
                }

                if (!empty($this->translator) && $language->language_id != Yii::$app->language && in_array($defaultLanguage->language, $languages) && in_array($language->language, $languages)) {
                    $source = $defaultLanguage->language;
                    $target = $language->language;
                }

                $serviceCategoryTranslation->title = StringHelper::truncate($this->title[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->title[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, $this->title[Yii::$app->language])['data']['translations'][0]['translatedText'] : null), 240, '');
                $serviceCategoryTranslation->slug = Inflector::slug($serviceCategoryTranslation->title) ?: null;
                $serviceCategoryTranslation->keywords = StringHelper::truncate($this->keywords[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? implode(',', (array) $this->keywords[$language->language_id]) : ($source && $target ? Yii::$app->translate->translate($source, $target, implode(',', (array) $this->keywords[Yii::$app->language]))['data']['translations'][0]['translatedText'] : null), 255, '');
                $serviceCategoryTranslation->description = StringHelper::truncate($this->description[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->description[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, $this->description[Yii::$app->language])['data']['translations'][0]['translatedText'] : null), 255, '');
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
                $serviceCategoryTranslation->content = $this->content[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->content[$language->language_id] : (!empty($translations) ? implode('.', $translations) : null);
                if ($serviceCategoryTranslation->title) {
                    $this->link('serviceCategoryTranslations', $serviceCategoryTranslation);
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
			if (!$this->saveServiceCategoryTranslations()) {
				throw new \Exception();
			}
			$transaction->commit();
			return true;
		} catch(\Exception $e) {
			$transaction->rollBack();
			return false;
		}
	}
}
