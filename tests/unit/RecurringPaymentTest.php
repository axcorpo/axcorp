<?php

namespace tests\unit;

use common\models\Invoice;
use common\models\PaymentMetadata;
use common\models\Subscription;
use common\services\SubscriptionPaymentService;
use tests\DatabaseTestCase;
use tests\support\FakePayment;
use tests\support\FakePaymentProcessor;
use tests\support\FakeSettings;
use Yii;

/**
 * The recurring payment renewal flow, end to end against a mocked payment
 * processor: an expired-but-in-grace subscription with recurring card metadata is
 * either renewed (successful transaction found) or left for the overdue clock
 * (no successful transaction).
 */
class RecurringPaymentTest extends DatabaseTestCase
{
	/**
	 * @var FakePaymentProcessor
	 */
	private $processor;

	protected function setUp(): void
	{
		parent::setUp();
		Yii::$app->set('settings', new FakeSettings([
			'values' => [
				'payment' => [
					'invoiceIssuanceBeforePeriod' => 5,
					'allowedPaymentOverduePeriod' => 5,
					'enableInvoiceEmailNotification' => 0,
				],
				'general' => [
					'vatRate' => 19,
				],
				// Invoice::getMerchantDetails() reads every contact key unguarded.
				'contact' => array_fill_keys([
					'company',
					'tin',
					'registrationNumber',
					'firstName',
					'middleName',
					'lastName',
					'email',
					'mobilePhone',
					'fixedPhone',
					'fax',
					'streetName',
					'streetNumber',
					'zipCode',
					'locality',
					'county',
					'country',
				], ''),
			],
		]));
		$payment = new FakePayment();
		$this->processor = $payment->processor;
		Yii::$app->set('payment', $payment);
		SubscriptionPaymentService::setCurrentDate();

		// The pipeline issues proformas and fiscal invoices: Invoice's
		// DocumentSeriesAndNumberBehavior picks the default series of the document's
		// type (FF for fiscal invoices, PF for proformas), and each document gets the
		// default invoice template of its variant.
		$this->insertRow('document_series', [
			'name' => 'FF', 'type' => \common\models\DocumentSeries::TYPE_INVOICE,
			'default' => 1, 'first_number' => 1, 'status' => 1, 'deleted' => 0,
		]);
		$this->insertRow('document_series', [
			'name' => 'PF', 'type' => \common\models\DocumentSeries::TYPE_PROFORM,
			'default' => 1, 'first_number' => 1, 'status' => 1, 'deleted' => 0,
		]);
		$this->insertRow('template', [
			'type' => \common\models\Template::TYPE_INVOICE,
			'variant' => \common\models\Template::INVOICE_VARIANT_FISCAL,
			'default' => 1, 'status' => 1, 'deleted' => 0,
		]);
		$this->insertRow('template', [
			'type' => \common\models\Template::TYPE_INVOICE,
			'variant' => \common\models\Template::INVOICE_VARIANT_PROFORM,
			'default' => 1, 'status' => 1, 'deleted' => 0,
		]);
	}

	protected function tearDown(): void
	{
		Yii::$app->clear('payment');
		Yii::$app->clear('settings');
		Yii::$app->clear('instance');
		parent::tearDown();
	}

	/**
	 * An expired-but-in-grace subscription with recurring card metadata.
	 *
	 * @return string subscription id (raw bytes)
	 */
	private function createRecurringSubscription(array $attributes = [])
	{
		// The invoices bill the subscriber's own user (Invoice::getClientDetails()).
		$userId = $this->insertRow('user', ['deleted' => 0]);
		$subscriberId = $this->insertRow('subscriber', ['user_id' => $userId, 'deleted' => 0]);
		$id = $this->createSubscription(array_merge([
			'subscriber_id' => $subscriberId,
			'end_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
		], $attributes));
		$this->insertRow('payment_metadata', [
			'subscription_id' => $id,
			'payment_method' => PaymentMetadata::PAYMENT_METHOD_CARD,
			'payment_processor' => PaymentMetadata::PAYMENT_PROCESSOR_STRIPE,
			'payer_id' => 'payer_123',
			'payment_id' => 'pm_123',
			'recurring_payment' => 1,
			'deleted' => 0,
		]);
		return $id;
	}

