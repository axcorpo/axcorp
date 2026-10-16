<?php

namespace backend\modules\nomenclature\models;

use common\helpers\StringHelper;
use common\models\UnitOfMeasure;
use common\models\UnitOfMeasureTranslation;
use Yii;
use yii\base\InvalidCallException;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class UnitOfMeasureForm extends UnitOfMeasure
{
	/**
	 * @var array The multilingual name of the UnitOfMeasure.
	 */
	public $name = [];

	/**
	 * @var array The multilingual symbol of the UnitOfMeasure.
	 */
	public $symbol = [];

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
			[['name'], 'required', 'when' => function ($model) {
				return empty($model->name[Yii::$app->language]);
			}, 'whenClient' => 'function (attribute, value) {
                return attribute.$form.find("[name*=\"[name][' . Yii::$app->language . ']\"]").val() == "";
    	    }'],
			[['symbol'], 'required', 'when' => function ($model) {
				return empty($model->symbol[Yii::$app->language]);
			}, 'whenClient' => 'function (attribute, value) {
		        return attribute.$form.find("[name*=\"[symbol][' . Yii::$app->language . ']\"]").val() == "";
	        }'],
			[['name', 'symbol'], 'each', 'rule' => ['trim']],
            [['translator'], 'safe'],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'name' => Yii::t('label', 'Name'),
			'symbol' => Yii::t('label', 'Symbol'),
			'code_id' => Yii::t('label', 'e-Invoice Code'),
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

		$this->name = ArrayHelper::map($this->unitOfMeasureTranslations, 'language_id', 'name');
		$this->symbol = ArrayHelper::map($this->unitOfMeasureTranslations, 'language_id', 'symbol');
	}

	/**
	 * Saves the UnitOfMeasure translations.
	 *
	 * @return bool
	 */
	protected function saveUnitOfMeasureTranslations()
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
            $defaultLanguage = \common\models\Language::findOne(['language_id' => Yii::$app->language]);

			$source = $target = null;

			foreach (\common\models\Language::findAllLanguages() as $language) {
				$unitOfMeasure = UnitOfMeasureTranslation::findOne([
					'unit_of_measure_id' => $this->id,
					'language_id' => $language->language_id,
				]);
				if (!$unitOfMeasure) {
					$unitOfMeasure = new UnitOfMeasureTranslation();
					$unitOfMeasure->unit_of_measure_id = $this->id;
					$unitOfMeasure->language_id = $language->language_id;
				}

                if (!empty($this->translator) && $language->language_id != Yii::$app->language && in_array($defaultLanguage->language, $languages) && in_array($language->language, $languages)) {
                    $source = $defaultLanguage->language;
                    $target = $language->language;
                }
                $unitOfMeasure->name = StringHelper::truncate($this->name[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->name[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, $this->name[Yii::$app->language])['data']['translations'][0]['translatedText'] : static::ensureTranslationValue($this->name, $language->language_id)), 255, '');
                $unitOfMeasure->symbol = StringHelper::truncate($this->symbol[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->symbol[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, $this->symbol[Yii::$app->language])['data']['translations'][0]['translatedText'] : static::ensureTranslationValue($this->symbol, $language->language_id)), 255, '');

				$this->link('unitOfMeasureTranslations', $unitOfMeasure);
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
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			// Reset all default statuses if the default checkbox is checked.
			if ($this->default) {
				static::updateAll(['default' => static::NO], ['default' => static::YES]);
			}
			if (!parent::save($runValidation, $attributeNames)) {
				throw new \Exception();
			}
			if (!$this->saveUnitOfMeasureTranslations()) {
				throw new \Exception();
			}
			$dbTransaction->commit();
			return $this;
		} catch(\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}
}
