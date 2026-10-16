<?php

namespace tests\unit;

use common\models\Affiliate;
use common\models\AffiliateCommission;
use common\models\User;
use common\models\Invoice;
use common\models\Subscription;
use common\services\AffiliateStatsService;
use tests\DatabaseTestCase;
use yii\base\InvalidConfigException;

/**
 * The shared affiliate figures (frontend dashboard + admin reports): the affiliate and
 * period scopes over referred master subscribers and their commissions, every month of the period, currencies never mixed,
 * cancelled out, storno netted, a conversion counted once, and the conversion steps.
 */
class AffiliateStatsServiceTest extends DatabaseTestCase
{
	/**
	 * @var Affiliate
	 */
	private $affiliate;

	/**
	 * @var Affiliate
	 */
	private $other;

	protected function setUp(): void
	{
		parent::setUp();
		$this->affiliate = $this->insertAffiliate('STATS001', 'Ana', 'Pop');
		$this->other = $this->insertAffiliate('STATS002', 'Ion', 'Ene');
	}

	/**
	 * @param string $code
	 * @param string $firstName
	 * @param string $lastName
	 * @param int $status
	 * @return Affiliate
	 */
	private function insertAffiliate($code, $firstName, $lastName, $status = Affiliate::STATUS_ACTIVE)
	{
		$userId = $this->insertRow('user', [
			'email' => strtolower($code) . '@example.test',
			'first_name' => $firstName,
			'last_name' => $lastName,
			'status' => User::STATUS_ACTIVE,
			'deleted' => User::NO,
		]);
		$id = $this->insertRow('affiliate', [
			'user_id' => $userId,
			'code' => $code,
			'commission_percentage' => 10,
			'status' => $status,
			'deleted' => Affiliate::NO,
		]);
		return Affiliate::findOne(['id' => $id]);
	}

	/**
	 * Jan – Jun 2026 (the end is exclusive), optionally scoped.
	 *
	 * @param array $config
	 * @return AffiliateStatsService
	 */
	private function stats(array $config = [])
	{
		return new AffiliateStatsService($config + ['from' => '2026-01-01 00:00:00', 'to' => '2026-07-01 00:00:00']);
	}

	/**
	 * A master subscriber referred by the affiliate.
	 *
	 * @param Affiliate $affiliate
	 * @param string $createdAt
	 * @param array $attributes
	 * @return string id bytes
	 */
	private function insertSubscriber(Affiliate $affiliate, $createdAt, array $attributes = [])
	{
		return $this->insertRow('subscriber', $attributes + [
			'affiliate_id' => $affiliate->getAttribute('id'),
			'status' => 1,
			'deleted' => 0,
			'created_at' => $createdAt,
		]);
	}

	/**
	 * @param Affiliate $affiliate
	 * @param string $amount
	 * @param int $status
	 * @param string $earnedAt
	 * @param array $attributes
	 */
	private function insertCommission(Affiliate $affiliate, $amount, $status, $earnedAt, array $attributes = [])
	{
		$this->insertRow('affiliate_commission', $attributes + [
			'affiliate_id' => $affiliate->getAttribute('id'),
			'invoice_id' => $this->uuidBytes(),
			'type' => (float) $amount < 0 ? Invoice::TYPE_RECTIFY : Invoice::TYPE_INVOICE,
			'base_amount' => (float) $amount * 10,
			'currency' => 'RON',
			'percentage' => 10,
			'amount' => $amount,
			'status' => $status,
			'earned_at' => $earnedAt,
		]);
	}

	public function testTheMonthsCoverThePeriodEmptyOnesIncluded()
	{
		$this->assertSame(['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06'], $this->stats()->getMonths());
		// An inclusive "to" day (the admin filters) keeps its month; a partial first month counts.
		$stats = new AffiliateStatsService(['from' => '2025-11-20 00:00:00', 'to' => '2026-01-01 00:00:01']);
		$this->assertSame(['2025-11', '2025-12', '2026-01'], $stats->getMonths());
		$this->assertSame([], (new AffiliateStatsService(['from' => '2026-05-01', 'to' => '2026-01-01']))->getMonths());
	}