	public function testSuccessfulRecurringPaymentRenewsAndMarksInvoicePaid()
	{
		$id = $this->createRecurringSubscription();
		$oldEndAt = $this->fetchColumn('subscription', $id, 'end_at');
		$invoiceId = $this->insertRow('invoice', ['status' => Invoice::STATUS_UNPAID, 'deleted' => 0]);

		$this->processor->transactions = [
			(object) ['id' => 'tx_1', 'status' => FakePaymentProcessor::TRANSACTION_STATUS_SUCCEEDED],
		];

		$result = SubscriptionPaymentService::checkSubscription(
			Subscription::findOne(['id' => $id]),
			Invoice::findOne(['id' => $invoiceId])
		);

		$this->assertTrue($result);
		// The subscription was re-activated for a new billing cycle from its old end date
		$this->assertEquals(Subscription::STATUS_ACTIVE, $this->fetchColumn('subscription', $id, 'status'));
		$this->assertSame(
			date('Y-m-d H:i:s', strtotime($oldEndAt . ' +1 month')),
			$this->fetchColumn('subscription', $id, 'end_at')
		);
		// The proforma stays UNPAID — it is a payment request, not the accounting
		// document; the money is documented by a fresh PAID FISCAL invoice whose
		// parent_id points back at the proforma.
		$this->assertEquals(Invoice::STATUS_UNPAID, $this->fetchColumn('invoice', $invoiceId, 'status'));
		$fiscal = (new \yii\db\Query())
			->from('invoice')
			->where(['parent_id' => $invoiceId, 'deleted' => 0])
			->one(Yii::$app->db);
		$this->assertNotFalse($fiscal, 'the settle must issue a fiscal invoice from the proforma');
		$this->assertEquals(Invoice::TYPE_INVOICE, $fiscal['type']);
		$this->assertEquals(Invoice::STATUS_PAID, $fiscal['status']);
		$this->assertNotNull($fiscal['paid_at']);
		$this->assertSame('FF', $fiscal['document_series']);
		// The processor was queried for this subscription's payment profile
		$this->assertSame('payer_123', $this->processor->lastFilters['payerId']);
	}

	/**
	 * A linked subscription's open proforma (amount/currency are what the processor
	 * invoice is matched against).
	 */
	private function insertProforma($amount = 100.00, $currency = 'RON')
	{
		return $this->insertRow('invoice', [
			'type' => Invoice::TYPE_PROFORMA,
			'status' => Invoice::STATUS_UNPAID,
			'amount' => $amount,
			'currency' => $currency,
			'deleted' => 0,
		]);
	}

	/**
	 * What the processor reports for a linked subscription whose renewal was collected:
	 * the paid cycle invoice and the (already advanced) current period.
	 */
	private function paidRenewal($oldEndAt, array $invoiceOverrides = [], $periodStart = null, $periodEnd = null)
	{
		$start = $periodStart ?: strtotime($oldEndAt);
		$end = $periodEnd ?: strtotime($oldEndAt . ' +1 month');
		return [
			'period' => ['start' => $start, 'end' => $end],
			'invoice' => array_merge([
				'id' => 'in_1',
				'status' => 'paid',
				'billing_reason' => 'subscription_cycle',
				'amount_paid' => 10000,
				'currency' => 'ron',
				'period_start' => $start,
				'period_end' => $end,
				'transaction_id' => 'ch_1',
			], $invoiceOverrides),
		];
	}

	private function check($id, $invoiceId)
	{
		return SubscriptionPaymentService::checkSubscription(
			Subscription::findOne(['id' => $id]),
			Invoice::findOne(['id' => $invoiceId])
		);
	}

	public function testLinkedSubscriptionIsVerifiedOnItsOwnProcessorInvoice()
	{
		$id = $this->createRecurringSubscription(['external_id' => 'sub_123']);
		$oldEndAt = $this->fetchColumn('subscription', $id, 'end_at');
		$invoiceId = $this->insertProforma();
		// Stripe's period: starts a minute after the old end (so it is visibly not the
		// local anchor) and runs 30 days, not the package's calendar month.
		$start = strtotime($oldEndAt) + 60;
		$end = $start + 30 * 86400;
		$this->processor->renewal = $this->paidRenewal($oldEndAt, [], $start, $end);
		$this->processor->transactions = []; // the customer's charges are not consulted

		$this->assertTrue($this->check($id, $invoiceId));
		$this->assertSame('sub_123', $this->processor->lastRenewalSubscriptionId);
		$this->assertSame([], $this->processor->lastFilters, 'a linked subscription must not fall back to the charge listing');
		$timeZone = new \DateTimeZone(Yii::$app->timeZone);
		$this->assertSame((new \DateTime('@' . $start))->setTimezone($timeZone)->format('Y-m-d H:i:s'), $this->fetchColumn('subscription', $id, 'start_at'));
		$this->assertSame((new \DateTime('@' . $end))->setTimezone($timeZone)->format('Y-m-d H:i:s'), $this->fetchColumn('subscription', $id, 'end_at'));
		$fiscal = (new \yii\db\Query())->from('invoice')->where(['parent_id' => $invoiceId, 'deleted' => 0])->one(Yii::$app->db);
		$this->assertNotFalse($fiscal);
		$this->assertStringContainsString('ch_1', (string) $fiscal['details']);
	}

