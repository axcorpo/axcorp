<?php
namespace console\controllers;

use common\models\Subscription;
use common\services\SubscriptionPaymentService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * The subscription billing runner. The pipeline itself lives in
 * {@see SubscriptionPaymentService}, shared with the Stripe webhook: this command is the
 * polling side that issues the proformas ahead of the due date and, at the due date,
 * verifies the recurring payment or suspends the subscription past the grace window.
 */
class PaymentController extends Controller
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		SubscriptionPaymentService::setCurrentDate();
	}

	/**
	 * Run the payment check.
	 *
	 * Two passes, each subscription on its own: a failure (the service catches
	 * exceptions, not every error) is logged and must not stop the rest of this run.
	 *
	 * @return int
	 */
	public function actionRun()
	{
		try {
			// 1. Issue the proforma of the upcoming renewal, then send it to email
			$issued = 0;
			foreach (SubscriptionPaymentService::getDueSubscriptions() as $subscription) {
				try {
					if (SubscriptionPaymentService::saveInvoice($subscription)) {
						$issued++;
					}
				} catch (\Throwable $e) {
					Yii::error(['message' => 'Proforma issuance failed.', 'subscription' => $subscription->code, 'error' => $e->getMessage()], __METHOD__);
				}
			}

			// 2. Check the subscriptions that reached their end: settle the collected
			// payment, or suspend past the allowed payment overdue period
			$checked = 0;
			foreach (SubscriptionPaymentService::getExpiredSubscriptions() as $subscription) {
				try {
					// A free term subscription is not billed: no proforma, straight to the check.
					$invoice = null;
					if ($subscription->type != Subscription::TYPE_FREE) {
						$invoice = SubscriptionPaymentService::findCycleProforma($subscription) ?: SubscriptionPaymentService::saveInvoice($subscription);
						if (!$invoice) {
							continue;
						}
					}
					SubscriptionPaymentService::checkSubscription($subscription, $invoice);
					$checked++;
				} catch (\Throwable $e) {
					Yii::error(['message' => 'Subscription check failed.', 'subscription' => $subscription->code, 'error' => $e->getMessage()], __METHOD__);
				}
			}

			$this->stdout("[" . date("Y-m-d H:i:s") . "] Issued {$issued} invoice(s), checked {$checked} subscription(s).\n");
			return ExitCode::OK;
		} catch (\Throwable $e) {
			$this->stderr($e->getMessage() . "\n");
			return ExitCode::UNSPECIFIED_ERROR;
		}
	}
}
