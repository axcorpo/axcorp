<?php

namespace common\components;

use tws\payment\processors\Stripe;
use Yii;

/**
 * Stripe processor with a working findTransactions().
 *
 * The vendor implementation is a Twispay-era stub: it queries the nonexistent
 * `/transaction` Stripe endpoint (the request returns `false`, `count(false)`
 * fatals on PHP 8 and kills the whole payment/run cron for every subscriber)
 * and leaves the transaction `status` unmapped, so a recurring card payment
 * could never be verified. This override lists the customer's charges — the
 * real Stripe endpoint — and maps them onto the Transaction model that
 * SubscriptionPaymentService::checkSubscription() expects.
 */
class StripeProcessor extends Stripe
{
	/**
	 * {@inheritdoc}
	 *
	 * Supported filters: `payerId` (the Stripe customer id), `startDate`
	 * (inclusive lower bound on the charge creation time). Results come back
	 * newest first, matching the reset()-takes-the-latest usage in
	 * SubscriptionPaymentService.
	 */
	public function findTransactions($filters)
	{
		try {
			$query = ['limit' => 100];
			if (!empty($filters['payerId'])) {
				$query['customer'] = $filters['payerId'];
			}
			if (!empty($filters['startDate'])) {
				$query['created'] = ['gte' => (new \DateTime($filters['startDate']))->getTimestamp()];
			}

			$response = $this->makeRequest('/charges', 'GET', $query);
			if (!is_array($response) || empty($response['data'])) {
				return [];
			}

			$transactions = [];
			foreach ($response['data'] as $charge) {
				$transactions[] = Yii::createObject([
					'class' => $this->transactionModelClass,
					'id' => $charge['id'] ?? null,
					'payerId' => $charge['customer'] ?? null,
					'paymentId' => $charge['id'] ?? null,
					'amount' => $charge['amount'] ?? null,
					'currency' => $charge['currency'] ?? null,
					'description' => $charge['description'] ?? null,
					'status' => (($charge['status'] ?? '') === 'succeeded' && empty($charge['refunded']))
						? static::TRANSACTION_STATUS_SUCCEEDED
						: static::TRANSACTION_STATUS_FAILED,
				]);
			}

			return $transactions;
		} catch (\Throwable $e) {
			// Never break the payment cron over a lookup failure — an empty result
			// just means "payment not confirmed yet" and gets retried next run.
			Yii::warning(['message' => 'Stripe findTransactions failed.', 'reason' => $e->getMessage()], __METHOD__);
			return [];
		}
	}

	/**
	 * The renewal state of a Stripe subscription: its current billing period and the
	 * invoice Stripe last billed on it (`latest_invoice`), both as plain arrays.
	 *
	 * The period is read from the subscription itself (older API versions) or from
	 * its first item (newer ones). Empty on any failure: the caller treats that as
	 * "not confirmed yet" and retries on its next run.
	 *
	 * @param string $subscriptionId the Stripe subscription id (`sub_...`).
	 * @return array{period?: array{start: int|null, end: int|null}, invoice?: array|null}
	 */
	public function findSubscriptionRenewal($subscriptionId)
	{
		try {
			$response = $this->makeRequest('/subscriptions/' . rawurlencode($subscriptionId), 'GET', [
				'expand' => ['latest_invoice'],
			]);
			if (!is_array($response)) {
				return [];
			}
			$item = $response['items']['data'][0] ?? [];
			$start = $response['current_period_start'] ?? ($item['current_period_start'] ?? null);
			$end = $response['current_period_end'] ?? ($item['current_period_end'] ?? null);
			$invoice = $response['latest_invoice'] ?? null;
			if (!is_array($invoice)) {
				$invoice = null;
			} else {
				// The processor's payment reference, best effort across API versions:
				// the charge id, else the payment intent, else the Stripe invoice id.
				$transactionId = is_string($invoice['charge'] ?? null) ? $invoice['charge'] : ($invoice['charge']['id'] ?? null);
				$transactionId = $transactionId ?: (is_string($invoice['payment_intent'] ?? null) ? $invoice['payment_intent'] : null);
				$period = $invoice['lines']['data'][0]['period'] ?? [];
				$invoice = [
					'id' => $invoice['id'] ?? null,
					'status' => $invoice['status'] ?? null,
					'billing_reason' => $invoice['billing_reason'] ?? null,
					'amount_paid' => isset($invoice['amount_paid']) ? (int) $invoice['amount_paid'] : null,
					'currency' => $invoice['currency'] ?? null,
					'period_start' => isset($period['start']) ? (int) $period['start'] : null,
					'period_end' => isset($period['end']) ? (int) $period['end'] : null,
					'transaction_id' => $transactionId ?: ($invoice['id'] ?? null),
				];
			}

			return [
				'period' => [
					'start' => $start ? (int) $start : null,
					'end' => $end ? (int) $end : null,
				],
				'invoice' => $invoice,
			];
		} catch (\Throwable $e) {
			Yii::warning(['message' => 'Stripe findSubscriptionRenewal failed.', 'reason' => $e->getMessage()], __METHOD__);
			return [];
		}
	}

	/**
	 * The period the subscription's NEXT cycle invoice will bill, as UTC timestamps,
	 * from Stripe's invoice preview (`/invoices/create_preview`): the same anchor
	 * arithmetic the real invoice will use (a 31st anchor bills until the 28th, then
	 * the 31st again), which a local "+1 month" cannot reproduce. A preview creates
	 * nothing on Stripe's side. Empty on any failure: the caller computes locally.
	 *
	 * @param string $subscriptionId the Stripe subscription id (`sub_...`).
	 * @return array{start?: int|null, end?: int|null}
	 */
	public function findSubscriptionUpcomingPeriod($subscriptionId)
	{
		try {
			$response = $this->makeRequest('/invoices/create_preview', 'POST', [
				'subscription' => $subscriptionId,
			]);
			$period = is_array($response) ? ($response['lines']['data'][0]['period'] ?? null) : null;
			if (!is_array($period)) {
				return [];
			}

			return [
				'start' => !empty($period['start']) ? (int) $period['start'] : null,
				'end' => !empty($period['end']) ? (int) $period['end'] : null,
			];
		} catch (\Throwable $e) {
			Yii::warning(['message' => 'Stripe findSubscriptionUpcomingPeriod failed.', 'reason' => $e->getMessage()], __METHOD__);
			return [];
		}
	}
}
