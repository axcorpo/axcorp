<?php

namespace backend\modules\subscriber\models;

use common\models\Invoice;
use common\models\Item;
use common\models\PaymentMetadata;
use common\models\Subscription;
use common\models\Template;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use tws\helpers\Url;

class SubscriptionCancelForm extends Subscription
{
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
		return ArrayHelper::merge(parent::rules(), [

		]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [

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
	 * Deletes all unpaid invoices.
	 *
	 * @return bool
	 */
	protected function deleteUnpaidInvoices()
	{
		try {
			$unpaidInvoices = Invoice::find()
				->alias('i')
				->select(['i.id'])
				->leftJoin(Item::tableName() . ' itm', '[[i.id]] = [[itm.invoice_id]]')
				->andWhere(['i.status' => Invoice::STATUS_UNPAID])
				->andWhere([
					'itm.item_id' => $this->id,
					'itm.type' => Item::TYPE_SUBSCRIPTION,
				])
				->indexBy('id')
				->asArray()
				->all();

			if (!empty($unpaidInvoices)) {
				return (bool) Invoice::deleteAll(['id' => array_keys($unpaidInvoices)]);
			}
			return true;
		} catch (\Exception $e) {
			return false;
		} catch (\Throwable $e) {
			return false;
		}
	}

	/**
	 * Cancels the recurring payment on Stripe, definitively: the subscription is
	 * cancelled right away (no cancel_at_period_end), so nothing can be reactivated
	 * from the customer side afterwards. An already cancelled or missing Stripe
	 * subscription counts as done.
	 *
	 * @return bool
	 */
	protected function cancelRecurringPayment()
	{
		try {
			$paymentMetadata = $this->paymentMetadata;
			if (
				!$this->external_id
				|| !$paymentMetadata
				|| $paymentMetadata->payment_processor != PaymentMetadata::PAYMENT_PROCESSOR_STRIPE
				|| !$paymentMetadata->recurring_payment
				|| $paymentMetadata->payment_method != PaymentMetadata::PAYMENT_METHOD_CARD
			) {
				return true;
			}

			$stripe = new \Stripe\StripeClient(
				Yii::$app->payment->via($paymentMetadata->payment_processor)->privateKey
			);

			try {
				$subscription = $stripe->subscriptions->retrieve($this->external_id, []);
			} catch (\Stripe\Exception\InvalidRequestException $e) {
				if ($e->getStripeCode() === 'resource_missing') {
					return true;
				}
				throw $e;
			}
			if (empty($subscription) || $subscription['status'] == 'canceled') {
				return true;
			}

			$subscription = $stripe->subscriptions->cancel($this->external_id, []);

			return $subscription['status'] == 'canceled';
		} catch (\Throwable $e) {
			Yii::error(['message' => 'Stripe subscription cancel failed.', 'subscription' => $this->code, 'reason' => $e->getMessage()], __METHOD__);
			return false;
		}
	}

	/**
	 * Sends an email confirmation.
	 *
	 * @return bool
	 */
	protected function sendEmailConfirmation()
	{
		try {
			$template = Template::findDefaultByTypeAndVariant(Template::TYPE_EMAIL, Template::EMAIL_VARIANT_SUBSCRIPTION_CANCELLATION);
			if (!$template || !($templateTranslation = $template->getTranslation())) {
				throw new \Exception();
			}
			$user = $this->subscriber->user;
			$shortCodeValues = [
				'{{APP_NAME}}' => Yii::$app->name,
				'{{APP_URL}}' => Url::to(['/site/index'], true, '@frontend'),
				'{{APP_LOGO_URL}}' => Url::to('@uploads/' . Yii::$app->settings->get('appLogo'), true) ?: Url::to('@frontend/web/img/logo.png', true),
				'{{APP_LOGO_ALT_URL}}' => Url::to('@uploads/' . Yii::$app->settings->get('appLogoAlt'), true) ?: Url::to('@frontend/web/img/logo-alt.png', true),
				'{{FIRST_NAME}}' => $user->first_name,
				'{{MIDDLE_NAME}}' => $user->middle_name,
				'{{LAST_NAME}}' => $user->last_name,
				'{{CODE}}' => $this->code,
				'{{SUBSCRIPTION}}' => $this->getFormattedName(),
				'{{PRICE}}' => Yii::$app->formatter->asCurrency($this->price, $this->currency),
				'{{BILLING_CYCLE}}' => $this->getFormattedBillingCycle(),
				'{{PAYMENT_PAGE_URL}}' => Url::to(['/account/payment/package'], true, '@frontend'),
			];
			return Yii::$app->mailer->compose()
				->setTo([$user->email => $user->fullName])
				->setSubject(strtr($templateTranslation->subject, $shortCodeValues))
				->setHtmlBody(strtr($templateTranslation->content, $shortCodeValues))
				->send();
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Cancels the subscription definitively: access ends now (end_at is closed at the
	 * cancellation time, which also removes the customer's reactivate option), the open
	 * invoices are dropped and the Stripe subscription is cancelled immediately.
	 *
	 * @return bool|\yii\db\ActiveRecord|self
	 */
	public function cancel()
	{
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			$now = (new \DateTime())->format('Y-m-d H:i:s');
			if (empty($this->end_at) || $this->end_at > $now) {
				$this->end_at = $now;
			}
			if (!parent::cancel()) {
				throw new \Exception();
			}
			if (!$this->deleteUnpaidInvoices()) {
				throw new \Exception();
			}
			if (!$this->cancelRecurringPayment()) {
				throw new \Exception();
			}
			$this->sendEmailConfirmation();
			$dbTransaction->commit();
			return $this;
		} catch(\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}
}
