<?php

namespace frontend\modules\account\models;

use common\models\PaymentMetadata;
use common\models\ScheduledTask;
use common\models\Subscription;
use common\models\User;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\helpers\Inflector;

class PaymentRequest extends Model
{
	/**
	 * @var int The Subscription model ID.
	 */
	public $subscription_id;

	/**
	 * @var string|int The payment processor to be used.
	 */
	public $payment_processor;

	/**
	 * @var string The email address to be used for payment.
	 */
	public $email;

	/**
	 * @var string The payment processor authorization token.
	 */
	public $token;

	/**
	 * @var int The payment type.
	 */
	public $type;

	/**
	 * @var User The User model.
	 */
	private $_user;

	/**
	 * @var Subscription The Subscription model.
	 */
	private $_subscription;


	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['subscription_id', 'payment_processor'], 'required'],
			['subscription_id', function ($attribute) {
				if (!$this->getSubscription()) {
					$this->addError($attribute, Yii::t('yii', '{attribute} is invalid.', ['attribute' => $this->getAttributeLabel($attribute)]));
				}
			}],
			[['payment_processor'], 'in', 'range' => array_keys(PaymentMetadata::getPaymentProcessorLabels())],
			[['email', 'token'], 'string'],
			[['type'], 'integer'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'subscription_id' => Yii::t('label', 'Subscription'),
			'payment_processor' => Yii::t('label', 'Payment Processor'),
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
	 * Gets the Subscription model.
	 *
	 * @return Subscription|null
	 */
	public function getSubscription()
	{
		if (!$this->_subscription) {
			$this->_subscription = Subscription::find()
				->andWhere([
					'id' => $this->subscription_id,
					'subscriber_id' => $this->getUser()->subscriber->id,
					'deleted' => Subscription::NO,
				])
				->andWhere([
					'OR',
					['IN', 'status', [Subscription::STATUS_INACTIVE, Subscription::STATUS_SUSPENDED, Subscription::STATUS_INCOMPLETE]],
					[
						'AND',
						['IS NOT', 'trial_period', null],
						['IS NOT', 'trial_cycle', null],
					],
				])
				->one();
		}
		return $this->_subscription;
	}

	/**
	 * Builds Stripe Checkout Session.
	 *
	 * @return mixed
	 */
	public function buildStripeCheckout()
	{
		try {
			$data = [];
			$payment = Yii::$app->payment->via($this->payment_processor);
			$subscription = $this->getSubscription();
			$subscriber = $subscription->subscriber;
			$user = $subscriber->user;

			$stripe = new \Stripe\StripeClient(
				$payment->privateKey
			);

			$data = [
				'metadata' => [
					// Stripe metadata is JSON — send the canonical UUID string, never raw
					// BINARY(16) bytes; the return path (findOne(['id' => ...])) converts back.
					'subscription_id' => $subscription->uuid,
					'ip' => Yii::$app->request->userIP,
				],
				'locale' => mb_substr(Yii::$app->language, 0, 2),
				'success_url' => Url::to(['/account/payment/index'], true) . '/' . Inflector::slug(Yii::t('account', 'Result')) . '?session_id={CHECKOUT_SESSION_ID}',
				'cancel_url' => Url::to(['/account/payment/index'], true) . '/' . Inflector::slug(Yii::t('account', 'Result')) . '?cancel=1',
			];

			switch ($this->type) {
				case PaymentMetadata::PAYMENT_TYPE_PACKAGE:
					$product = $stripe->products->retrieve(
						$subscription->package->external_id,
						[]
					);
					$data['mode'] = 'subscription';
					$data['line_items'] = [
						[
							'price' => $product['default_price'],
							'quantity' => 1,
						],
					];
					break;
				case PaymentMetadata::PAYMENT_TYPE_FEATURE:
					$data['mode'] = 'subscription';
					foreach ($subscription->subscriptionFeatures as $feature) {
						$product = $stripe->products->retrieve(
							$feature->feature->external_id,
							[]
						);
						$data['line_items'][] = [
							'price' => $product['default_price'],
							'quantity' => $feature->value,
						];
					}
					break;
				default:break;
			}

			$customers = $stripe->customers->search(['query' => "email:'" . $subscriber->user->email . "'", 'limit' => 1]);
			$customer = $customers->data[0]->id ?? null;
			if (!empty($customer)) {
				$data['customer'] = $customer;
			} else {
				$data['customer_email'] = $subscriber->user->email;
			}

			$session = $stripe->checkout->sessions->create($data);

			$content = [];
			$content[] = Html::beginTag('form', [
				'id' => 'payment-request-form',
				'accept-charset' => 'UTF-8',
				'action' => $session->url,
				'method' => 'GET',
			]);
			$content[] = Html::endTag('form');
			return implode("\n", $content);
		} catch (\Exception $e) {
			Yii::error('Stripe checkout session failed: ' . $e->getMessage(), 'stripe');
			$this->addError('', $e->getMessage());
			return null;
		}
	}

	/**
	 * Gets the payment request form.
	 *
	 * @return mixed
	 */
	public function getPaymentForm()
	{
		if ($this->payment_processor == PaymentMetadata::PAYMENT_PROCESSOR_STRIPE) {
			return $this->buildStripeCheckout();
		}
		return null;
	}
}
