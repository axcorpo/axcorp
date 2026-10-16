<?php

namespace common\models;

use common\helpers\CaptchaHelper;
use Yii;
use yii\base\Model;

/**
 * Subscribe form
 */
class SubscribeForm extends Model
{
	/**
	 * @var int The Package model ID.
	 */
	public $package_id;

	/**
	 * @var string The custom Package model requirements.
	 */
	public $custom_package_requirements;

	/**
	 * @var bool Accept TOS flag.
	 */
	public $acceptTerms;

	/**
	 * @var string The honeypot field.
	 */
	public $workEmail;

    /**
     * @var string The honeypot field.
     */
    public $captchaResponse;

	/**
	 * @var User The User model.
	 */
	private $_user;

	/**
	 * @var Subscriber The Subscriber model.
	 */
	private $_subscriber;

	/**
	 * @var Package The Package model.
	 */
	private $_package;


	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['package_id', 'acceptTerms'], 'required'],
			['custom_package_requirements', 'required', 'when' => function () {
				// No package yet means package_id is unknown or inactive, which the
				// `exist` rule below reports; there is nothing custom to require.
				$package = $this->getPackage();
				return $package !== null && $package->type == Package::TYPE_CUSTOM;
			}, 'whenClient' => 'function (attribute, value) {
				return attribute.$form.find("[name*=\"[package_id]\"]:checked").attr("data-custom") == "true";
			}'],
			['acceptTerms', 'boolean'],
			['workEmail', 'safe'],
            ['captchaResponse', 'safe'],
            [['package_id'], 'exist', 'skipOnError' => true, 'targetClass' => Package::class, 'targetAttribute' => ['package_id' => 'id'], 'filter' => ['status' => Package::STATUS_ACTIVE, 'deleted' => Package::NO]],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'package_id' => Yii::t('common', 'Package'),
			'custom_package_requirements' => Yii::t('common', 'What are your requirements?'),
			'acceptTerms' => Yii::t('common', 'Terms and Conditions'),
		];
	}

	/**
	 * Gets the User model.
	 *
	 * @return User
	 */
	public function getUser()
	{
		if (!$this->_user) {
			$this->_user = Yii::$app->user->identity;
		}
		return $this->_user;
	}

	/**
	 * Gets the Subscriber model.
	 *
	 * @return Subscriber
	 */
	public function getSubscriber()
	{
		return $this->_subscriber;
	}

	/**
	 * Gets the Package model.
	 *
	 * @return Package
	 */
	public function getPackage()
	{
		if (!$this->_package) {
			$this->_package = Package::findOne([
				'id' => $this->package_id,
				'status' => Package::STATUS_ACTIVE,
				'deleted' => Package::NO,
			]);
		}
		return $this->_package;
	}

	/**
	 * Saves the Subscriber model.
	 *
	 * @return bool
	 */
	protected function saveSubscriber()
	{
		try {
			$user = $this->getUser();

			$subscriber = new Subscriber();
			$subscriber->user_id = $user->id;
			$subscriber->code = Subscriber::generateUniqueCode();
			$subscriber->setSerializedValue('data', [
				'package_id' => $this->package_id,
			]);
			$subscriber->status = Subscriber::STATUS_ACTIVE;
			// Subscribing without a subscriber row yet is a signup too: attribute it (?aff=).
			\common\components\AffiliateTracker::assign($subscriber);
			if (!$subscriber->save()) {
				throw new \Exception($subscriber->getErrorSummary(false)[0]);
			}

			$this->_subscriber = $subscriber;

			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * Saves the Subscription model.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function saveSubscription()
	{
		try {
			$subscriber = $this->getSubscriber();
			$package = $this->getPackage();

			$subscription = new Subscription();
			$subscription->subscriber_id = $subscriber->id;
			$subscription->package_id = $package->id;
			$subscription->code = Subscription::generateUniqueCode();
			$subscription->trial_period = $package->trial_period;
			$subscription->trial_cycle = $package->trial_cycle;
			$subscription->billing_period = $package->billing_period;
			$subscription->billing_cycle = $package->billing_cycle;
			$subscription->price = $package->price;
			$subscription->currency = $package->currency;
			$subscription->type = $package->type;
			$subscription->start_at = (new \DateTime)->format('Y-m-d H:i:s');
			// A free package without a cycle is lifetime; with one it expires like a paid
			// package (but is never renewed).
			if ($subscription->type == Subscription::TYPE_FREE && !$subscription->getHasBillingCycle()) {
				$subscription->end_at = null;
			} elseif ($subscription->type == Subscription::TYPE_FREE) {
				$subscription->end_at = $subscription->getEndAtDate()->format('Y-m-d H:i:s');
			} else {
				$subscription->end_at = $subscription->getTrialEndAtDate()->format('Y-m-d H:i:s');
			}
			$subscription->status = Subscription::STATUS_ACTIVE;
			if (!$subscription->save()) {
				throw new \Exception($subscription->getErrorSummary(false)[0]);
			}

			// batchInsert() bypasses the models — the BINARY(16) UUID v7 ids must be generated here.
			$columns = ['id', 'subscription_id', 'feature_id', 'name', 'value', 'price', 'renewable', 'type'];
			$rows = [];
			foreach ($package->packageFeatures as $packageFeature) {
				$rows[] = [
					'id' => \common\helpers\UuidHelper::uuid7Bytes(),
					'subscription_id' => $subscription->id,
					'feature_id' => $packageFeature->feature_id,
					'name' => $packageFeature->name,
					'value' => $packageFeature->value,
					'price' => $packageFeature->price,
					'renewable' => $packageFeature->renewable,
					'type' => SubscriptionFeature::TYPE_PACKAGE_FEATURE,
				];
			}
			if ($rows && !Yii::$app->db->createCommand()->batchInsert(SubscriptionFeature::tableName(), $columns, $rows)->execute()) {
				throw new \Exception('SubscriptionFeature cannot be saved.');
			}

			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * Sends email for custom package request.
	 *
	 * @return bool
	 */
	public function sendCustomPackageRequestEmail()
	{
		$user = $this->getUser();

		return Yii::$app->mailer->compose([
				'html' => 'custom-package-request-html',
				'text' => 'custom-package-request-text',
			], [
				'user' => $user,
				'message' => $this->custom_package_requirements,
			])
			->setTo(Yii::$app->settings->get('email', 'contact'))
			->setReplyTo([$user->email => $user->fullName])
			->setSubject('[' . Yii::$app->name . ']: ' . Yii::t('common', 'Enterprise package request from {0}', [$user->fullName]))
			->send();
	}

	/**
	 * Subscribes user to the system.
	 *
	 * @return bool
	 */
	public function subscribe()
	{
		if (!empty($this->workEmail)) {
			return false;
		}
        if (!CaptchaHelper::verify($this->captchaResponse)) {
            $this->addError('captchaResponse', Yii::t('common', 'The captcha verification failed. Please try again.'));
            return false;
        }
		if (!$this->validate()) {
			return false;
		}

		$dbTransaction = Yii::$app->db->beginTransaction();
		try {
			if (!($user = $this->getUser())) {
				throw new \Exception();
			}
			if (!$this->saveSubscriber()) {
				throw new \Exception();
			}
			if (!$this->saveSubscription()) {
				throw new \Exception();
			}
			if ($this->getPackage()->type == Package::TYPE_CUSTOM) {
				if (!$this->sendCustomPackageRequestEmail()) {
					throw new \Exception();
				}
			}

			$dbTransaction->commit();
			return true;
		} catch(\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}
}
