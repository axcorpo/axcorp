<?php

namespace backend\modules\nomenclature\models;

use backend\modules\nomenclature\models\InvoiceTemplateForm;
use common\components\ApplicationBootstrap;
use common\helpers\StringHelper;
use common\models\Language;
use common\models\Template;
use common\models\TemplateTranslation;
use Yii;
use yii\base\InvalidCallException;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class EmailTemplateForm extends Template
{
	/**
	 * @var array The multilingual name of the Template.
	 */
	public $name;

	/**
	 * @var array The multilingual subject of the Template.
	 */
	public $subject;

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

		$this->type = static::TYPE_EMAIL;
		$this->status = static::STATUS_ACTIVE;
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return ArrayHelper::merge(parent::rules(), [
			[['variant'], 'required'],
			[['name'], 'required', 'when' => function ($model) {
				return empty($model->name[Yii::$app->language]);
			}, 'whenClient' => 'function (attribute, value) {
        return attribute.$form.find("[name*=\"[name][' . Yii::$app->language . ']\"]").val() == "";
    	}'],
			[['subject'], 'required', 'when' => function ($model) {
				return empty($model->name[Yii::$app->language]);
			}, 'whenClient' => 'function (attribute, value) {
        return attribute.$form.find("[name*=\"[subject][' . Yii::$app->language . ']\"]").val() == "";
    	}'],
			[['content'], 'required', 'when' => function ($model) {
				return empty($model->name[Yii::$app->language]);
			}, 'whenClient' => 'function (attribute, value) {
        return attribute.$form.find("[name*=\"[content][' . Yii::$app->language . ']\"]").val() == "";
    	}'],
			[['name', 'subject', 'content'], 'each', 'rule' => ['trim']],
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
			'subject' => Yii::t('label', 'Subject'),
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

		$this->name = ArrayHelper::map($this->templateTranslations, 'language_id', 'name');
		$this->subject = ArrayHelper::map($this->templateTranslations, 'language_id', 'subject');
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
            foreach (array_keys(\backend\modules\nomenclature\models\EmailTemplateForm::getShortCodeItems($this->variant)) as $key) {
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
                $translation = StringHelper::truncate($this->subject[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->subject[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, strtr($this->subject[Yii::$app->language], $shortCodes))['data']['translations'][0]['translatedText'] : null), 255, '');
                $templateTranslation->subject = strtr($translation, $reverseShortCodes);
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
	 * @param null|int $variant
	 * @return array
	 */
	public static function getShortCodeItems($variant = null)
	{
		$shortCodeItems = [
			'{{APP_NAME}}' => Yii::t('label', 'Application Name'),
			'{{APP_URL}}' => Yii::t('label', 'Application URL'),
			'{{APP_LOGO_URL}}' => Yii::t('label', 'Application Logo URL'),
			'{{APP_LOGO_ALT_URL}}' => Yii::t('label', 'Application Logo Alternative URL'),
		];

		switch ($variant) {
			case static::EMAIL_VARIANT_ACCOUNT_ACTIVATION:
				$shortCodeItems = array_merge($shortCodeItems, [
					'{{FIRST_NAME}}' => Yii::t('label', 'First Name'),
					'{{MIDDLE_NAME}}' => Yii::t('label', 'Middle Name'),
					'{{LAST_NAME}}' => Yii::t('label', 'Last Name'),
					'{{EMAIL}}' => Yii::t('label', 'Email'),
					'{{PHONE}}' => Yii::t('label', 'Phone'),
					'{{ACCOUNT_ACTIVATION_CODE}}' => Yii::t('label', 'Account Activation Code'),
					'{{ACCOUNT_ACTIVATION_PAGE_URL}}' => Yii::t('label', 'Account Activation Page URL'),
					'{{ACCOUNT_ACTIVATION_URL}}' => Yii::t('label', 'Account Activation URL'),
				]);
				break;
			case static::EMAIL_VARIANT_PASSWORD_RESET:
				$shortCodeItems = array_merge($shortCodeItems, [
					'{{FIRST_NAME}}' => Yii::t('label', 'First Name'),
					'{{MIDDLE_NAME}}' => Yii::t('label', 'Middle Name'),
					'{{LAST_NAME}}' => Yii::t('label', 'Last Name'),
					'{{EMAIL}}' => Yii::t('label', 'Email'),
					'{{PHONE}}' => Yii::t('label', 'Phone'),
					'{{PASSWORD_RESET_CODE}}' => Yii::t('label', 'Password Reset Code'),
					'{{PASSWORD_RESET_PAGE_URL}}' => Yii::t('label', 'Password Reset Page URL'),
					'{{PASSWORD_RESET_URL}}' => Yii::t('label', 'Password Reset URL'),
				]);
				break;
			case static::EMAIL_VARIANT_WELCOME:
				$shortCodeItems = array_merge($shortCodeItems, [
					'{{FIRST_NAME}}' => Yii::t('label', 'First Name'),
					'{{MIDDLE_NAME}}' => Yii::t('label', 'Middle Name'),
					'{{LAST_NAME}}' => Yii::t('label', 'Last Name'),
					'{{EMAIL}}' => Yii::t('label', 'Email'),
					'{{PHONE}}' => Yii::t('label', 'Phone'),
				]);
				break;
			case static::EMAIL_VARIANT_INVOICE_ISSUANCE:
			case static::EMAIL_VARIANT_INVOICE_PAYMENT_CONFIRMATION:
				$shortCodeItems = array_merge($shortCodeItems, InvoiceTemplateForm::getShortCodeItems());
				break;
			case static::EMAIL_VARIANT_SUBSCRIPTION_CANCELLATION:
				$shortCodeItems = array_merge($shortCodeItems, [
					'{{FIRST_NAME}}' => Yii::t('label', 'First Name'),
					'{{MIDDLE_NAME}}' => Yii::t('label', 'Middle Name'),
					'{{LAST_NAME}}' => Yii::t('label', 'Last Name'),
					'{{CODE}}' => Yii::t('label', 'Code'),
					'{{SUBSCRIPTION}}' => Yii::t('label', 'Subscription'),
					'{{PRICE}}' => Yii::t('label', 'Price'),
					'{{BILLING_CYCLE}}' => Yii::t('label', 'Billing Cycle'),
					'{{PAYMENT_PAGE_URL}}' => Yii::t('label', 'Payment Page URL'),
				]);
				break;
			case static::EMAIL_VARIANT_TWO_FACTOR_AUTHENTICATION:
				$shortCodeItems = array_merge($shortCodeItems, [
					'{{FIRST_NAME}}' => Yii::t('label', 'First Name'),
					'{{MIDDLE_NAME}}' => Yii::t('label', 'Middle Name'),
					'{{LAST_NAME}}' => Yii::t('label', 'Last Name'),
					'{{EMAIL}}' => Yii::t('label', 'Email'),
					'{{PHONE}}' => Yii::t('label', 'Phone'),
					'{{TWO_FACTOR_AUTHENTICATION_CODE}}' => Yii::t('label', 'Two-Factor Authentication Code'),
					'{{TWO_FACTOR_AUTHENTICATION_PAGE_URL}}' => Yii::t('label', 'Two-Factor Authentication Page URL'),
					'{{TWO_FACTOR_AUTHENTICATION_URL}}' => Yii::t('label', 'Two-Factor Authentication URL'),
				]);
				break;
			// Filled by common\services\AffiliateNotificationService.
			case static::EMAIL_VARIANT_AFFILIATE_PAYOUT_PAID:
			case static::EMAIL_VARIANT_AFFILIATE_INVOICE_REJECTED:
				$shortCodeItems = array_merge($shortCodeItems, [
					'{{FIRST_NAME}}' => Yii::t('label', 'First Name'),
					'{{LAST_NAME}}' => Yii::t('label', 'Last Name'),
					'{{AMOUNT}}' => Yii::t('label', 'Amount'),
					'{{CURRENCY}}' => Yii::t('label', 'Currency'),
					'{{REASON}}' => Yii::t('label', 'Rejection Reason'),
					'{{ACCOUNT_URL}}' => Yii::t('label', 'Account URL'),
				]);
				break;
			// Filled by common\services\AffiliateApplicationService.
			case static::EMAIL_VARIANT_AFFILIATE_APPLICATION_APPROVED:
			case static::EMAIL_VARIANT_AFFILIATE_APPLICATION_REJECTED:
				$shortCodeItems = array_merge($shortCodeItems, [
					'{{FIRST_NAME}}' => Yii::t('label', 'First Name'),
					'{{LAST_NAME}}' => Yii::t('label', 'Last Name'),
					'{{COMPANY_NAME}}' => Yii::t('label', 'Company'),
					'{{AFFILIATE_CODE}}' => Yii::t('label', 'Affiliate Code'),
					'{{REASON}}' => Yii::t('label', 'Rejection Reason'),
					'{{ACCOUNT_URL}}' => Yii::t('label', 'Account URL'),
				]);
				break;
			default:
				break;
		}

		return $shortCodeItems;
	}
}
