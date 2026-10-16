<?php

namespace tests\unit;

use common\components\StripeProcessor;
use PHPUnit\Framework\TestCase;

/**
 * The app-level Stripe processor, without the HTTP leg: the request it makes and how
 * the Stripe payload maps onto what SubscriptionPaymentService reads.
 */
class StripeProcessorTest extends TestCase
{
	/**
	 * @param mixed $response what the stubbed makeRequest() returns
	 * @return StubStripeProcessor
	 */
	private function processor($response)
	{
		$processor = new StubStripeProcessor();
		$processor->response = $response;

		return $processor;
	}

	public function testFindTransactionsListsTheCustomersChargesSinceTheDueDate()
	{
		$processor = $this->processor(['data' => [
			['id' => 'ch_ok', 'customer' => 'cus_1', 'amount' => 1000, 'currency' => 'ron', 'status' => 'succeeded', 'refunded' => false],
			['id' => 'ch_refunded', 'customer' => 'cus_1', 'status' => 'succeeded', 'refunded' => true],
			['id' => 'ch_failed', 'customer' => 'cus_1', 'status' => 'failed'],
		]]);

		$transactions = $processor->findTransactions(['payerId' => 'cus_1', 'startDate' => '2026-10-01 10:00:00']);

		$this->assertSame('/charges', $processor->lastRequest[0]);
		$this->assertSame('GET', $processor->lastRequest[1]);
		$this->assertSame('cus_1', $processor->lastRequest[2]['customer']);
		$this->assertSame((new \DateTime('2026-10-01 10:00:00'))->getTimestamp(), $processor->lastRequest[2]['created']['gte']);
		$this->assertCount(3, $transactions);
		$this->assertSame('ch_ok', $transactions[0]->id);
		$this->assertSame(StripeProcessor::TRANSACTION_STATUS_SUCCEEDED, $transactions[0]->status);
		$this->assertSame(StripeProcessor::TRANSACTION_STATUS_FAILED, $transactions[1]->status);
		$this->assertSame(StripeProcessor::TRANSACTION_STATUS_FAILED, $transactions[2]->status);
	}

	public function testFindTransactionsIsEmptyWhenStripeFails()
	{
		// The vendor stub's count(false) fatal used to kill the whole payment/run cron.
		$this->assertSame([], $this->processor(false)->findTransactions(['payerId' => 'cus_1']));
	}

	public function testFindSubscriptionRenewalMapsThePeriodAndTheLatestInvoice()
	{
		$processor = $this->processor([
			'items' => ['data' => [['current_period_start' => 1000, 'current_period_end' => 2000]]],
			'latest_invoice' => [
				'id' => 'in_1',
				'status' => 'paid',
				'billing_reason' => 'subscription_cycle',
				'amount_paid' => 4900,
				'currency' => 'ron',
				'charge' => 'ch_1',
				'lines' => ['data' => [['period' => ['start' => 1000, 'end' => 2000]]]],
			],
		]);

		$renewal = $processor->findSubscriptionRenewal('sub_1');

		$this->assertSame('/subscriptions/sub_1', $processor->lastRequest[0]);
		$this->assertSame(['latest_invoice'], $processor->lastRequest[2]['expand']);
		$this->assertSame(['start' => 1000, 'end' => 2000], $renewal['period']);
		$this->assertSame('paid', $renewal['invoice']['status']);
		$this->assertSame('subscription_cycle', $renewal['invoice']['billing_reason']);
		$this->assertSame(4900, $renewal['invoice']['amount_paid']);
		$this->assertSame(2000, $renewal['invoice']['period_end']);
		$this->assertSame('ch_1', $renewal['invoice']['transaction_id']);
	}

	public function testFindSubscriptionRenewalFallsBackToThePaymentIntentAndTheInvoiceId()
	{
		$withIntent = $this->processor(['latest_invoice' => ['id' => 'in_1', 'payment_intent' => 'pi_1']])->findSubscriptionRenewal('sub_1');
		$bare = $this->processor(['latest_invoice' => ['id' => 'in_2']])->findSubscriptionRenewal('sub_1');

		$this->assertSame('pi_1', $withIntent['invoice']['transaction_id']);
		$this->assertSame('in_2', $bare['invoice']['transaction_id']);
		$this->assertSame(['start' => null, 'end' => null], $bare['period']);
	}

	public function testFindSubscriptionRenewalIsEmptyWhenStripeFails()
	{
		$this->assertSame([], $this->processor(false)->findSubscriptionRenewal('sub_1'));
	}

	public function testFindSubscriptionUpcomingPeriodReadsTheInvoicePreview()
	{
		$processor = $this->processor(['lines' => ['data' => [['period' => ['start' => 3000, 'end' => 4000]]]]]);

		$period = $processor->findSubscriptionUpcomingPeriod('sub_1');

		$this->assertSame(['/invoices/create_preview', 'POST', ['subscription' => 'sub_1']], $processor->lastRequest);
		$this->assertSame(['start' => 3000, 'end' => 4000], $period);
		$this->assertSame([], $this->processor(false)->findSubscriptionUpcomingPeriod('sub_1'));
	}
}

/**
 * StripeProcessor with makeRequest() replaced by a canned response.
 */
class StubStripeProcessor extends StripeProcessor
{
	/**
	 * @var mixed
	 */
	public $response;

	/**
	 * @var array [url, method, data] of the last request
	 */
	public $lastRequest = [];

	protected function makeRequest($url, $method = 'GET', $data = [])
	{
		$this->lastRequest = [$url, $method, $data];
		return $this->response;
	}
}