	public function testLinkedSubscriptionWithoutAReachableProcessorIsRetriedLater()
	{
		$id = $this->createRecurringSubscription(['external_id' => 'sub_123']);
		$oldEndAt = $this->fetchColumn('subscription', $id, 'end_at');
		$invoiceId = $this->insertProforma();
		$this->processor->renewal = []; // Stripe unreachable
		$this->processor->transactions = [
			(object) ['id' => 'tx_1', 'status' => FakePaymentProcessor::TRANSACTION_STATUS_SUCCEEDED],
		];

		$this->assertFalse($this->check($id, $invoiceId));
		$this->assertSame($oldEndAt, $this->fetchColumn('subscription', $id, 'end_at'));
		$this->assertEquals(Subscription::STATUS_ACTIVE, $this->fetchColumn('subscription', $id, 'status'));
		$this->assertSame([], $this->processor->lastFilters);
	}

	/**
	 * @dataProvider notARenewalPaymentProvider
	 */
	public function testLinkedSubscriptionIsNotRenewedOnAProcessorInvoiceThatIsNotItsPaidRenewal(array $invoiceOverrides)
	{
		$id = $this->createRecurringSubscription(['external_id' => 'sub_123']);
		$oldEndAt = $this->fetchColumn('subscription', $id, 'end_at');
		$invoiceId = $this->insertProforma();
		$this->processor->renewal = $this->paidRenewal($oldEndAt, $invoiceOverrides);

		$this->assertFalse($this->check($id, $invoiceId));
		$this->assertSame($oldEndAt, $this->fetchColumn('subscription', $id, 'end_at'));
		$this->assertFalse((new \yii\db\Query())->from('invoice')->where(['parent_id' => $invoiceId])->exists(Yii::$app->db));
	}

	public function notARenewalPaymentProvider()
	{
		$oldEndAt = date('Y-m-d H:i:s', strtotime('-2 days'));
		return [
			'invoice still open' => [['status' => 'open']],
			'first purchase, not a cycle' => [['billing_reason' => 'subscription_create']],
			'amount differs from the proforma' => [['amount_paid' => 9900]],
			'currency differs from the proforma' => [['currency' => 'eur']],
			'previous cycle (ends at the current end_at)' => [['period_end' => strtotime($oldEndAt)]],
			'no period on the invoice' => [['period_end' => null]],
		];
	}

	public function testProcessorIsNotAskedForTheRenewalWithoutAnExternalId()
	{
		$id = $this->createRecurringSubscription(['external_id' => null]);
		$oldEndAt = $this->fetchColumn('subscription', $id, 'end_at');
		$invoiceId = $this->insertProforma();
		$this->processor->renewal = $this->paidRenewal($oldEndAt, [], time(), time() + 86400);
		$this->processor->transactions = [
			(object) ['id' => 'tx_1', 'status' => FakePaymentProcessor::TRANSACTION_STATUS_SUCCEEDED],
		];

		$this->assertTrue($this->check($id, $invoiceId));
		$this->assertNull($this->processor->lastRenewalSubscriptionId);
		$this->assertSame('payer_123', $this->processor->lastFilters['payerId']);
		$this->assertSame(date('Y-m-d H:i:s', strtotime($oldEndAt . ' +1 month')), $this->fetchColumn('subscription', $id, 'end_at'));
	}

	public function testStaleProcessorPeriodFallsBackToTheLocalCycle()
	{
		// A paid renewal invoice, but a current period that does not move past the
		// current end_at (stale read) is not a new cycle: the local anchor is used.
		$id = $this->createRecurringSubscription(['external_id' => 'sub_123']);
		$oldEndAt = $this->fetchColumn('subscription', $id, 'end_at');
		$invoiceId = $this->insertProforma();
		$renewal = $this->paidRenewal($oldEndAt);
		$renewal['period'] = ['start' => strtotime($oldEndAt . ' -1 month'), 'end' => strtotime($oldEndAt)];
		$this->processor->renewal = $renewal;

		$this->assertTrue($this->check($id, $invoiceId));
		$this->assertSame($oldEndAt, $this->fetchColumn('subscription', $id, 'start_at'));
		$this->assertSame(date('Y-m-d H:i:s', strtotime($oldEndAt . ' +1 month')), $this->fetchColumn('subscription', $id, 'end_at'));
	}

