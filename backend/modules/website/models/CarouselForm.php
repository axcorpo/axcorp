<?php

namespace backend\modules\website\models;

use common\models\Language;
use common\models\Carousel;
use common\models\CarouselTranslation;
use Yii;
use yii\base\InvalidCallException;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use common\helpers\StringHelper;

class CarouselForm extends Carousel
{
	/**
	 * @var array The multilingual name of the carousel.
	 */
	public $name = [];

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
			[['type'], 'required'],
			[['name'], 'required', 'when' => function ($model) {
				return empty($model->name[Yii::$app->language]);
			}, 'whenClient' => 'function (attribute, value) {
				return attribute.$form.find("[name*=\"[name][' . Yii::$app->language . ']\"]").val() == "";
			}'],
			[['name'], 'each', 'rule' => ['trim']],
			[['config'], 'default', 'value' => null],
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

		$this->name = ArrayHelper::map($this->carouselTranslations, 'language_id', 'name');
	}

    /**
     * Saves the translations.
     *
     * @return bool
     */
    protected function saveCarouselTranslations()
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
                $carouselTranslation = CarouselTranslation::findOne([
                    'carousel_id' => $this->id,
                    'language_id' => $language->language_id,
                ]);
                if (!$carouselTranslation) {
                    $carouselTranslation = new CarouselTranslation();
                    $carouselTranslation->carousel_id = $this->id;
                    $carouselTranslation->language_id = $language->language_id;
                }

                if (!empty($this->translator) && $language->language_id != Yii::$app->language && in_array($defaultLanguage->language, $languages) && in_array($language->language, $languages)) {
                    $source = $defaultLanguage->language;
                    $target = $language->language;
                }
                $carouselTranslation->name = StringHelper::truncate($this->name[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->name[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, $this->name[Yii::$app->language])['data']['translations'][0]['translatedText'] : null), 255, '');
                if ($carouselTranslation->name) {
                    $this->link('carouselTranslations', $carouselTranslation);
                }
            }
            return true;
        } catch (\Exception $e) {
            $this->addError('', $e->getMessage());
            return false;
        }
    }

	/**
	 * Saves the model.
	 *
	 * @return bool
	 */
	public function saveModel()
	{
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->saveCarouselTranslations()) {
				throw new \Exception();
			}
			$dbTransaction->commit();
			return true;
		} catch(\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}
}
