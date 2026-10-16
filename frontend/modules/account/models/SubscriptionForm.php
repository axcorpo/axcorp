<?php

namespace frontend\modules\account\models;

use common\models\Company;
use common\models\PaymentMetadata;
use common\models\Subscription;
use Yii;
use yii\base\Model;

class SubscriptionForm extends Subscription
{
	/**
	 * @var bool Flag that indicates if the payer agrees with the automatic payment.
	 */
	public $enable_recurring_payment;


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
			[['enable_recurring_payment'], 'boolean'],
			// Only one of the signed-in account's own companies may be billed.
			[['company_id'], 'exist', 'skipOnError' => true, 'targetClass' => Company::class, 'targetAttribute' => ['company_id' => 'id'], 'filter' => ['user_id' => Yii::$app->user->id]],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'enable_recurring_payment' => Yii::t('label', 'Enable Recurring Payment'),
			'company_id' => Yii::t('label', 'Company'),
		];
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

		$this->enable_recurring_payment = $this->paymentMetadata->recurring_payment;
		// Expose the BINARY(16) FK as a canonical UUID string so the widget (whose option
		// keys are UUID strings) can match the selected value; beforeSave / UuidActiveQuery
		// convert back to bytes on the save path.
		$this->company_id = $this->uuidOf('company_id') ?: null;
	}

	/**
	 * Saves the PaymentMetadata model.
	 *
	 * @return bool
	 */
	protected function savePaymentMetadata()
	{
		try {
			if (!($paymentMetadata = $this->paymentMetadata)) {
				$paymentMetadata = new PaymentMetadata();
				$paymentMetadata->subscription_id = $this->id;
			}
			$paymentMetadata->recurring_payment = $this->enable_recurring_payment;
			$paymentMetadata->ip_address = Yii::$app->request->userIP;
			$paymentMetadata->status = PaymentMetadata::STATUS_ACTIVE;
			if (!$paymentMetadata->save()) {
				throw new \Exception('Payment metadata cannot be saved.');
			}

			if ($paymentMetadata->payment_processor == PaymentMetadata::PAYMENT_PROCESSOR_STRIPE && $paymentMetadata->payment_method == PaymentMetadata::PAYMENT_METHOD_CARD) {
				// Keep the Stripe subscription in step with the flag: otherwise Stripe
				// keeps charging a subscription the app no longer recognizes as recurring
				// (or vice versa). The vendor processor's updatePayment/cancelRecurringPayment
				// are Twispay-era stubs that hit the wrong Stripe endpoints with charge ids.
				if (!$this->syncStripeRecurringPayment((bool) $paymentMetadata->recurring_payment)) {
					throw new \Exception('Recurring payment cannot be updated.');
				}
			} elseif ($paymentMetadata->payment_processor && $paymentMetadata->payment_method == PaymentMetadata::PAYMENT_METHOD_CARD) {
				$paymentProcessor = Yii::$app->payment->via($paymentMetadata->payment_processor);
				if ($paymentMetadata->recurring_payment) {
					$payment = $paymentProcessor->updatePayment($paymentMetadata->payment_id, [
						'payerId' => $paymentMetadata->payer_id,
						'cardId' => $paymentMetadata->card_id,
						'ipAddress' => $paymentMetadata->ip_address,
						'status' => $paymentProcessor::PAYMENT_STATUS_IN_PROGRESS,
					]);
					if (!$payment) {
						throw new \Exception('Recurring payment cannot be updated.');
					}
				} else {
					if (!$paymentProcessor->cancelRecurringPayment($paymentMetadata->payment_id)) {
						throw new \Exception('Recurring payment cannot be cancelled.');
					}
				}
			}

			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * Mirrors the recurring flag onto the Stripe subscription (same pattern as
	 * SubscriptionCancelForm::cancelRecurringPayment).
	 *
	 * - disabling: schedule the Stripe subscription to cancel at period end; the
	 *   already-paid period keeps running, no further charges;
	 * - enabling: lift a pending cancel_at_period_end. A Stripe subscription that
	 *   already ended is terminal: re-enabling is refused with a field error, the
	 *   subscriber has to go through a new payment instead.
	 *
	 * @param bool $enable
	 * @return bool
	 */
	protected function syncStripeRecurringPayment($enable)
	{
		try {
			if (!$this->external_id) {
				// Nothing to sync (e.g. created before external ids were stored): do not
				// block the local flag over it.
				return true;
			}

			$paymentProcessor = Yii::$app->payment->via(PaymentMetadata::PAYMENT_PROCESSOR_STRIPE);
			$stripe = new \Stripe\StripeClient(
				$paymentProcessor->privateKey
			);

			$subscription = $stripe->subscriptions->retrieve(
				$this->external_id,
				[]
			);

			if ($enable) {
				if (empty($subscription) || $subscription['status'] == 'canceled') {
					$this->addError('enable_recurring_payment', Yii::t('frontend', 'The recurring payment can no longer be resumed because the card subscription has ended. Please make a new payment instead.'));
					return false;
				}
				if ($subscription['cancel_at_period_end']) {
					$subscription = $stripe->subscriptions->update(
						$this->external_id,
						[
							'cancel_at_period_end' => false,
						]
					);
					return !$subscription['cancel_at_period_end'];
				}
				return true;
			}

			if (empty($subscription) || $subscription['status'] == 'canceled' || $subscription['cancel_at_period_end']) {
				return true;
			}
			$subscription = $stripe->subscriptions->update(
				$this->external_id,
				[
					'cancel_at_period_end' => true,
				]
			);
			return (bool) $subscription['cancel_at_period_end'];
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
			if (!parent::save($runValidation, $attributeNames)) {
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