	public function testMissingProcessorPeriodFallsBackToTheLocalCycle()
	{
		$id = $this->createRecurringSubscription(['external_id' => 'sub_123']);
		$oldEndAt = $this->fetchColumn('subscription', $id, 'end_at');
		$invoiceId = $this->insertProforma();
		$renewal = $this->paidRenewal($oldEndAt);
		$renewal['period'] = ['start' => null, 'end' => null];
		$this->processor->renewal = $renewal;

		$this->assertTrue($this->check($id, $invoiceId));
		$this->assertSame(date('Y-m-d H:i:s', strtotime($oldEndAt . ' +1 month')), $this->fetchColumn('subscription', $id, 'end_at'));
	}

	public function testFiscalInvoiceLineCoversTheProcessorPeriod()
	{
		$id = $this->createRecurringSubscription(['external_id' => 'sub_123']);
		$oldEndAt = $this->fetchColumn('subscription', $id, 'end_at');
		$invoiceId = $this->insertProforma();
		// The proforma's line was computed locally: +1 calendar month
		$this->insertRow('item', [
			'invoice_id' => $invoiceId,
			'item_id' => $id,
			'type' => \common\models\Item::TYPE_SUBSCRIPTION,
			'status' => \common\models\Item::STATUS_ACTIVE,
			'deleted' => 0,
			'details' => serialize(['start_at' => $oldEndAt, 'end_at' => date('Y-m-d H:i:s', strtotime($oldEndAt . ' +1 month'))]),
		]);
		$start = strtotime($oldEndAt) + 60;
		$end = $start + 30 * 86400;
		$this->processor->renewal = $this->paidRenewal($oldEndAt, [], $start, $end);

		$this->assertTrue($this->check($id, $invoiceId));
		$fiscal = (new \yii\db\Query())->from('invoice')->where(['parent_id' => $invoiceId, 'deleted' => 0])->one(Yii::$app->db);
		$line = (new \yii\db\Query())->from('item')->where(['invoice_id' => $fiscal['id']])->one(Yii::$app->db);
		$details = unserialize($line['details']);
		$timeZone = new \DateTimeZone(Yii::$app->timeZone);
		$this->assertSame((new \DateTime('@' . $start))->setTimezone($timeZone)->format('Y-m-d H:i:s'), $details['start_at']);
		$this->assertSame((new \DateTime('@' . $end))->setTimezone($timeZone)->format('Y-m-d H:i:s'), $details['end_at']);
		// ... and matches what the subscription itself now says
		$this->assertSame($details['start_at'], $this->fetchColumn('subscription', $id, 'start_at'));
		$this->assertSame($details['end_at'], $this->fetchColumn('subscription', $id, 'end_at'));
	}

	public function testFiscalInvoiceLineCopiesTheProformaLineWithoutAProcessorPeriod()
	{
		$id = $this->createRecurringSubscription(['external_id' => 'sub_123']);
		$oldEndAt = $this->fetchColumn('subscription', $id, 'end_at');
		$invoiceId = $this->insertProforma();
		$proformaDetails = ['start_at' => $oldEndAt, 'end_at' => date('Y-m-d H:i:s', strtotime($oldEndAt . ' +1 month'))];
		$this->insertRow('item', [
			'invoice_id' => $invoiceId,
			'item_id' => $id,
			'type' => \common\models\Item::TYPE_SUBSCRIPTION,
			'status' => \common\models\Item::STATUS_ACTIVE,
			'deleted' => 0,
			'details' => serialize($proformaDetails),
		]);
		$renewal = $this->paidRenewal($oldEndAt);
		$renewal['period'] = ['start' => null, 'end' => null];
		$this->processor->renewal = $renewal;

		$this->assertTrue($this->check($id, $invoiceId));
		$fiscal = (new \yii\db\Query())->from('invoice')->where(['parent_id' => $invoiceId, 'deleted' => 0])->one(Yii::$app->db);
		$line = (new \yii\db\Query())->from('item')->where(['invoice_id' => $fiscal['id']])->one(Yii::$app->db);

		$this->assertSame($proformaDetails, unserialize($line['details']));
	}

