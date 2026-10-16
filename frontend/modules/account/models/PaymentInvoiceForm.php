<?php

namespace frontend\modules\account\models;

use common\models\Company;
use common\models\Invoice;
use common\models\Item;
use common\models\PaymentMetadata;
use common\models\Subscription;
use common\models\User;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class PaymentInvoiceForm extends Model
{
	/**
	 * @var int|string The Subscription model ID.
	 */
	public $invoice_id;

	/**
	 * @var int|string The Company model ID.
	 */
	public $company_id;

	/**
	 * @var int The payment method.
	 */
	public $payment_method;

	/**
	 * @var string The payment method.
	 */
	public $payment_processor;

	/**
	 * @var bool Flag that indicates if the payer agrees with the automatic payment.
	 */
	public $enable_recurring_payment;

	/**
	 * @var User The User model.
	 */
	private $_user;

	/**
	 * @var Subscription The Subscription model.
	 */
	private $_subscription;

	/**
	 * @var Invoice The Invoice model.
	 */
	private $_invoice;


	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->payment_method = PaymentMetadata::PAYMENT_METHOD_CARD;
		$this->payment_processor = PaymentMetadata::PAYMENT_PROCESSOR_STRIPE;
		$this->enable_recurring_payment = Yii::$app->settings->get('enableRecurringPayment', 'payment');
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['invoice_id', 'payment_method', 'payment_processor'], 'required'],
			[['payment_method', 'payment_processor'], 'integer'],
			[['enable_recurring_payment'], 'boolean'],
			[['invoice_id'], 'exist', 'skipOnError' => true, 'targetClass' => Invoice::class, 'targetAttribute' => ['invoice_id' => 'id']],
			// Only one of the signed-in account's own companies may be billed.
			[['company_id'], 'exist', 'skipOnError' => true, 'targetClass' => Company::class, 'targetAttribute' => ['company_id' => 'id'], 'filter' => ['user_id' => Yii::$app->user->id]],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'invoice_id' => Yii::t('label', 'Invoice'),
			'company_id' => Yii::t('label', 'Company'),
			'payment_method' => Yii::t('label', 'Payment Method'),
			'payment_processor' => Yii::t('label', 'Payment Processor'),
			'enable_recurring_payment' => Yii::t('label', 'Enable Recurring Payment'),
		]);
	}

	/**
	 * Gets the User model.
	 *
	 * @return User|null
	 */
	public function getUser()
	{
		if (!$this->_user) {
			$this->_user = Yii::$app->user->identity;
		}
		return $this->_user;
	}

	/**
	 * Gets the Invoice model.
	 *
	 * @return Invoice|null
	 */
	public function getInvoice()
	{
		if (!$this->_invoice) {
			$this->_invoice = Invoice::findOne([
				'id' => $this->invoice_id,
				'subscriber_id' => $this->getUser()->subscriber->id,
				'status' => Invoice::STATUS_UNPAID,
				'deleted' => Invoice::NO,
			]);
		}
		return $this->_invoice;
	}

	/**
	 * Gets the Subscription model.
	 *
	 * @return Subscription|null
	 */
	public function getSubscription()
	{
		if (!$this->_subscription) {
			$this->_subscription = Item::findOne([
				'invoice_id' => $this->getInvoice()->id,
				'type' => Item::TYPE_SUBSCRIPTION,
				'deleted' => Item::NO,
			])->subscription;
		}
		return $this->_subscription;
	}

	/**
	 * Saves the Subscription model.
	 *
	 * @return bool
	 */
	protected function saveSubscription()
	{
		try {
			$subscription = $this->getSubscription();
			$subscription->company_id = $this->company_id;
			if (!$subscription->save()) {
				throw new \Exception('Subscription cannot be saved.');
			}

			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * Saves the PaymentMetadata model.
	 *
	 * @return bool
	 */
	protected function savePaymentMetadata()
	{
		try {
			$subscription = $this->getSubscription();

			if (!($paymentMetadata = $subscription->paymentMetadata)) {
				$paymentMetadata = new PaymentMetadata();
				$paymentMetadata->subscription_id = $subscription->id;
			}
			$paymentMetadata->payment_method = $this->payment_method;
			$paymentMetadata->payment_processor = $this->payment_processor;
			$paymentMetadata->recurring_payment = $this->enable_recurring_payment;
			$paymentMetadata->ip_address = Yii::$app->request->userIP;
			$paymentMetadata->status = PaymentMetadata::STATUS_ACTIVE;
			if (!$paymentMetadata->save()) {
				throw new \Exception($paymentMetadata->getErrorSummary(false)[0]);
			}
			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * Saves the models.
	 *
	 * @return bool
	 */
	public function save()
	{
		$dbTransaction = Yii::$app->db->beginTransaction();
		try {
			if (!$this->validate()) {
				throw new \Exception();
			}
			if (!$this->saveSubscription()) {
				throw new \Exception();
			}
			if (!$this->savePaymentMetadata()) {
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