	public function testMonthlyFiguresNeedAPeriod()
	{
		$this->expectException(InvalidConfigException::class);
		(new AffiliateStatsService())->getMonthlySignups();
	}

	public function testSignupsAreFilteredByAffiliateAndPeriod()
	{
		$this->insertSubscriber($this->affiliate, '2026-01-05 10:00:00');
		$this->insertSubscriber($this->affiliate, '2026-03-05 10:00:00');
		$this->insertSubscriber($this->affiliate, '2026-03-06 10:00:00');
		$this->insertSubscriber($this->affiliate, '2025-12-31 23:59:59'); // before
		$this->insertSubscriber($this->affiliate, '2026-07-01 00:00:00'); // after (exclusive end)
		$this->insertSubscriber($this->affiliate, '2026-02-01 10:00:00', ['deleted' => 1]);
		$this->insertSubscriber($this->other, '2026-06-30 23:00:00');
		// Not referred: never an affiliate signup.
		$this->insertRow('subscriber', ['status' => 1, 'deleted' => 0, 'created_at' => '2026-02-02 10:00:00']);

		$this->assertSame([1, 0, 2, 0, 0, 1], $this->stats()->getMonthlySignups()['signups']);
		$this->assertSame([1, 0, 2, 0, 0, 0], $this->stats(['affiliateId' => $this->affiliate->getAttribute('id')])->getMonthlySignups()['signups']);
		$this->assertSame([0, 0, 0, 0, 0, 1], $this->stats(['affiliateId' => $this->other->getAttribute('id')])->getMonthlySignups()['signups']);
		$both = [$this->affiliate->getAttribute('id'), $this->other->getAttribute('id')];
		$this->assertSame(4, $this->stats(['affiliateId' => $both])->getSignupCount());
		// Without a period: all time, the deleted one still out.
		$this->assertSame(6, (new AffiliateStatsService())->getSignupCount());
	}

	public function testAConversionIsCountedOnceInItsFirstPaidMonth()
	{
		$early = $this->insertSubscriber($this->affiliate, '2026-01-10 10:00:00');
		$before = $this->insertSubscriber($this->affiliate, '2025-06-10 10:00:00');
		$voided = $this->insertSubscriber($this->affiliate, '2026-02-10 10:00:00');
		foreach (['2026-02-05 10:00:00', '2026-03-05 10:00:00'] as $date) {
			$this->insertCommission($this->affiliate, '5.00', AffiliateCommission::STATUS_PAID, $date, ['subscriber_id' => $early]);
		}
		$this->insertCommission($this->affiliate, '5.00', AffiliateCommission::STATUS_PAID, '2025-07-05 10:00:00', ['subscriber_id' => $before]);
		$this->insertCommission($this->affiliate, '5.00', AffiliateCommission::STATUS_PAID, '2026-04-05 10:00:00', ['subscriber_id' => $before]);
		$this->insertCommission($this->affiliate, '5.00', AffiliateCommission::STATUS_CANCELLED, '2026-03-05 10:00:00', ['subscriber_id' => $voided]);
		$this->insertCommission($this->affiliate, '-5.00', AffiliateCommission::STATUS_PENDING, '2026-04-05 10:00:00', ['subscriber_id' => $voided]);

		$this->assertSame([0, 1, 0, 0, 0, 0], $this->stats()->getMonthlySignups()['conversions']);
		// Commissions of another affiliate do not convert here.
		$this->assertSame([0, 0, 0, 0, 0, 0], $this->stats(['affiliateId' => $this->other->getAttribute('id')])->getMonthlySignups()['conversions']);
	}

