<?php

namespace backend\modules\nomenclature\models;

use common\components\ApplicationBootstrap;
use common\helpers\StringHelper;
use common\models\Language;
use common\models\Template;
use common\models\TemplateTranslation;
use Yii;
use yii\base\InvalidCallException;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class InvoiceTemplateForm extends Template
{
	/**
	 * @var array The multilingual name of the Template.
	 */
	public $name;

	/**
	 * @var array The multilingual content of the Template.
	 */
	public $content;

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
		$this->type = self::TYPE_INVOICE;
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
			[['content'], 'required', 'when' => function ($model) {
				return empty($model->name[Yii::$app->language]);
			}, 'whenClient' => 'function (attribute, value) {
        return attribute.$form.find("[name*=\"[content][' . Yii::$app->language . ']\"]").val() == "";
    	}'],
			[['name', 'content'], 'each', 'rule' => ['trim']],
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
			'content' => Yii::t('label', 'Content'),
			'type' => Yii::t('label', 'Type'),
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

		$this->name = ArrayHelper::map($this->templateTranslations, 'language_id', 'name');
		$this->content = ArrayHelper::map($this->templateTranslations, 'language_id', 'content');
	}

    /**
     * Saves the translations.
     *
     * @return bool
     */
    protected function saveTemplateTranslations()
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

            $shortCodes = []; $reverseShortCodes = [];
            foreach (array_keys(\backend\modules\nomenclature\models\InvoiceTemplateForm::getShortCodeItems()) as $key) {
                $shortCodes[$key] = md5($key);
            }
            $reverseShortCodes = array_flip($shortCodes);

            $source = $target = null;

            foreach (Language::findAllLanguages() as $language) {
                $templateTranslation = TemplateTranslation::findOne([
                    'template_id' => $this->id,
                    'language_id' => $language->language_id,
                ]);
                if (!$templateTranslation) {
                    $templateTranslation = new TemplateTranslation();
                    $templateTranslation->template_id = $this->id;
                    $templateTranslation->language_id = $language->language_id;
                }

                if (!empty($this->translator) && $language->language_id != Yii::$app->language && in_array($defaultLanguage->language, $languages) && in_array($language->language, $languages)) {
                    $source = $defaultLanguage->language;
                    $target = $language->language;
                }
                $templateTranslation->name = StringHelper::truncate($this->name[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->name[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, $this->name[Yii::$app->language])['data']['translations'][0]['translatedText'] : null), 255, '');
                $parts = StringHelper::splitString(strtr($this->content[$defaultLanguage->language_id], $shortCodes), 5000);
                $translations = [];
                foreach ($parts as $part) {
                    $translation = html_entity_decode($part);
                    $translation = $source && $target ? Yii::$app->translate->translate($source, $target, $translation)['data']['translations'][0]['translatedText'] : null;
                    $translation = html_entity_decode($translation);
                    $translation = ApplicationBootstrap::recursiveStripTags($translation);
                    $translation = str_replace(['& nbsp;', '&nbsp;', '< / p>'], [' ', ' ', ''], $translation);
                    $translation = strtr($translation, $reverseShortCodes);
                    $translations[] = $translation;
                }
                $templateTranslation->content = $this->content[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->content[$language->language_id] : (!empty($translations) ? implode('.', $translations) : null);
                if ($templateTranslation->name) {
                    $this->link('templateTranslations', $templateTranslation);
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

	/**
	 * Saves the model.
	 *
	 * @return bool|\yii\db\ActiveRecord
	 */
	public function saveModel()
	{
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->saveTemplateTranslations()) {
				throw new \Exception();
			}
			$dbTransaction->commit();
			return $this;
		} catch(\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}

	/**
	 * Gets the short code items.
	 *
	 * @return array
	 */
	public static function getShortCodeItems()
	{
		return [
			'{{APP_NAME}}' => Yii::t('label', 'Application Name'),
			'{{APP_URL}}' => Yii::t('label', 'Application URL'),
			'{{APP_LOGO_URL}}' => Yii::t('label', 'Application Logo URL'),
			'{{APP_LOGO_ALT_URL}}' => Yii::t('label', 'Application Logo Alternative URL'),
            '{{DOCUMENT_NUMBER}}' => Yii::t('label', 'Document Number'),
            '{{ISSUED_AT}}' => Yii::t('label', 'Issued At'),
            '{{DUE_AT}}' => Yii::t('label', 'Due At'),
			'{{COMPANY_NAME}}' => Yii::t('label', 'Company Name'),
			'{{COMPANY_TIN}}' => Yii::t('label', 'Company Taxpayer Identification Number'),
			'{{COMPANY_REG_NO}}' => Yii::t('label', 'Company Registration Number'),
			'{{COMPANY_LEGAL_REPRESENTATIVE}}' => Yii::t('label', 'Company Legal Representative'),
			'{{COMPANY_EMAIL}}' => Yii::t('label', 'Company Email'),
			'{{COMPANY_PHONE}}' => Yii::t('label', 'Company Phone'),
			'{{COMPANY_FAX}}' => Yii::t('label', 'Company Fax'),
			'{{COMPANY_ADDRESS}}' => Yii::t('label', 'Company Address'),
			'{{PAYMENT_METHOD}}' => Yii::t('label', 'Payment Method'),
			'{{PAYMENT_PROCESSOR}}' => Yii::t('label', 'Payment Processor'),
			'{{TOTAL_AMOUNT}}' => Yii::t('label', 'Total Amount'),
			'{{ITEMS_LIST}}' => Yii::t('label', 'Items List'),
			'{{CLIENT_NAME}}' => Yii::t('label', 'Client Name'),
			'{{CLIENT_PIN}}' => Yii::t('label', 'Client Personal Identification Number'),
			'{{CLIENT_TIN}}' => Yii::t('label', 'Client Taxpayer Identification Number'),
			'{{CLIENT_REG_NO}}' => Yii::t('label', 'Client Registration Number'),
			'{{CLIENT_LEGAL_REPRESENTATIVE}}' => Yii::t('label', 'Client Legal Representative'),
			'{{CLIENT_EMAIL}}' => Yii::t('label', 'Client Email'),
			'{{CLIENT_PHONE}}' => Yii::t('label', 'Client Phone'),
			'{{CLIENT_FAX}}' => Yii::t('label', 'Client Fax'),
			'{{CLIENT_ADDRESS}}' => Yii::t('label', 'Client Address'),
		];
	}
}
