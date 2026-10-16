<?php

namespace backend\modules\subscriber\models;

use common\models\Feature;
use common\models\PackageFeature;
use common\models\ScheduledTask;
use common\models\Subscription;
use common\models\SubscriptionFeature;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class SubscriptionForm extends Subscription
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->price = 0;
		$this->currency = Yii::$app->settings->get('currencyCode');
		$this->billing_period = 1;
		$this->billing_cycle = ScheduledTask::CYCLE_MONTH;
		$this->type = static::TYPE_FREE;
		$this->status = static::STATUS_INACTIVE;

		// Package features
		$this->{Feature::WORKSPACES} = 1;
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
			[['package_id', 'type', 'billing_period', 'billing_cycle'], 'required'],
			[['billing_period'], 'integer', 'min' => 1],

            // Package features
			[[
				Feature::WORKSPACES,
			], 'required'],
			[[
				Feature::WORKSPACES,
			], 'integer', 'min' => 1],
			[array_keys(Feature::getFeatureLabels()), 'trim'],
			[array_keys(Feature::getFeatureLabels()), 'default', 'value' => null],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), Feature::getFeatureLabels(), [
			'package_id' => Yii::t('label', 'Package'),
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function afterFind()
	{
		parent::afterFind();

		// Expose the FK as a canonical UUID string so the package dropdown (whose option
		// keys are UUID strings) can match the selected value; beforeSave converts back.
		$this->package_id = $this->uuidOf('package_id');

		foreach ($this->subscriptionFeatures as $subscriptionFeature) {
			if ($this->hasAttribute($subscriptionFeature->name)) {
				$this->{$subscriptionFeature->name} = $subscriptionFeature->value;
			}
		}
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return Model::scenarios();
	}

	/**
	 * Saves the SubscriptionFeature models.
	 *
	 * @return bool
	 */
	protected function saveSubscriptionFeatures()
	{
		try {
			if ($this->type == static::TYPE_STANDARD) {
				$packageFeatures = $this->package->getPackageFeatures()->all();
				$subscriptionFeatures = [];

				foreach ($packageFeatures as $packageFeature) {
					$subscriptionFeatures[] = [
						// batchInsert() bypasses the models — the UUID v7 id must be generated here
						'id' => \common\helpers\UuidHelper::uuid7Bytes(),
						'subscription_id' => $this->id,
						'feature_id' => $packageFeature->feature_id,
						'name' => $packageFeature->name,
						'value' => (string) $packageFeature->value,
						'renewable' => $packageFeature->renewable,
					];
				}

				SubscriptionFeature::getDb()
					->createCommand()
					->batchInsert(SubscriptionFeature::tableName(), array_keys($subscriptionFeatures[0]), $subscriptionFeatures)
					->execute();
			} else {
				$availableSubscriptionFeatures = Feature::getFeatureLabels();
				$existingSubscriptionFeatures = $this->getSubscriptionFeatures()->indexBy('name')->all();
				$newSubscriptionFeatures = array_diff(array_keys($availableSubscriptionFeatures), array_keys($existingSubscriptionFeatures));
				$subscriptionFeatures = [];

				/** @var SubscriptionFeature[] $existingSubscriptionFeatures */
				foreach ($existingSubscriptionFeatures as $existingSubscriptionFeature) {
					if ($this->hasAttribute($existingSubscriptionFeature->name)) {
						$existingSubscriptionFeature->value = $this->{$existingSubscriptionFeature->name};
						$subscriptionFeatures[] = $existingSubscriptionFeature;
					}
				}

				if (!empty($newSubscriptionFeatures)) {
					$features = Feature::findAllFeatures();

					foreach ($newSubscriptionFeatures as $newSubscriptionFeature) {
						$subscriptionFeature = new SubscriptionFeature();
						$subscriptionFeature->subscription_id = $this->id;
						$subscriptionFeature->feature_id = $features[$newSubscriptionFeature]->id;
						$subscriptionFeature->name = $newSubscriptionFeature;
						$subscriptionFeature->value = (string) $this->$newSubscriptionFeature;
						$subscriptionFeature->renewable = $features[$newSubscriptionFeature]->renewable ?: 0;
						$subscriptionFeatures[] = $subscriptionFeature;
					}
				}

				foreach ($subscriptionFeatures as $subscriptionFeature) {
					if (!$subscriptionFeature->save()) {
						$this->addErrors($subscriptionFeature->getErrors());
						throw new \Exception('Cannot save the SubscriptionFeature models.');
					}
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
	 * @return bool
	 */
	public function saveModel()
	{
		$dbTransaction = static::getDb()->beginTransaction();
		try {
            if (!$this->code) {
                $this->code = static::generateUniqueCode();
            }
			if (!empty($this->start_at)) {
				$this->end_at = $this->getEndAtDate()->format('Y-m-d H:i:s');
			}
			$this->type = $this->package->type;
			// Update standard package price based on the billing period and cycle
			if ($this->type == static::TYPE_STANDARD) {
				$this->trial_period = $this->package->trial_period;
				$this->trial_cycle = $this->package->trial_cycle;
				$this->currency = $this->package->currency;
				$this->price = $this->package->price * $this->billing_period;
				if ($this->billing_cycle == ScheduledTask::CYCLE_YEAR) {
					$this->price *= 12;
				}
			}
			if (!$this->save(true, parent::attributes())) {
				throw new \Exception();
			}
			if (!$this->saveSubscriptionFeatures()) {
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