	public function testCommissionsSeparateCurrenciesNetStornoAndLeaveCancelledOut()
	{
		$this->insertCommission($this->affiliate, '10.00', AffiliateCommission::STATUS_PAID, '2026-01-10 10:00:00');
		$this->insertCommission($this->affiliate, '4.00', AffiliateCommission::STATUS_PAYABLE, '2026-02-10 10:00:00');
		$this->insertCommission($this->affiliate, '6.00', AffiliateCommission::STATUS_PENDING, '2026-06-01 10:00:00');
		$this->insertCommission($this->affiliate, '-2.00', AffiliateCommission::STATUS_PENDING, '2026-06-20 10:00:00');
		$this->insertCommission($this->affiliate, '99.00', AffiliateCommission::STATUS_CANCELLED, '2026-02-10 10:00:00');
		$this->insertCommission($this->affiliate, '3.00', AffiliateCommission::STATUS_PAID, '2026-03-10 10:00:00', ['currency' => 'EUR']);
		$this->insertCommission($this->other, '50.00', AffiliateCommission::STATUS_PAID, '2026-03-10 10:00:00');
		$this->insertCommission($this->affiliate, '70.00', AffiliateCommission::STATUS_PAID, '2026-07-01 00:00:00'); // after

		$series = $this->stats()->getMonthlyCommissions();
		$this->assertSame(['RON', 'EUR'], array_keys($series));
		$this->assertSame([10.0, 0.0, 50.0, 0.0, 0.0, 0.0], $series['RON'][AffiliateCommission::STATUS_PAID]);
		$this->assertSame([0.0, 4.0, 0.0, 0.0, 0.0, 0.0], $series['RON'][AffiliateCommission::STATUS_PAYABLE]);
		$this->assertSame([0.0, 0.0, 0.0, 0.0, 0.0, 4.0], $series['RON'][AffiliateCommission::STATUS_PENDING]);
		$this->assertSame([0.0, 0.0, 3.0, 0.0, 0.0, 0.0], $series['EUR'][AffiliateCommission::STATUS_PAID]);

		$mine = $this->stats(['affiliateId' => $this->affiliate->getAttribute('id')]);
		$this->assertSame(10.0, $mine->getMonthlyCommissions()['RON'][AffiliateCommission::STATUS_PAID][0]);
		$this->assertSame(0.0, $mine->getMonthlyCommissions()['RON'][AffiliateCommission::STATUS_PAID][2]);
		$this->assertSame([AffiliateCommission::STATUS_PAID => 10.0, AffiliateCommission::STATUS_PAYABLE => 4.0, AffiliateCommission::STATUS_PENDING => 4.0], $mine->getCommissionTotals()['RON']);
		// Revenue = commission bases: 100 + 40 + 60 - 20 (storno), the cancelled 990 out.
		$this->assertEquals(180.0, (float) $mine->getRevenueTotals()['RON']);
		$this->assertEquals(30.0, (float) $mine->getRevenueTotals()['EUR']);
		$this->assertSame(['RON', 'EUR'], $mine->getCurrencies());
	}

	public function testAffiliatesAreRankedByNetRevenueInOneCurrency()
	{
		$third = $this->insertAffiliate('STATS003', 'Dan', 'Ilie');
		$this->insertCommission($this->affiliate, '10.00', AffiliateCommission::STATUS_PAID, '2026-01-10 10:00:00');
		$this->insertCommission($this->other, '30.00', AffiliateCommission::STATUS_PAID, '2026-01-10 10:00:00');
		$this->insertCommission($this->other, '-5.00', AffiliateCommission::STATUS_PENDING, '2026-02-10 10:00:00');
		$this->insertCommission($third, '2.00', AffiliateCommission::STATUS_PENDING, '2026-02-10 10:00:00');
		$this->insertCommission($third, '1000.00', AffiliateCommission::STATUS_PAID, '2026-02-10 10:00:00', ['currency' => 'EUR']);
		$this->insertCommission($third, '1000.00', AffiliateCommission::STATUS_CANCELLED, '2026-02-10 10:00:00');

		$rows = $this->stats()->getAffiliateRevenue('RON');
		$this->assertSame(['Ion Ene (STATS002)', 'Ana Pop (STATS001)', 'Dan Ilie (STATS003)'], array_column($rows, 'label'));
		$this->assertSame([250.0, 100.0, 20.0], array_column($rows, 'amount'));
		$this->assertSame($this->other->uuid, $rows[0]['uuid']);

		$folded = $this->stats(['limit' => 1])->getAffiliateRevenue('RON');
		$this->assertCount(2, $folded);
		$this->assertTrue($folded[1]['other']);
		$this->assertSame(120.0, $folded[1]['amount']);
		$this->assertNull($folded[1]['uuid']);
	}