	/**
	 * A linked, package-backed subscription with what saveInvoice() needs to issue a
	 * proforma (the series and templates come from setUp): a package with a name.
	 */
	private function createIssuableSubscription($endAt)
	{
		// The issuance notification links into the frontend through the `instance`
		// component, which the test app does not have; and a console URL manager
		// needs a script URL to build any URL at all.
		Yii::$app->set('instance', new \tests\support\FakeInstance());
		Yii::$app->urlManager->scriptUrl = '/index.php';
		$packageId = $this->insertRow('package', [
			'billing_period' => 1, 'billing_cycle' => 'month', 'price' => 100, 'currency' => 'RON',
			'type' => Subscription::TYPE_STANDARD, 'status' => 1, 'deleted' => 0,
		]);
		$this->insertRow('package_translation', [
			'package_id' => $packageId, 'language_id' => Yii::$app->language, 'name' => 'Standard', 'deleted' => 0,
		]);
		$id = $this->createRecurringSubscription([
			'external_id' => 'sub_123',
			'package_id' => $packageId,
			'end_at' => $endAt,
			'price' => 100,
			'currency' => 'RON',
		]);

		return $id;
	}

	private function proformaLineDetails($subscriptionId)
	{
		$line = (new \yii\db\Query())
			->from(['itm' => 'item'])
			->innerJoin(['i' => 'invoice'], '[[i.id]] = [[itm.invoice_id]]')
			->where(['itm.item_id' => $subscriptionId, 'i.type' => Invoice::TYPE_PROFORMA, 'i.deleted' => 0])
			->select(['itm.details', 'i.due_at'])
			->one(Yii::$app->db);
		$this->assertNotFalse($line, 'the proforma and its subscription line must be issued');

		return [unserialize($line['details']), $line['due_at']];
	}

	public function testProformaLineBillsTheProcessorsUpcomingCycle()
	{
		// A 31st anchor: Stripe bills Feb 28 → Mar 31, "+1 month" says Feb 28 → Mar 28.
		$endAt = '2027-02-28 10:00:00';
		$id = $this->createIssuableSubscription($endAt);
		$timeZone = new \DateTimeZone(Yii::$app->timeZone);
		$start = (new \DateTime($endAt, $timeZone))->getTimestamp();
		$end = (new \DateTime('2027-03-31 11:00:00', $timeZone))->getTimestamp();
		$this->processor->upcomingPeriod = ['start' => $start, 'end' => $end];

		$invoice = SubscriptionPaymentService::saveInvoice(Subscription::findOne(['id' => $id]));

		$this->assertNotNull($invoice);
		$this->assertSame('sub_123', $this->processor->lastUpcomingSubscriptionId);
		[$details, $dueAt] = $this->proformaLineDetails($id);
		$this->assertSame($endAt, $dueAt);
		$this->assertSame(['start_at' => $endAt, 'end_at' => '2027-03-31 11:00:00'], $details);
	}

	public function testProformaLineFallsBackToTheLocalPeriodWithoutAPreview()
	{
		$endAt = '2027-02-28 10:00:00';
		$id = $this->createIssuableSubscription($endAt);
		$this->processor->upcomingPeriod = []; // Stripe unreachable / no preview

		$invoice = SubscriptionPaymentService::saveInvoice(Subscription::findOne(['id' => $id]));

		$this->assertNotNull($invoice);
		[$details] = $this->proformaLineDetails($id);
		$this->assertSame(['start_at' => $endAt, 'end_at' => '2027-03-28 10:00:00'], $details);
	}

	public function testProformaLineIgnoresAPreviewThatDoesNotReachPastTheCurrentEnd()
	{
		$endAt = '2027-02-28 10:00:00';
		$id = $this->createIssuableSubscription($endAt);
		$timeZone = new \DateTimeZone(Yii::$app->timeZone);
		// A stale preview: the cycle that ends at the current end_at
		$this->processor->upcomingPeriod = [
			'start' => (new \DateTime('2027-01-31 10:00:00', $timeZone))->getTimestamp(),
			'end' => (new \DateTime($endAt, $timeZone))->getTimestamp(),
		];

		$this->assertNotNull(SubscriptionPaymentService::saveInvoice(Subscription::findOne(['id' => $id])));
		[$details] = $this->proformaLineDetails($id);
		$this->assertSame(['start_at' => $endAt, 'end_at' => '2027-03-28 10:00:00'], $details);
	}

