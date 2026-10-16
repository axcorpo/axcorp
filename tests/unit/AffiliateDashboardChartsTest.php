<?php

namespace tests\unit;

use common\helpers\ChartHelper;
use common\models\Affiliate;
use common\models\AffiliateCommission;
use common\models\Invoice;
use common\models\Subscriber;
use common\models\User;
use DateTimeImmutable;
use frontend\modules\account\models\AffiliateDashboard;
use tests\DatabaseTestCase;
use Yii;

/**
 * The chart series of the affiliate dashboard (12 months with the empty ones, scoped to
 * the affiliate, one series per currency, cancelled out, storno netted, a conversion
 * counted once) and the fixed status colours of ChartHelper.
 */
class AffiliateDashboardChartsTest extends DatabaseTestCase
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
		$this->affiliate = $this->insertAffiliate('CHART001');
		$this->other = $this->insertAffiliate('CHART002');
	}

	/**
	 * @param string $code
	 * @return Affiliate
	 */
	private function insertAffiliate($code)
	{
		$userId = $this->insertRow('user', [
			'email' => strtolower($code) . '@example.test',
			'status' => User::STATUS_ACTIVE,
			'deleted' => User::NO,
		]);
		$id = $this->insertRow('affiliate', [
			'user_id' => $userId,
			'code' => $code,
			'commission_percentage' => 10,
			'status' => Affiliate::STATUS_ACTIVE,
			'deleted' => Affiliate::NO,
		]);
		return Affiliate::findOne(['id' => $id]);
	}

	/**
	 * The dashboard with "now" pinned to 15 October 2026: the window is Nov 2025 – Oct 2026.
	 *
	 * @param Affiliate|null $affiliate
	 * @return AffiliateDashboard
	 */
	private function dashboard(?Affiliate $affiliate = null)
	{
		return new AffiliateDashboard([
			'affiliate' => $affiliate ?: $this->affiliate,
			'now' => new DateTimeImmutable('2026-10-15 09:00:00'),
		]);
	}

	/**
	 * @param Affiliate $affiliate
	 * @param string $createdAt
	 * @param array $attributes
	 * @return string id bytes
	 */
	private function insertSubscriber(Affiliate $affiliate, $createdAt, array $attributes = [])
	{
		$userId = $this->insertRow('user', ['status' => User::STATUS_ACTIVE, 'deleted' => User::NO]);
		return $this->insertRow('subscriber', $attributes + [
			'user_id' => $userId,
			'affiliate_id' => $affiliate->getAttribute('id'),
			'status' => Subscriber::STATUS_ACTIVE,
			'deleted' => Subscriber::NO,
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
		$type = (float) $amount < 0 ? Invoice::TYPE_RECTIFY : Invoice::TYPE_INVOICE;
		$this->insertRow('affiliate_commission', $attributes + [
			'affiliate_id' => $affiliate->getAttribute('id'),
			'invoice_id' => $this->insertRow('invoice', [
				'subscriber_id' => $attributes['subscriber_id'] ?? null,
				'type' => $type,
				'status' => Invoice::STATUS_PAID,
				'deleted' => Invoice::NO,
			]),
			'type' => $type,
			'base_amount' => (float) $amount * 10,
			'currency' => 'RON',
			'percentage' => 10,
			'amount' => $amount,
			'status' => $status,
			'earned_at' => $earnedAt,
		]);
	}

	public function testTheWindowIsTwelveCalendarMonthsEndingWithTheCurrentOne()
	{
		$dashboard = $this->dashboard();

		$months = $dashboard->getChartMonths();
		$this->assertCount(12, $months);
		$this->assertSame('2025-11', $months[0]);
		$this->assertSame('2026-10', $months[11]);
		$this->assertCount(12, $dashboard->getChartMonthLabels());
	}

	public function testTheMonthLabelsFollowTheApplicationLanguage()
	{
		$language = Yii::$app->language;
		Yii::$app->language = 'ro-RO';
		try {
			$dashboard = $this->dashboard();
			$this->assertSame('oct. 2026', $dashboard->getChartMonthLabels()[11]);
			$this->assertSame('octombrie 2026', $dashboard->getChartMonthTitles()[11]);
		} finally {
			Yii::$app->language = $language;
		}
	}

	public function testSignupsFillEmptyMonthsAndStayScopedToTheAffiliate()
	{
		$this->insertSubscriber($this->affiliate, '2026-10-02 10:00:00');
		$this->insertSubscriber($this->affiliate, '2026-10-20 10:00:00');
		$this->insertSubscriber($this->affiliate, '2025-11-01 00:00:00');
		$this->insertSubscriber($this->affiliate, '2025-10-31 23:59:59'); // before the window
		$this->insertSubscriber($this->affiliate, '2026-05-05 10:00:00', ['deleted' => 1]);
		$this->insertSubscriber($this->other, '2026-10-03 10:00:00');

		$signups = $this->dashboard()->getMonthlySignups()['signups'];

		$this->assertSame([1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2], $signups);
	}

	public function testAConversionIsCountedOnceInTheFirstPaidMonth()
	{
		$early = $this->insertSubscriber($this->affiliate, '2026-01-10 10:00:00');
		$late = $this->insertSubscriber($this->affiliate, '2026-02-10 10:00:00');
		$before = $this->insertSubscriber($this->affiliate, '2025-06-10 10:00:00');
		$voided = $this->insertSubscriber($this->affiliate, '2026-03-10 10:00:00');
		$theirs = $this->insertSubscriber($this->other, '2026-03-10 10:00:00');

		// $early pays in March, again in April and May; one storno in June.
		foreach (['2026-03-05 10:00:00', '2026-04-05 10:00:00', '2026-05-05 10:00:00'] as $date) {
			$this->insertCommission($this->affiliate, '5.00', AffiliateCommission::STATUS_PAID, $date, ['subscriber_id' => $early]);
		}
		$this->insertCommission($this->affiliate, '-5.00', AffiliateCommission::STATUS_PENDING, '2026-06-05 10:00:00', ['subscriber_id' => $early]);
		// $late's first paid invoice is in September.
		$this->insertCommission($this->affiliate, '5.00', AffiliateCommission::STATUS_PENDING, '2026-09-05 10:00:00', ['subscriber_id' => $late]);
		// $before first paid before the window: not a conversion of the window.
		$this->insertCommission($this->affiliate, '5.00', AffiliateCommission::STATUS_PAID, '2025-07-05 10:00:00', ['subscriber_id' => $before]);
		$this->insertCommission($this->affiliate, '5.00', AffiliateCommission::STATUS_PAID, '2026-02-05 10:00:00', ['subscriber_id' => $before]);
		// A cancelled commission does not convert; a storno alone does not either.
		$this->insertCommission($this->affiliate, '5.00', AffiliateCommission::STATUS_CANCELLED, '2026-04-05 10:00:00', ['subscriber_id' => $voided]);
		$this->insertCommission($this->affiliate, '-5.00', AffiliateCommission::STATUS_PENDING, '2026-07-05 10:00:00', ['subscriber_id' => $voided]);
		$this->insertCommission($this->other, '5.00', AffiliateCommission::STATUS_PAID, '2026-04-05 10:00:00', ['subscriber_id' => $theirs]);

		$conversions = $this->dashboard()->getMonthlySignups()['conversions'];

		// Nov 2025 … Oct 2026: March (index 4) and September (index 10).
		$this->assertSame([0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1, 0], $conversions);
	}

	public function testCommissionsAreSplitPerCurrencyAndStatusWithoutCancelled()
	{
		$this->insertCommission($this->affiliate, '10.00', AffiliateCommission::STATUS_PAID, '2026-10-01 10:00:00');
		$this->insertCommission($this->affiliate, '2.50', AffiliateCommission::STATUS_PAID, '2026-10-09 10:00:00');
		$this->insertCommission($this->affiliate, '4.00', AffiliateCommission::STATUS_PAYABLE, '2026-09-09 10:00:00');
		$this->insertCommission($this->affiliate, '3.00', AffiliateCommission::STATUS_PENDING, '2026-09-19 10:00:00');
		$this->insertCommission($this->affiliate, '99.00', AffiliateCommission::STATUS_CANCELLED, '2026-09-19 10:00:00');
		$this->insertCommission($this->affiliate, '7.00', AffiliateCommission::STATUS_PAID, '2026-02-01 10:00:00', ['currency' => 'EUR']);
		$this->insertCommission($this->affiliate, '50.00', AffiliateCommission::STATUS_PAID, '2025-10-01 10:00:00'); // before the window
		$this->insertCommission($this->other, '500.00', AffiliateCommission::STATUS_PAID, '2026-10-01 10:00:00');

		$series = $this->dashboard()->getMonthlyCommissions();

		// Largest total first: RON (19.50) before EUR (7.00) — RON is the default.
		$this->assertSame(['RON', 'EUR'], array_keys($series));
		$this->assertSame([AffiliateCommission::STATUS_PAID, AffiliateCommission::STATUS_PAYABLE, AffiliateCommission::STATUS_PENDING], array_keys($series['RON']));
		$this->assertSame(12.5, $series['RON'][AffiliateCommission::STATUS_PAID][11]);
		$this->assertSame(4.0, $series['RON'][AffiliateCommission::STATUS_PAYABLE][10]);
		$this->assertSame(3.0, $series['RON'][AffiliateCommission::STATUS_PENDING][10]);
		$this->assertEquals(19.5, array_sum(array_map('array_sum', $series['RON'])));
		$this->assertEquals(7.0, array_sum($series['EUR'][AffiliateCommission::STATUS_PAID]));
		$this->assertEquals(7.0, $series['EUR'][AffiliateCommission::STATUS_PAID][3]);
		$this->assertCount(12, $series['EUR'][AffiliateCommission::STATUS_PENDING]);
	}

	public function testAStornoNetsIntoItsMonth()
	{
		$this->insertCommission($this->affiliate, '10.00', AffiliateCommission::STATUS_PAYABLE, '2026-08-01 10:00:00');
		$this->insertCommission($this->affiliate, '6.00', AffiliateCommission::STATUS_PENDING, '2026-09-01 10:00:00');
		$this->insertCommission($this->affiliate, '-6.00', AffiliateCommission::STATUS_PENDING, '2026-09-20 10:00:00');
		$this->insertCommission($this->affiliate, '-10.00', AffiliateCommission::STATUS_PENDING, '2026-10-02 10:00:00');

		$series = $this->dashboard()->getMonthlyCommissions()['RON'];

		$this->assertSame(10.0, $series[AffiliateCommission::STATUS_PAYABLE][9]);
		$this->assertSame(0.0, $series[AffiliateCommission::STATUS_PENDING][10]);
		$this->assertSame(-10.0, $series[AffiliateCommission::STATUS_PENDING][11]);
	}

	public function testWithoutDataTheSeriesAreEmpty()
	{
		$dashboard = $this->dashboard();

		$this->assertSame([], $dashboard->getMonthlyCommissions());
		$this->assertFalse(ChartHelper::hasData(array_values($dashboard->getMonthlySignups())));
	}

	public function testTheStatusColoursAreFixed()
	{
		$this->assertSame('#2a78d6', ChartHelper::statusColor(AffiliateCommission::STATUS_PAID));
		$this->assertSame('#eb6834', ChartHelper::statusColor(AffiliateCommission::STATUS_PAYABLE));
		$this->assertSame('#1baf7a', ChartHelper::statusColor(AffiliateCommission::STATUS_PENDING));
		$this->assertNull(ChartHelper::statusColor(AffiliateCommission::STATUS_CANCELLED));
		$this->assertSame(['#2a78d6', '#eb6834', '#1baf7a'], ChartHelper::colors());
	}

	public function testThePaletteIsNeverCycled()
	{
		$this->assertSame('#1baf7a', ChartHelper::color(3));
		$this->expectException(\InvalidArgumentException::class);
		ChartHelper::color(4);
	}

	public function testTheConfigsFollowTheChartRules()
	{
		$single = ChartHelper::bar(['a', 'b'], [['label' => 'Signups', 'data' => [1, 2], 'color' => ChartHelper::BLUE]], ['horizontal' => true]);
		$this->assertSame('roundedHorizontalBar', $single['type']);
		$this->assertFalse($single['options']['legend']['display']);
		$this->assertSame(0, $single['options']['scales']['xAxes'][0]['ticks']['precision']);
		$this->assertTrue($single['options']['scales']['xAxes'][0]['ticks']['beginAtZero']);
		$this->assertCount(1, $single['options']['scales']['yAxes']);

		$stacked = ChartHelper::bar(['a'], [
			['label' => 'Paid', 'data' => [1], 'color' => ChartHelper::BLUE],
			['label' => 'Payable', 'data' => [1], 'color' => ChartHelper::ORANGE],
		], ['stacked' => true, 'format' => ChartHelper::FORMAT_CURRENCY, 'currency' => 'RON', 'locale' => 'ro-RO', 'totalLabel' => 'Total']);
		$this->assertTrue($stacked['options']['legend']['display']);
		$this->assertSame('start', $stacked['options']['legend']['align']);
		$this->assertSame(['mode' => 'index', 'intersect' => false], array_intersect_key($stacked['options']['tooltips'], ['mode' => 1, 'intersect' => 1]));
		// Amounts carry the app's own currency word ("LEI" for RON), not Intl's "RON".
		$this->assertStringContainsString('"LEI"', (string) $stacked['options']['tooltips']['callbacks']['label']);
		$this->assertArrayHasKey('footer', $stacked['options']['tooltips']['callbacks']);
		$this->assertFalse($stacked['options']['scales']['xAxes'][0]['gridLines']['display']);

		$this->expectException(\InvalidArgumentException::class);
		ChartHelper::bar(['a'], [], ['format' => ChartHelper::FORMAT_CURRENCY]);
	}
}