	public function testTheConversionStepsAreSubsetsOfTheSignups()
	{
		$paidActive = $this->insertSubscriber($this->affiliate, '2026-01-10 10:00:00');
		$paidCancelled = $this->insertSubscriber($this->affiliate, '2026-02-10 10:00:00');
		$freeActive = $this->insertSubscriber($this->affiliate, '2026-03-10 10:00:00');
		$this->insertSubscriber($this->affiliate, '2026-04-10 10:00:00'); // nothing
		$old = $this->insertSubscriber($this->affiliate, '2025-04-10 10:00:00'); // before the period

		foreach ([$paidActive, $freeActive, $old] as $subscriber) {
			$this->insertRow('subscription', ['subscriber_id' => $subscriber, 'status' => Subscription::STATUS_ACTIVE, 'deleted' => 0]);
		}
		// Two active subscriptions still make one subscriber; a deleted one is none.
		$this->insertRow('subscription', ['subscriber_id' => $paidActive, 'status' => Subscription::STATUS_ACTIVE, 'deleted' => 0]);
		$this->insertRow('subscription', ['subscriber_id' => $paidCancelled, 'status' => Subscription::STATUS_CANCELLED, 'deleted' => 0]);
		$this->insertRow('subscription', ['subscriber_id' => $paidCancelled, 'status' => Subscription::STATUS_ACTIVE, 'deleted' => 1]);
		// Paid: twice, or once (whenever — even after the period); a storno alone is not paying.
		$this->insertCommission($this->affiliate, '5.00', AffiliateCommission::STATUS_PAID, '2026-01-15 10:00:00', ['subscriber_id' => $paidActive]);
		$this->insertCommission($this->affiliate, '5.00', AffiliateCommission::STATUS_PAID, '2026-02-15 10:00:00', ['subscriber_id' => $paidActive]);
		$this->insertCommission($this->affiliate, '5.00', AffiliateCommission::STATUS_PENDING, '2026-09-15 10:00:00', ['subscriber_id' => $paidCancelled]);
		$this->insertCommission($this->affiliate, '-5.00', AffiliateCommission::STATUS_PENDING, '2026-03-15 10:00:00', ['subscriber_id' => $freeActive]);
		$this->insertCommission($this->affiliate, '5.00', AffiliateCommission::STATUS_PAID, '2025-05-15 10:00:00', ['subscriber_id' => $old]);

		$cohort = $this->stats()->getCohort();
		$this->assertSame(['signups' => 4, 'active' => 2, 'paid' => 2], $cohort);
		$this->assertLessThanOrEqual($cohort['signups'], $cohort['paid']);
		$this->assertLessThanOrEqual($cohort['signups'], $cohort['active']);
		$this->assertSame(3, $this->stats()->getActiveSubscriptionCount());

		$this->assertSame(['signups' => 0, 'active' => 0, 'paid' => 0], $this->stats(['affiliateId' => $this->other->getAttribute('id')])->getCohort());
	}

	public function testActiveAffiliatesFollowTheScope()
	{
		$this->insertAffiliate('STATS003', 'Dan', 'Ilie', Affiliate::STATUS_INACTIVE);
		$this->insertSubscriber($this->other, '2026-01-10 10:00:00');

		$this->assertSame(2, $this->stats()->getActiveAffiliateCount());
		$this->assertSame(1, $this->stats(['affiliateId' => $this->affiliate->getAttribute('id')])->getActiveAffiliateCount());
		$both = [$this->affiliate->getAttribute('id'), $this->other->getAttribute('id')];
		$this->assertSame(2, $this->stats(['affiliateId' => $both])->getActiveAffiliateCount());
	}
}
