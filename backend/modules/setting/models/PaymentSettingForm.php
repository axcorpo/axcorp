<?php

namespace backend\modules\setting\models;

use common\models\PaymentMetadata;
use common\models\ScheduledTask;
use common\models\Setting;
use common\traits\SecretSettingTrait;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class PaymentSettingForm extends Setting
{
	use SecretSettingTrait;

	/**
	 * {@inheritdoc}
	 */
	public function secretAttributes()
	{
		return [
			'stripePrivateKey',
		];
	}

	/**
	 * @var bool Flag that indicates if recurring payment is active.
	 */
	public $enableRecurringPayment;

	/**
	 * @var bool Flag that indicates if notifications should be sent for invoices.
	 */
	public $enableInvoiceEmailNotification;

	/**
	 * @var bool Flag that indicates if the invoice document should be attached to email.
	 */
	public $attachInvoiceToEmail;

	/**
	 * @var int The number of days for the account availability in case of overdue payments.
	 */
	public $invoiceIssuanceBeforePeriod;

	/**
	 * @var int The number of days for the account availability in case of overdue payments.
	 */
	public $allowedPaymentOverduePeriod;

	/**
	 * @var string The payment methods list.
	 */
	public $paymentMethods = [];

	/**
	 * @var string The payment processors list.
	 */
	public $paymentProcessors = [];

	/**
	 * @var string The Stripe API base URL.
	 */
	public $stripeBaseUrl;

	/**
	 * @var int The Stripe API private key.
	 */
	public $stripePrivateKey;

	/**
	 * @var string The Stripe API public key.
	 */
	public $stripePublicKey;

	/**
	 * @var string The Stripe Webhook IPN  key.
	 */
	public $stripeWebhookIPNKey;

	/**
	 * @var string The bank name.
	 */
	public $bankName;

	/**
	 * @var string The bank account.
	 */
	public $bankAccount;

	/**
	 * @var string The bank BIC code.
	 */
	public $bankBic;

	/**
	 * @var string The bank SWIFT code.
	 */
	public $bankSwift;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->invoiceIssuanceBeforePeriod = 3;
		$this->allowedPaymentOverduePeriod = 3;
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['enableRecurringPayment', 'enableInvoiceEmailNotification', 'attachInvoiceToEmail'], 'boolean'],
			[['invoiceIssuanceBeforePeriod', 'allowedPaymentOverduePeriod'], 'integer', 'min' => 0],

			[['paymentMethods'], 'required'],
			[['paymentProcessors'], 'required', 'when' => function () {
				return in_array(PaymentMetadata::PAYMENT_METHOD_CARD, (array) $this->paymentMethods);
			}, 'whenClient' => 'function (attribute, value) {
				return attribute.$form.find(\'[name*="[paymentMethods][' . PaymentMetadata::PAYMENT_METHOD_CARD . ']"]\').prop("checked");
			}'],

			[['stripeBaseUrl', 'stripePrivateKey', 'stripePublicKey'], 'required', 'when' => function () {
				return in_array(PaymentMetadata::PAYMENT_METHOD_CARD, (array) $this->paymentMethods);
			}, 'whenClient' => 'function (attribute, value) {
				return attribute.$form.find(\'[name*="[paymentMethods][' . PaymentMetadata::PAYMENT_METHOD_CARD . ']"]\').prop("checked");
			}'],
			[['stripeBaseUrl'], 'url'],
			[['stripeBaseUrl', 'stripePrivateKey', 'stripePublicKey', 'stripeWebhookIPNKey'], 'string'],
			[['stripeBaseUrl', 'stripePrivateKey', 'stripePublicKey', 'stripeWebhookIPNKey'], 'trim'],

			[['bankName', 'bankAccount', 'bankBic', 'bankSwift'], 'required', 'when' => function () {
				return in_array(PaymentMetadata::PAYMENT_METHOD_BANK, (array) $this->paymentMethods);
			}, 'whenClient' => 'function (attribute, value) {
				return attribute.$form.find(\'[name*="[paymentMethods][' . PaymentMetadata::PAYMENT_METHOD_BANK . ']"]\').prop("checked");
			}'],
			[['bankName', 'bankAccount', 'bankBic', 'bankSwift'], 'string'],
			[['bankName', 'bankAccount', 'bankBic', 'bankSwift'], 'trim'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'enableRecurringPayment' => Yii::t('label', 'Enable Recurring Payment'),
			'enableInvoiceEmailNotification' => Yii::t('label', 'Enable Invoice Email Notification'),
			'attachInvoiceToEmail' => Yii::t('label', 'Attach Invoice To Email'),
			'invoiceIssuanceBeforePeriod' => Yii::t('label', 'Invoice Issuance Before Period'),
			'allowedPaymentOverduePeriod' => Yii::t('label', 'Allowed Payment Overdue Period'),
			'stripeBaseUrl' => Yii::t('label', 'Base URL'),
			'stripePrivateKey' => Yii::t('label', 'Private Key'),
			'stripePublicKey' => Yii::t('label', 'Public Key'),
			'stripeWebhookIPNKey' => Yii::t('label', 'Webhook Key'),
			'bankName' => Yii::t('label', 'Bank Name'),
			'bankAccount' => Yii::t('label', 'Bank Account'),
			'bankBic' => Yii::t('label', 'Bank BIC'),
			'bankSwift' => Yii::t('label', 'Bank SWIFT'),
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

		$this->setAttributes($this->getUnserializedValue('setting'));

		// Captured before the request can overwrite them; see the trait.
		$this->rememberSecrets();
	}

	/**
	 * Saves the ScheduledTask model for recurring payment.
	 *
	 * @return bool
	 */
	protected function saveRecurringPaymentScheduledTask()
	{
		$scheduledTask = ScheduledTask::findOne([
			'resource' => __CLASS__ . '::' . __FUNCTION__,
			'resource_key' => $this->uuid,
			'deleted' => ScheduledTask::NO,
		]);
		if (!$scheduledTask) {
			$scheduledTask = new ScheduledTask();
		}
		$scheduledTask->cron_expression = '* * * * * *';
		$scheduledTask->app_command = 'payment/run';
		$scheduledTask->resource = __CLASS__ . '::' . __FUNCTION__;
		$scheduledTask->resource_key = $this->uuid;
		$scheduledTask->application = str_replace('app-', '', Yii::$app->id);
		$scheduledTask->type = ScheduledTask::TYPE_APP;
		$scheduledTask->status = ScheduledTask::STATUS_ACTIVE;

		return $scheduledTask->save();
	}

	/**
	 * Saves the ScheduledTask model for renewing subscription features quota.
	 *
	 * @return bool
	 */
	protected function saveSubscriptionRenewFeaturesQuotaScheduledTask()
	{
		$scheduledTask = ScheduledTask::findOne([
			'resource' => __CLASS__ . '::' . __FUNCTION__,
			'resource_key' => $this->uuid,
			'deleted' => ScheduledTask::NO,
		]);
		if (!$scheduledTask) {
			$scheduledTask = new ScheduledTask();
		}
		$scheduledTask->cron_expression = '* * * * * *';
		$scheduledTask->app_command = 'subscription/renew-features-quota';
		$scheduledTask->resource = __CLASS__ . '::' . __FUNCTION__;
		$scheduledTask->resource_key = $this->uuid;
		$scheduledTask->application = str_replace('app-', '', Yii::$app->id);
		$scheduledTask->type = ScheduledTask::TYPE_APP;
		$scheduledTask->status = ScheduledTask::STATUS_ACTIVE;

		return $scheduledTask->save();
	}

	/**
	 * Saves the setting.
	 *
	 * @return bool|\yii\db\ActiveRecord
	 */
	public function saveModel()
	{
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			$this->name = 'payment';
			$this->type = static::TYPE_APP;
			$this->status = static::STATUS_ACTIVE;
			$this->setSerializedValue('setting', [
				'enableRecurringPayment' => $this->enableRecurringPayment,
				'enableInvoiceEmailNotification' => $this->enableInvoiceEmailNotification,
				'attachInvoiceToEmail' => $this->attachInvoiceToEmail,
				'invoiceIssuanceBeforePeriod' => $this->invoiceIssuanceBeforePeriod,
				'allowedPaymentOverduePeriod' => $this->allowedPaymentOverduePeriod,
				'paymentMethods' => $this->paymentMethods,
				'paymentProcessors' => $this->paymentProcessors,
				'stripeBaseUrl' => $this->stripeBaseUrl,
				'stripePrivateKey' => $this->stripePrivateKey,
				'stripePublicKey' => $this->stripePublicKey,
				'stripeWebhookIPNKey' => $this->stripeWebhookIPNKey,
				'bankName' => $this->bankName,
				'bankAccount' => $this->bankAccount,
				'bankBic' => $this->bankBic,
				'bankSwift' => $this->bankSwift,
			]);

			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->saveRecurringPaymentScheduledTask()) {
				throw new \Exception();
			}
			if (!$this->saveSubscriptionRenewFeaturesQuotaScheduledTask()) {
				throw new \Exception();
			}
			$dbTransaction->commit();
			return $this;
		} catch (\Exception $e) {
			$dbTransaction->rollBack();
			return null;
		}
	}
}
