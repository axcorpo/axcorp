<?php

namespace tests\unit;

use common\models\Invoice;
use common\models\Item;
use common\models\Subscription;
use console\controllers\PaymentController;
use tests\DatabaseTestCase;
use tests\support\FakeSettings;
use Yii;

/**
 * The subscription expiry pipeline (payment/run): due-subscription selection and
 * suspension after the allowed payment overdue period.
 */
class PaymentExpiryTest extends DatabaseTestCase
{
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
			],
		]));
	}

	protected function tearDown(): void
	{
		Yii::$app->clear('settings');
		parent::tearDown();
	}

	/**
	 * @return PaymentController
	 */
	private function controller()
	{
		return new PaymentController('payment', Yii::$app);
	}

	/**
	 * Calls a pipeline method on the service, with the clock pinned to now.
	 *
	 * @param string $method
	 * @param array $arguments
	 * @return mixed
	 */
	private function invoke($method, array $arguments = [])
	{
		\common\services\SubscriptionPaymentService::setCurrentDate();
		return \common\services\SubscriptionPaymentService::$method(...$arguments);
	}

	private function dueSubscriptionIds()
	{
		return array_map(function ($model) {
			return $model->id;
		}, $this->invoke('getDueSubscriptions'));
	}

	private function expiredSubscriptionIds()
	{
		return array_map(function ($model) {
			return $model->id;
		}, $this->invoke('getExpiredSubscriptions'));
	}

	/**
	 * Inserts a proforma with one subscription line, the way saveInvoice() issues it.
	 *
	 * @param string $subscriptionId
	 * @param string $dueAt
	 * @param array $invoiceAttributes
	 * @param array $itemAttributes
	 * @return string the invoice id
	 */
	private function insertProforma($subscriptionId, $dueAt, array $invoiceAttributes = [], array $itemAttributes = [])
	{
		$invoiceId = $this->insertRow('invoice', array_merge([
			'type' => Invoice::TYPE_PROFORMA,
			'status' => Invoice::STATUS_UNPAID,
			'due_at' => $dueAt,
			'deleted' => 0,
		], $invoiceAttributes));
		$this->insertRow('item', array_merge([
			'invoice_id' => $invoiceId,
			'item_id' => $subscriptionId,
			'type' => Item::TYPE_SUBSCRIPTION,
			'status' => Item::STATUS_ACTIVE,
			'deleted' => 0,
		], $itemAttributes));

		return $invoiceId;
	}

	public function testDueSelectionIncludesSubscriptionNearingItsEnd()
	{
		$id = $this->createSubscription(['end_at' => date('Y-m-d H:i:s', strtotime('+2 days'))]);

		$this->assertContains($id, $this->dueSubscriptionIds());
	}

	public function testDueSelectionIncludesExpiredSubscription()
	{
		$id = $this->createSubscription(['end_at' => date('Y-m-d H:i:s', strtotime('-10 days'))]);

		$this->assertContains($id, $this->dueSubscriptionIds());
	}

	public function testDueSelectionExcludesSubscriptionFarFromItsEnd()
	{
		$id = $this->createSubscription(['end_at' => date('Y-m-d H:i:s', strtotime('+20 days'))]);

		$this->assertNotContains($id, $this->dueSubscriptionIds());
	}

	public function testDueSelectionExcludesFreeSubscriptions()
	{
		$id = $this->createSubscription([
			'type' => Subscription::TYPE_FREE,
			'end_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
		]);

		$this->assertNotContains($id, $this->dueSubscriptionIds());
	}

	public function testDueSelectionExcludesCancelledSubscriptions()
	{
		$id = $this->createSubscription([
			'status' => Subscription::STATUS_CANCELLED,
			'end_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
		]);

		$this->assertNotContains($id, $this->dueSubscriptionIds());
	}

	public function testDueSelectionExcludesSuspendedSubscriptions()
	{
		$id = $this->createSubscription([
			'status' => Subscription::STATUS_SUSPENDED,
			'end_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
		]);

		$this->assertNotContains($id, $this->dueSubscriptionIds());
	}

	public function testDueSelectionExcludesDeletedSubscriptions()
	{
		$id = $this->createSubscription([
			'end_at' => date('Y-m-d H:i:s', strtotime('+2 days')),
			'deleted' => 1,
		]);

		$this->assertNotContains($id, $this->dueSubscriptionIds());
	}

	public function testDueSelectionExcludesSubscriptionWithItsCycleProforma()
	{
		$endAt = date('Y-m-d H:i:s', strtotime('+2 days'));
		$id = $this->createSubscription(['end_at' => $endAt]);
		$this->insertProforma($id, $endAt);

		$this->assertNotContains($id, $this->dueSubscriptionIds());
	}

	public function testDueSelectionIncludesSubscriptionWithProformaOfAnotherCycle()
	{
		// The previous cycle's proforma stays in the table (unpaid, by convention)
		// after the renewal moved end_at on — it must not hide the new cycle.
		$endAt = date('Y-m-d H:i:s', strtotime('+2 days'));
		$id = $this->createSubscription(['end_at' => $endAt]);
		$this->insertProforma($id, date('Y-m-d H:i:s', strtotime('-28 days')));

		$this->assertContains($id, $this->dueSubscriptionIds());
	}

	public function testDueSelectionIgnoresPaidFiscalInvoice()
	{
		// Only a proforma marks the cycle as issued; a fiscal invoice does not.
		$endAt = date('Y-m-d H:i:s', strtotime('+2 days'));
		$id = $this->createSubscription(['end_at' => $endAt]);
		$this->insertProforma($id, $endAt, ['type' => Invoice::TYPE_INVOICE, 'status' => Invoice::STATUS_PAID]);

		$this->assertContains($id, $this->dueSubscriptionIds());
	}

	public function testDueSelectionIgnoresDeletedProforma()
	{
		$endAt = date('Y-m-d H:i:s', strtotime('+2 days'));
		$id = $this->createSubscription(['end_at' => $endAt]);
		$this->insertProforma($id, $endAt, ['deleted' => 1]);

		$this->assertContains($id, $this->dueSubscriptionIds());
	}

	public function testDueSelectionIgnoresProformaWithDeletedOrInactiveLine()
	{
		$endAt = date('Y-m-d H:i:s', strtotime('+2 days'));
		$deletedLine = $this->createSubscription(['end_at' => $endAt]);
		$this->insertProforma($deletedLine, $endAt, [], ['deleted' => 1]);
		$inactiveLine = $this->createSubscription(['end_at' => $endAt]);
		$this->insertProforma($inactiveLine, $endAt, [], ['status' => Item::STATUS_INACTIVE]);

		$ids = $this->dueSubscriptionIds();
		$this->assertContains($deletedLine, $ids);
		$this->assertContains($inactiveLine, $ids);
	}

	public function testDueSelectionIgnoresProformaOfAnotherSubscription()
	{
		$endAt = date('Y-m-d H:i:s', strtotime('+2 days'));
		$id = $this->createSubscription(['end_at' => $endAt]);
		$other = $this->createSubscription(['end_at' => $endAt]);
		$this->insertProforma($other, $endAt);

		$ids = $this->dueSubscriptionIds();
		$this->assertContains($id, $ids);
		$this->assertNotContains($other, $ids);
	}

	public function testExpiredSelectionIncludesActiveSubscriptionPastItsEnd()
	{
		// With its proforma already issued: that is exactly the state at the due date.
		$endAt = date('Y-m-d H:i:s', strtotime('-1 day'));
		$id = $this->createSubscription(['end_at' => $endAt]);
		$this->insertProforma($id, $endAt);

		$this->assertContains($id, $this->expiredSubscriptionIds());
		$this->assertNotContains($id, $this->dueSubscriptionIds());
	}

	public function testExpiredSelectionExcludesSubscriptionBeforeItsEnd()
	{
		$id = $this->createSubscription(['end_at' => date('Y-m-d H:i:s', strtotime('+2 days'))]);

		$this->assertNotContains($id, $this->expiredSubscriptionIds());
	}

	public function testExpiredSelectionExcludesSuspendedCancelledLifetimeFreeAndDeleted()
	{
		$endAt = date('Y-m-d H:i:s', strtotime('-10 days'));
		$suspended = $this->createSubscription(['end_at' => $endAt, 'status' => Subscription::STATUS_SUSPENDED]);
		$cancelled = $this->createSubscription(['end_at' => $endAt, 'status' => Subscription::STATUS_CANCELLED]);
		// A free package without a cycle is lifetime: no end, never expires.
		$free = $this->createSubscription(['end_at' => null, 'billing_period' => null, 'billing_cycle' => null, 'type' => Subscription::TYPE_FREE]);
		$deleted = $this->createSubscription(['end_at' => $endAt, 'deleted' => 1]);

		$ids = $this->expiredSubscriptionIds();
		$this->assertNotContains($suspended, $ids);
		$this->assertNotContains($cancelled, $ids);
		$this->assertNotContains($free, $ids);
		$this->assertNotContains($deleted, $ids);
	}

	public function testExpiredSelectionIncludesFreeTermSubscriptionPastItsEnd()
	{
		// A free package with a cycle gives a term subscription: it expires like a paid
		// one (and is never billed, see testCheckSubscriptionSuspendsFreeTermWithoutBilling).
		$free = $this->createSubscription(['end_at' => date('Y-m-d H:i:s', strtotime('-1 day')), 'type' => Subscription::TYPE_FREE]);

		$this->assertContains($free, $this->expiredSubscriptionIds());
		$this->assertNotContains($free, $this->dueSubscriptionIds());
	}

	public function testCheckSubscriptionSuspendsFreeTermWithoutBilling()
	{
		$id = $this->createSubscription(['end_at' => date('Y-m-d H:i:s', strtotime('-1 day')), 'type' => Subscription::TYPE_FREE]);
		$subscription = Subscription::findOne(['id' => $id]);

		$this->assertTrue($this->invoke('checkSubscription', [$subscription, null]));

		$this->assertEquals(Subscription::STATUS_SUSPENDED, $this->fetchColumn('subscription', $id, 'status'));
		$this->assertSame(0, (int) (new \yii\db\Query())->from('invoice')->where(['subscriber_id' => $subscription->subscriber_id])->count('*', Yii::$app->db));
	}

	public function testFindCycleProformaReturnsTheProformaDueAtTheSubscriptionEnd()
	{
		$endAt = date('Y-m-d H:i:s', strtotime('-1 day'));
		$id = $this->createSubscription(['end_at' => $endAt]);
		$this->insertProforma($id, date('Y-m-d H:i:s', strtotime('-31 days')));
		$cycleProformaId = $this->insertProforma($id, $endAt);

		$invoice = $this->invoke('findCycleProforma', [Subscription::findOne(['id' => $id])]);

		$this->assertNotNull($invoice);
		$this->assertEquals($cycleProformaId, $invoice->id);
	}

	public function testFindCycleProformaReturnsNullWithoutOne()
	{
		$id = $this->createSubscription(['end_at' => date('Y-m-d H:i:s', strtotime('-1 day'))]);
		$this->insertProforma($id, date('Y-m-d H:i:s', strtotime('-31 days')));

		$this->assertNull($this->invoke('findCycleProforma', [Subscription::findOne(['id' => $id])]));
	}

	public function testSaveInvoiceReturnsEachSubscriptionsOwnCycleProforma()
	{
		// One subscriber, five subscriptions due at the same time: the lookup is per
		// subscription, so each gets its own proforma back, never a sibling's.
		$subscriberId = $this->insertRow('subscriber', ['deleted' => 0]);
		$endAt = date('Y-m-d H:i:s', strtotime('+2 days'));
		$subscriptions = [];
		$proformas = [];
		for ($i = 0; $i < 5; $i++) {
			$id = $this->createSubscription(['subscriber_id' => $subscriberId, 'end_at' => $endAt]);
			$subscriptions[] = $id;
			$proformas[] = $this->insertProforma($id, $endAt, ['subscriber_id' => $subscriberId]);
		}

		foreach ($subscriptions as $index => $id) {
			$invoice = $this->invoke('saveInvoice', [Subscription::findOne(['id' => $id])]);

			$this->assertNotNull($invoice);
			$this->assertEquals($proformas[$index], $invoice->id);
		}
	}

	public function testSaveInvoiceDoesNotReuseAnOldCycleProformaWhenASiblingHasANewerOne()
	{
		// The subscriber-level lookup used to return S2's previous-cycle proforma as
		// soon as S1 got its new one (newest unpaid invoice newer than newest paid).
		$subscriberId = $this->insertRow('subscriber', ['deleted' => 0]);
		$endAt = date('Y-m-d H:i:s', strtotime('+2 days'));
		$s1 = $this->createSubscription(['subscriber_id' => $subscriberId, 'end_at' => $endAt]);
		$s2 = $this->createSubscription(['subscriber_id' => $subscriberId, 'end_at' => $endAt]);
		// Last cycle, settled: old proformas (unpaid by convention) + paid fiscal invoices
		$oldS2Proforma = $this->insertProforma($s2, date('Y-m-d H:i:s', strtotime('-28 days')), ['subscriber_id' => $subscriberId]);
		$this->insertProforma($s2, date('Y-m-d H:i:s', strtotime('-28 days')), ['subscriber_id' => $subscriberId, 'type' => Invoice::TYPE_INVOICE, 'status' => Invoice::STATUS_PAID]);
		// This cycle: S1 already has its proforma, S2 not yet
		$s1Proforma = $this->insertProforma($s1, $endAt, ['subscriber_id' => $subscriberId]);

		$invoice = $this->invoke('saveInvoice', [Subscription::findOne(['id' => $s2])]);

		// Nothing to reuse, so it goes on to issue a new one (which this schema-only
		// database cannot complete) — never S1's proforma or S2's old-cycle one.
		if ($invoice !== null) {
			$this->assertNotEquals($s1Proforma, $invoice->id);
			$this->assertNotEquals($oldS2Proforma, $invoice->id);
			$this->assertEquals($endAt, $invoice->due_at);
		} else {
			$this->assertNull($invoice);
		}
		$this->assertContains($s2, $this->dueSubscriptionIds());
		$this->assertNotContains($s1, $this->dueSubscriptionIds());
	}

	public function testCheckSubscriptionSuspendsPastTheOverduePeriod()
	{
		// end_at 10 days ago + 5 allowed overdue days -> well past the limit
		$id = $this->createSubscription(['end_at' => date('Y-m-d H:i:s', strtotime('-10 days'))]);
		$invoiceId = $this->insertRow('invoice', ['status' => Invoice::STATUS_UNPAID, 'deleted' => 0]);

		$result = $this->invoke('checkSubscription', [
			Subscription::findOne(['id' => $id]),
			Invoice::findOne(['id' => $invoiceId]),
		]);

		$this->assertTrue($result);
		$this->assertEquals(Subscription::STATUS_SUSPENDED, $this->fetchColumn('subscription', $id, 'status'));
	}

	public function testCheckSubscriptionKeepsActiveWithinTheOverduePeriod()
	{
		// end_at 2 days ago + 5 allowed overdue days -> still inside the grace window;
		// without recurring payment metadata the check fails but must not suspend.
		$id = $this->createSubscription(['end_at' => date('Y-m-d H:i:s', strtotime('-2 days'))]);
		$invoiceId = $this->insertRow('invoice', ['status' => Invoice::STATUS_UNPAID, 'deleted' => 0]);

		$result = $this->invoke('checkSubscription', [
			Subscription::findOne(['id' => $id]),
			Invoice::findOne(['id' => $invoiceId]),
		]);

		$this->assertFalse($result);
		$this->assertEquals(Subscription::STATUS_ACTIVE, $this->fetchColumn('subscription', $id, 'status'));
	}

	public function testRunLeavesExpiredSubscriptionActiveWhenInvoiceCannotBeIssued()
	{
		// Documents the current pipeline behavior: when the invoice cannot be generated
		// (no document series/templates in this schema-only database), actionRun skips
		// checkSubscription entirely, so even a long-expired subscription stays active.
		$id = $this->createSubscription(['end_at' => date('Y-m-d H:i:s', strtotime('-10 days'))]);

		ob_start();
		try {
			$this->controller()->runAction('run');
		} finally {
			ob_end_clean();
		}

		$this->assertEquals(Subscription::STATUS_ACTIVE, $this->fetchColumn('subscription', $id, 'status'));
	}
}
