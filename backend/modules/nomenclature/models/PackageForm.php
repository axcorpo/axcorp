<?php

namespace backend\modules\nomenclature\models;

use common\components\ApplicationBootstrap;
use common\helpers\StringHelper;
use common\models\Feature;
use common\models\Language;
use common\models\Package;
use common\models\PackageFeature;
use common\models\PackageTranslation;
use common\models\PaymentMetadata;
use common\models\ScheduledTask;
use Yii;
use yii\base\InvalidCallException;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class PackageForm extends Package
{
	/**
	 * @var array The multilingual name of the Package.
	 */
	public $name = [];

	/**
	 * @var array The multilingual content of the Package.
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

		$this->trial_period = 14;
		$this->trial_cycle = ScheduledTask::CYCLE_DAY;
		$this->billing_period = 1;
		$this->billing_cycle = ScheduledTask::CYCLE_MONTH;
		$this->currency = Yii::$app->settings->get('currencyCode');
		$this->type = static::TYPE_STANDARD;
		$this->status = static::STATUS_ACTIVE;

		// Package features
		$this->{Feature::WORKSPACES} = 1;
		$this->{Feature::USERS} = 1;
	}

	/**
	 * @inheritdoc
	 */
	public function attributes()
	{
		$attributes = parent::attributes();

		foreach (array_keys(Feature::getFeatureLabels()) as $packageFeatureName) {
			$attributes[] = $packageFeatureName;
		}

		return $attributes;
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return ArrayHelper::merge(parent::rules(), [
			[['type'], 'required'],
			[['trial_period', 'trial_cycle', 'billing_period', 'billing_cycle', 'price', 'type'], 'required', 'when' => function () {
				return $this->type != static::TYPE_FREE;
			}, 'whenClient' => 'function (attribute, value) {
        return attribute.$form.find("[name*=\"[type]").val() != "' . static::TYPE_FREE . '";
    	}'],
			[['price'], 'number', 'min' => 1, 'when' => function () {
				return $this->type != static::TYPE_FREE;
			}, 'whenClient' => 'function (attribute, value) {
        return attribute.$form.find("[name*=\"[type]").val() != "' . static::TYPE_FREE . '";
    	}'],
			[['name'], 'required', 'when' => function () {
				return empty($this->name[Yii::$app->language]);
			}, 'whenClient' => 'function (attribute, value) {
        return attribute.$form.find("[name*=\"[name][' . Yii::$app->language . ']\"]").val() == "";
    	}'],
			[['name', 'content'], 'each', 'rule' => ['trim']],
			[['content'], 'each', 'rule' => ['default', 'value' => null]],

			// Package features
			[[
				Feature::WORKSPACES,
				Feature::USERS,
			], 'required'],
			[[
				Feature::WORKSPACES,
				Feature::USERS,
			], 'integer', 'min' => 1],
			[array_keys(Feature::getFeatureLabels()), 'trim'],
			[array_keys(Feature::getFeatureLabels()), 'default', 'value' => null],
            [['translator'], 'safe'],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), Feature::getFeatureLabels(), [
			'billing_period' => Yii::t('label', 'Billing Cycle'),
			'name' => Yii::t('label', 'Name'),
			'content' => Yii::t('label', 'Description'),
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

		$this->name = ArrayHelper::map($this->packageTranslations, 'language_id', 'name');
		$this->content = ArrayHelper::map($this->packageTranslations, 'language_id', 'content');

		foreach ($this->packageFeatures as $packageFeature) {
			if ($this->hasAttribute($packageFeature->name)) {
				$this->{$packageFeature->name} = $packageFeature->value;
			}
		}
	}

    /**
     * Saves the translations.
     *
     * @return bool
     */
    protected function savePackageTranslations()
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
                $packageTranslation = PackageTranslation::findOne([
                    'package_id' => $this->id,
                    'language_id' => $language->language_id,
                ]);
                if (!$packageTranslation) {
                    $packageTranslation = new PackageTranslation();
                    $packageTranslation->package_id = $this->id;
                    $packageTranslation->language_id = $language->language_id;
                }

                if (!empty($this->translator) && $language->language_id != Yii::$app->language && in_array($defaultLanguage->language, $languages) && in_array($language->language, $languages)) {
                    $source = $defaultLanguage->language;
                    $target = $language->language;
                }

                $packageTranslation->name = StringHelper::truncate($this->name[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->name[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, $this->name[Yii::$app->language])['data']['translations'][0]['translatedText'] : null), 255, '');
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
                $packageTranslation->content = $this->content[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->content[$language->language_id] : (!empty($translations) ? implode('.', $translations) : null);
                if ($packageTranslation->name) {
                    $this->link('packageTranslations', $packageTranslation);
                }
            }
            return true;
        } catch (\Exception $e) {
            $this->addError('', $e->getMessage());
            return false;
        }
    }

	/**
	 * Saves the Package features.
	 *
	 * @return bool
	 */
	protected function savePackageFeatures()
	{
		try {
			foreach (Feature::findAllFeatures() as $feature) {
				if ($this->hasAttribute($feature->name)) {
					$packageFeature = PackageFeature::findOne([
						'package_id' => $this->id,
						'name' => $feature->name,
						'deleted' => PackageFeature::NO,
					]);
					if (!$packageFeature) {
						$packageFeature = new PackageFeature();
						$packageFeature->package_id = $this->id;
					}
					$packageFeature->feature_id = $feature->id;
					$packageFeature->name = $feature->name;
					$packageFeature->value = (string) $this->{$feature->name};
					$packageFeature->renewable = $feature->renewable;
					if (!$packageFeature->save()) {
						throw new \Exception('Cannot save the PackageFeature models.');
					}
				}
			}
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * The Stripe product attributes mirroring this package.
	 *
	 * @return array
	 */
	public function buildStripeProductData()
	{
		return [
			'name' => ($this->name[Yii::$app->language] ?? null) ?: ($this->translation->name ?? ''),
			'active' => (bool) $this->status,
			'metadata' => [
				// Canonical UUID string — raw BINARY(16) bytes are not valid in Stripe metadata.
				'package_id' => $this->uuid,
			],
		];
	}

	/**
	 * The Stripe recurring price attributes mirroring this package.
	 *
	 * @return array
	 */
	public function buildStripePriceData()
	{
		return [
			// round() — 19.99 × 100 must not travel as 1998.9999999999998.
			'unit_amount_decimal' => round($this->price * 100, 2),
			'currency' => $this->currency,
			'recurring' => [
				'interval' => $this->billing_cycle,
				'interval_count' => (int) $this->billing_period,
			],
		];
	}

	/**
	 * Whether the product's current default price already matches the package.
	 *
	 * @param \Stripe\Price|array|null $price
	 * @param array $priceData as returned by {@see buildStripePriceData()}
	 * @return bool
	 */
	public static function stripePriceMatches($price, array $priceData)
	{
		return $price !== null
			&& round((float) $price['unit_amount_decimal'], 2) === round((float) $priceData['unit_amount_decimal'], 2)
			&& strcasecmp((string) $price['currency'], (string) $priceData['currency']) === 0
			&& ($price['recurring']['interval'] ?? null) === $priceData['recurring']['interval']
			&& (int) ($price['recurring']['interval_count'] ?? 0) === $priceData['recurring']['interval_count'];
	}

	/**
	 * Creates or updates the Stripe product mirroring this package, keeping the name and
	 * the default recurring price in sync.
	 *
	 * Stripe prices are immutable: a price/currency/cycle change creates a new price,
	 * promotes it to default_price and archives the previous one. Stripe subscriptions
	 * already running keep the price they were started with.
	 *
	 * @param bool $isNewRecord
	 * @return \Stripe\Product|null null when the Stripe call fails — logged, callers treat it as non-fatal
	 */
	public function saveStripeProduct($isNewRecord = true)
	{
		try {
			$payment = Yii::$app->payment->via(PaymentMetadata::PAYMENT_PROCESSOR_STRIPE);
			$stripe = new \Stripe\StripeClient(
				$payment->privateKey
			);
			$productData = $this->buildStripeProductData();
			$priceData = $this->buildStripePriceData();

			if (!$isNewRecord && $this->external_id) {
				$product = $stripe->products->retrieve($this->external_id, [
					'expand' => ['default_price'],
				]);
				$defaultPrice = $product['default_price'];
				$product = $stripe->products->update($product['id'], $productData);

				if (!static::stripePriceMatches($defaultPrice, $priceData)) {
					$price = $stripe->prices->create(['product' => $product['id']] + $priceData);
					$product = $stripe->products->update($product['id'], [
						'default_price' => $price['id'],
					]);
					if ($defaultPrice !== null) {
						$stripe->prices->update($defaultPrice['id'], ['active' => false]);
					}
				}
				return $product;
			}

			// New record, or an existing package that never reached Stripe (backfill).
			return $stripe->products->create($productData + [
				'default_price_data' => $priceData,
			]);
		} catch (\Exception $e) {
			Yii::error([
				'message' => 'Stripe product sync failed.',
				'package_id' => $this->uuid,
				'error' => $e->getMessage(),
			], 'stripe');
			return null;
		}
	}

	/**
	 * Saves the model.
	 *
	 * @return bool|static
	 */
	public function saveModel()
	{
		$isNewRecord = $this->getIsNewRecord();
		$paymentSettings = Yii::$app->settings->getCategory('payment');
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			if ($this->type == static::TYPE_FREE) {
				$this->trial_period = null;
				$this->trial_cycle = null;
				// The billing cycle is kept: it is the term of the free subscription (which
				// then expires without renewal). Without a period the package is lifetime.
				if (empty($this->billing_period)) {
					$this->billing_period = null;
					$this->billing_cycle = null;
				}
				$this->price = 0;
				$this->currency = null;
			}
            if (!$this->save(true, parent::attributes())) {
                throw new \Exception();
            }
			if (!$this->savePackageTranslations()) {
				throw new \Exception();
			}
			if (!$this->savePackageFeatures()) {
				throw new \Exception();
			}
			// Free packages are never sold through Stripe (price 0, no currency) — skip the
			// sync entirely. A Stripe failure is non-fatal: the local save stands and the
			// product is created on the next save through the missing-external_id branch.
			if ($this->type != static::TYPE_FREE
				&& array_key_exists(PaymentMetadata::PAYMENT_METHOD_CARD, (array) ($paymentSettings['paymentMethods'] ?? []))) {
				$activePaymentProcessors = (array) ($paymentSettings['paymentProcessors'][PaymentMetadata::PAYMENT_METHOD_CARD] ?? []);
				if (array_key_exists(PaymentMetadata::PAYMENT_PROCESSOR_STRIPE, $activePaymentProcessors)) {
					$product = $this->saveStripeProduct($isNewRecord);
					if ($product !== null && $this->external_id !== $product['id']) {
						$this->external_id = $product['id'];
						if (!$this->save(false, ['external_id'])) {
							throw new \Exception();
						}
					}
				}
			}
			$dbTransaction->commit();
			return $this;
		} catch(\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}
}