	public function testProcessorIsNotAskedForAPreviewWithoutAnExternalId()
	{
		$id = $this->createIssuableSubscription('2027-02-28 10:00:00');
		Yii::$app->db->createCommand()->update('subscription', ['external_id' => null], ['id' => $id])->execute();
		$this->processor->upcomingPeriod = ['start' => time(), 'end' => time() + 40 * 86400];

		$this->assertNotNull(SubscriptionPaymentService::saveInvoice(Subscription::findOne(['id' => $id])));
		$this->assertNull($this->processor->lastUpcomingSubscriptionId);
		[$details] = $this->proformaLineDetails($id);
		$this->assertSame('2027-03-28 10:00:00', $details['end_at']);
	}

	public function testFormatProcessorTimestampUsesTheAppTimeZone()
	{
		// 2026-09-29 19:53:42 UTC is 22:53:42 in Europe/Bucharest (summer time)
		$timestamp = gmmktime(19, 53, 42, 9, 29, 2026);
		$expected = (new \DateTime('@' . $timestamp))->setTimezone(new \DateTimeZone(Yii::$app->timeZone))->format('Y-m-d H:i:s');

		$this->assertSame($expected, SubscriptionPaymentService::formatProcessorTimestamp($timestamp));
		$this->assertSame($expected, SubscriptionPaymentService::formatProcessorTimestamp((string) $timestamp));
		$this->assertNull(SubscriptionPaymentService::formatProcessorTimestamp(null));
		$this->assertNull(SubscriptionPaymentService::formatProcessorTimestamp(0));
	}

	public function testFailedRecurringPaymentLeavesEverythingUnchanged()
	{
		$id = $this->createRecurringSubscription();
		$oldEndAt = $this->fetchColumn('subscription', $id, 'end_at');
		$invoiceId = $this->insertRow('invoice', ['status' => Invoice::STATUS_UNPAID, 'deleted' => 0]);

		$this->processor->transactions = []; // no successful charge found

		$result = SubscriptionPaymentService::checkSubscription(
			Subscription::findOne(['id' => $id]),
			Invoice::findOne(['id' => $invoiceId])
		);

		$this->assertFalse($result);
		$this->assertSame($oldEndAt, $this->fetchColumn('subscription', $id, 'end_at'));
		$this->assertEquals(Subscription::STATUS_ACTIVE, $this->fetchColumn('subscription', $id, 'status'));
		$this->assertEquals(Invoice::STATUS_UNPAID, $this->fetchColumn('invoice', $invoiceId, 'status'));
	}

	public function testDeclinedTransactionDoesNotRenew()
	{
		$id = $this->createRecurringSubscription();
		$invoiceId = $this->insertRow('invoice', ['status' => Invoice::STATUS_UNPAID, 'deleted' => 0]);

		$this->processor->transactions = [
			(object) ['id' => 'tx_declined', 'status' => 'failed'],
		];

		$result = SubscriptionPaymentService::checkSubscription(
			Subscription::findOne(['id' => $id]),
			Invoice::findOne(['id' => $invoiceId])
		);

		$this->assertFalse($result);
		$this->assertEquals(Invoice::STATUS_UNPAID, $this->fetchColumn('invoice', $invoiceId, 'status'));
	}

	public function testNonRecurringSubscriptionIsNotCharged()
	{
		$id = $this->createSubscription([
			'end_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
		]);
		// metadata without recurring setup
		$this->insertRow('payment_metadata', [
			'subscription_id' => $id,
			'payment_method' => PaymentMetadata::PAYMENT_METHOD_CASH,
			'recurring_payment' => 0,
			'deleted' => 0,
		]);
		$invoiceId = $this->insertRow('invoice', ['status' => Invoice::STATUS_UNPAID, 'deleted' => 0]);
		$this->processor->transactions = [
			(object) ['id' => 'tx_1', 'status' => FakePaymentProcessor::TRANSACTION_STATUS_SUCCEEDED],
		];

		$result = SubscriptionPaymentService::checkSubscription(
			Subscription::findOne(['id' => $id]),
			Invoice::findOne(['id' => $invoiceId])
		);

		$this->assertFalse($result);
		$this->assertSame([], $this->processor->lastFilters, 'the processor must not be queried at all');
		$this->assertEquals(Invoice::STATUS_UNPAID, $this->fetchColumn('invoice', $invoiceId, 'status'));
	}
}
