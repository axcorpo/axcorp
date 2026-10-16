<?php

namespace frontend\modules\account\models;

use common\models\Invoice;
use common\models\Item;
use common\models\PaymentMetadata;
use common\models\Subscription;
use common\models\Template;
use Yii;
use yii\base\Model;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use tws\helpers\Url;

class SubscriptionCancelForm extends Subscription
{
	/**
	 * @var \DateTime The current date and time instance used for this process operations.
	 */
	public static $currentDate;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
		self::$currentDate = new \DateTime();
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
	 * Cancels the recurring payment using a specific payment processor.
	 *
	 * @return bool
	 */
	protected function cancelRecurringPayment()
	{
		try {

			$paymentMetadata = $this->paymentMetadata;

			if (!$this->external_id || $paymentMetadata->payment_processor != PaymentMetadata::PAYMENT_PROCESSOR_STRIPE || !$paymentMetadata->recurring_payment || $paymentMetadata->payment_method != PaymentMetadata::PAYMENT_METHOD_CARD) {
				return true;
			}

			$paymentProcessor = Yii::$app->payment->via($paymentMetadata->payment_processor);

			$stripe = new \Stripe\StripeClient(
				$paymentProcessor->privateKey
			);

			$subscription = $stripe->subscriptions->retrieve(
				$this->external_id,
				[]
			);

			if (empty($subscription) || $subscription['status'] == 'canceled') {
				return true;
			}

			$subscription = $stripe->subscriptions->update(
				$this->external_id,
				[
					'cancel_at_period_end' => true,
				]
			);

			// An explicit bool on every path (the method used to fall out returning null).
			return (bool) $subscription['cancel_at_period_end'];
		} catch (\Exception $e) {
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
				'{{APP_URL}}' => Url::to(['/site/index'], true),
				'{{APP_LOGO}}' => Url::to('@uploads/' . Yii::$app->settings->get('appLogo'), true) ?: Url::to('@web/img/logo.png', true),
				'{{APP_LOGO_ALT}}' => Url::to('@uploads/' . Yii::$app->settings->get('appLogoAlt'), true) ?: Url::to('@web/img/logo-alt.png', true),
				'{{FIRST_NAME}}' => $user->first_name,
				'{{MIDDLE_NAME}}' => $user->middle_name,
				'{{LAST_NAME}}' => $user->last_name,
				'{{CODE}}' => $this->code,
				'{{SUBSCRIPTION}}' => $this->getFormattedName(),
				'{{PRICE}}' => Yii::$app->formatter->asCurrency($this->price, $this->currency),
				'{{BILLING_CYCLE}}' => $this->getFormattedBillingCycle(),
				'{{PAYMENT_PAGE_URL}}' => Url::to(['/account/payment/package'], true),
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
	 * Cancels the subscription.
	 *
	 * @return bool|\yii\db\ActiveRecord|self
	 */
	public function cancel()
	{
		$currentDate = self::$currentDate->format('Y-m-d H:i:s');
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			if (!parent::cancel()) {
				throw new \Exception();
			}
			if ($currentDate > $this->end_at) {
				if (!$this->deleteUnpaidInvoices()) {
					throw new \Exception();
				}
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
