<?php

namespace frontend\modules\account\models;

use common\models\Affiliate;
use common\services\AffiliateStatsService;
use DateTimeImmutable;
use yii\base\BaseObject;

/**
 * The figures of the affiliate dashboard, all scoped to one affiliate.
 *
 * The counting itself lives in common\services\AffiliateStatsService, shared with the
 * admin reports, so the affiliate and the admin always see the same numbers.
 * Amounts are per currency and never converted (decision: totals per currency).
 */
class AffiliateDashboard extends BaseObject
{
	/**
	 * @var Affiliate
	 */
	public $affiliate;

	/**
	 * @var DateTimeImmutable|null "now" of the charts (tests pin it), default the current time
	 */
	public $now;

	/**
	 * @var array|null memoized [[getCommissionTotals()]]
	 */
	private $_commissionTotals;

	/**
	 * @var AffiliateStatsService|null the last 12 months
	 */
	private $_chartStats;

	/**
	 * @var AffiliateStatsService|null all time
	 */
	private $_allTimeStats;

	/**
	 * Subscribers who signed up through the affiliate's links.
	 *
	 * @return int
	 */
	public function getSignupCount()
	{
		return $this->getAllTimeStats()->getSignupCount();
	}

	/**
	 * Active subscriptions of those subscribers.
	 *
	 * @return int
	 */
	public function getActiveSubscriptionCount()
	{
		return $this->getAllTimeStats()->getActiveSubscriptionCount();
	}

	/**
	 * The net revenue (VAT excluded) the referred subscribers generated, per currency:
	 * `['RON' => '1250.00', ...]`, see AffiliateStatsService::getRevenueTotals().
	 *
	 * @return array
	 */
	public function getRevenueTotals()
	{
		return $this->getAllTimeStats()->getRevenueTotals();
	}

	/**
	 * Commissions per currency and status, see Affiliate::getCommissionTotals().
	 *
	 * @return array
	 */
	public function getCommissionTotals()
	{
		if ($this->_commissionTotals === null) {
			$this->_commissionTotals = $this->affiliate->getCommissionTotals();
		}
		return $this->_commissionTotals;
	}

	/**
	 * The currencies the dashboard has figures in, sorted.
	 *
	 * @return string[]
	 */
	public function getCurrencies()
	{
		$currencies = array_unique(array_merge(array_keys($this->getRevenueTotals()), array_keys($this->getCommissionTotals())));
		sort($currencies);
		return $currencies;
	}

	/**
	 * The affiliate's referral link: the master home page with `?aff=CODE` (any master
	 * page captures it, see common\components\AffiliateTracker).
	 *
	 * @return string
	 */
	public function getReferralUrl()
	{
		return $this->affiliate->getReferralUrl();
	}

	// ------------------------------------------------------------------------ charts

	/**
	 * The chart months: the last 12 calendar months, the current one included, oldest
	 * first, as `Y-m` keys.
	 *
	 * @return string[]
	 */
	public function getChartMonths()
	{
		return $this->getChartStats()->getMonths();
	}

	/**
	 * Short month labels for the axis, in the application language ("oct. 2026").
	 *
	 * @return string[]
	 */
	public function getChartMonthLabels()
	{
		return $this->getChartStats()->getMonthLabels();
	}

	/**
	 * Full month names for the tooltips and tables ("octombrie 2026").
	 *
	 * @return string[]
	 */
	public function getChartMonthTitles()
	{
		return $this->getChartStats()->getMonthTitles();
	}

	/**
	 * Signups and conversions per chart month:
	 * `['signups' => int[12], 'conversions' => int[12]]`, see
	 * AffiliateStatsService::getMonthlySignups().
	 *
	 * @return array
	 */
	public function getMonthlySignups()
	{
		return $this->getChartStats()->getMonthlySignups();
	}

	/**
	 * Commission amounts per currency, status and chart month (largest currency first),
	 * see AffiliateStatsService::getMonthlyCommissions().
	 *
	 * @return array
	 */
	public function getMonthlyCommissions()
	{
		return $this->getChartStats()->getMonthlyCommissions();
	}

	/**
	 * The first day of the oldest chart month.
	 *
	 * @return DateTimeImmutable
	 */
	protected function getChartStart()
	{
		$now = $this->now ?: new DateTimeImmutable();
		return $now->modify('first day of this month')->setTime(0, 0)->modify('-11 months');
	}

	/**
	 * The chart window as `[start, end)` datetimes; future-dated rows stay out.
	 *
	 * @return string[]
	 */
	protected function getChartRange()
	{
		$start = $this->getChartStart();
		return [$start->format('Y-m-d H:i:s'), $start->modify('+12 months')->format('Y-m-d H:i:s')];
	}

	/**
	 * The affiliate's figures over the chart window.
	 *
	 * @return AffiliateStatsService
	 */
	protected function getChartStats()
	{
		if ($this->_chartStats === null) {
			list($from, $to) = $this->getChartRange();
			$this->_chartStats = new AffiliateStatsService([
				'affiliateId' => $this->affiliate->getAttribute('id'),
				'from' => $from,
				'to' => $to,
			]);
		}
		return $this->_chartStats;
	}

	/**
	 * The affiliate's all-time figures.
	 *
	 * @return AffiliateStatsService
	 */
	protected function getAllTimeStats()
	{
		if ($this->_allTimeStats === null) {
			$this->_allTimeStats = new AffiliateStatsService([
				'affiliateId' => $this->affiliate->getAttribute('id'),
			]);
		}
		return $this->_allTimeStats;
	}
}
