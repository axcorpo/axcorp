<?php

namespace tests\unit;

use common\models\Subscription;
use tests\DatabaseTestCase;

/**
 * Subscription::activate() on a free subscription: lifetime without a billing cycle,
 * a term (start + cycle, never renewed) when its package carries one.
 */
class SubscriptionFreeTermTest extends DatabaseTestCase
{
	public function testActivateLifetimeFreeSubscriptionIsOpenEnded()
	{
		// Free package without a cycle: the subscription never ends.
		$id = $this->createSubscription([
			'type' => Subscription::TYPE_FREE,
			'billing_period' => null,
			'billing_cycle' => null,
			'end_at' => date('Y-m-d H:i:s', strtotime('+5 days')),
		]);
		$subscription = Subscription::findOne(['id' => $id]);

		$this->assertTrue($subscription->activate('2026-07-01 00:00:00'));

		$this->assertSame('2026-07-01 00:00:00', $this->fetchColumn('subscription', $id, 'start_at'));
		$this->assertNull($this->fetchColumn('subscription', $id, 'end_at'));
	}

	public function testActivateFreeSubscriptionWithCycleEndsAfterTheTerm()
	{
		// Free package with a cycle: a term subscription, start + cycle, like a paid one.
		$id = $this->createSubscription([
			'type' => Subscription::TYPE_FREE,
			'billing_period' => 12,
			'billing_cycle' => 'month',
		]);
		$subscription = Subscription::findOne(['id' => $id]);

		$this->assertTrue($subscription->activate('2026-07-01 00:00:00'));

		$this->assertSame('2026-07-01 00:00:00', $this->fetchColumn('subscription', $id, 'start_at'));
		$this->assertSame('2027-07-01 00:00:00', $this->fetchColumn('subscription', $id, 'end_at'));
	}

	public function testHasBillingCycleOnlyWithPeriodAndCycle()
	{
		$subscription = new Subscription(['type' => Subscription::TYPE_FREE, 'billing_period' => 1, 'billing_cycle' => 'year']);
		$this->assertTrue($subscription->getHasBillingCycle());

		$subscription = new Subscription(['type' => Subscription::TYPE_FREE, 'billing_period' => null, 'billing_cycle' => null]);
		$this->assertFalse($subscription->getHasBillingCycle());
	}
}
