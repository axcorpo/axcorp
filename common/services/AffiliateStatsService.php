<?php

namespace common\services;

use common\models\Affiliate;
use common\models\AffiliateCommission;
use common\models\Invoice;
use common\models\Subscriber;
use common\models\Subscription;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use Yii;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;
use yii\db\Expression;
use yii\db\Query;

/**
 * The affiliate figures behind the charts, shared by the affiliate's dashboard (frontend)
 * and the admin reports (backend), so both count the same way:
 *
 * - a signup is a referred (affiliate_id set), non-deleted master subscriber, dated by
 *   its creation;
 * - a conversion is a subscriber's FIRST commission-generating invoice (a non-cancelled,
 *   non-storno commission), counted once, in that month;
 * - revenue is the commission base (net, VAT excluded; storno negative), cancelled left out;
 * - commissions are dated by when they were earned (the invoice's paid date).
 *
 * Every figure takes the same optional scope: [[affiliateId]] and the period [[from]] (inclusive) – [[to]] (exclusive). Amounts are per currency, never
 * converted (decision: totals per currency). Each method runs one grouped query.
 *
 * ```php
 * $stats = new AffiliateStatsService(['affiliateId' => $affiliate->getAttribute('id'), 'from' => '2026-01-01', 'to' => '2027-01-01']);
 * $stats->getMonthlySignups(); // ['signups' => int[12], 'conversions' => int[12]]
 * ```
 */
class AffiliateStatsService extends BaseObject
{
	/**
	 * @var string|string[]|null affiliate id(s) (raw bytes); null = every affiliate
	 */
	public $affiliateId;

	/**
	 * @var DateTimeInterface|string|null start of the period, inclusive; null = no start
	 */
	public $from;

	/**
	 * @var DateTimeInterface|string|null end of the period, EXCLUSIVE; null = no end
	 */
	public $to;

	/**
	 * @var int how many rows the ranked figures name before folding the rest into "Other"
	 */
	public $limit = 10;

	/**
	 * @var array memoized results, by method
	 */
	private $_cache = [];

	// ------------------------------------------------------------------------ months

	/**
	 * The months of the period, oldest first, as `Y-m` keys — every month, empty or not.
	 *
	 * @return string[]
	 * @throws InvalidConfigException without a complete period
	 */
	public function getMonths()
	{
		if (isset($this->_cache['months'])) {
			return $this->_cache['months'];
		}
		list($from, $to) = $this->getPeriod();
		if (!$from || !$to) {
			throw new InvalidConfigException('The monthly figures need both "from" and "to".');
		}
		$months = [];
		$month = $from->modify('first day of this month')->setTime(0, 0);
		// "to" is exclusive: a period ending on the 1st at midnight leaves that month out.
		$last = $to->modify('-1 second')->format('Y-m');
		while ($month->format('Y-m') <= $last) {
			$months[] = $month->format('Y-m');
			$month = $month->modify('+1 month');
		}
		return $this->_cache['months'] = $months;
	}

	/**
	 * Short month labels for the axis, in the application language ("oct. 2026").
	 *
	 * @return string[]
	 */
	public function getMonthLabels()
	{
		return $this->formatMonths('LLL y', 'M Y');
	}

	/**
	 * Full month names for the tooltips and tables ("octombrie 2026").
	 *
	 * @return string[]
	 */
	public function getMonthTitles()
	{
		return $this->formatMonths('LLLL y', 'F Y');
	}

	// ------------------------------------------------------------------------ series

	/**
	 * Signups and conversions per month of the period:
	 * `['signups' => int[], 'conversions' => int[]]`, aligned with [[getMonths()]].
	 *
	 * A subscriber who first paid before the period is not counted again inside it.
	 *
	 * @return array
	 */
	public function getMonthlySignups()
	{
		if (isset($this->_cache['monthlySignups'])) {
			return $this->_cache['monthlySignups'];
		}
		$signups = $this->subscriberQuery('s')
			->select(['month' => "DATE_FORMAT([[s.created_at]], '%Y-%m')", 'total' => 'COUNT(*)'])
			->groupBy(['month']);
		$this->applyPeriod($signups, '[[s.created_at]]');

		$firstPaid = $this->paidCommissionQuery('c')
			->select(['c.subscriber_id', 'first_paid' => 'MIN(COALESCE([[c.earned_at]], [[c.created_at]]))'])
			->groupBy(['c.subscriber_id']);
		$conversions = (new Query())
			->select(['month' => "DATE_FORMAT([[first_paid]], '%Y-%m')", 'total' => 'COUNT(*)'])
			->from(['f' => $firstPaid])
			->groupBy(['month']);
		$this->applyPeriod($conversions, '[[first_paid]]');

		return $this->_cache['monthlySignups'] = [
			'signups' => array_map('intval', $this->fillMonths($signups->all())),
			'conversions' => array_map('intval', $this->fillMonths($conversions->all())),
		];
	}

	/**
	 * Commission amounts per currency, status and month, by the date the commission was
	 * earned: `['RON' => [STATUS_PAID => float[], STATUS_PAYABLE => float[],
	 * STATUS_PENDING => float[]], ...]`, aligned with [[getMonths()]].
	 *
	 * Cancelled commissions are left out; storno (negative) ones net into their month.
	 * Statuses come in the fixed order of ChartHelper::statusColor(); currencies are
	 * ordered by their total, largest first, so the first one is a chart's default.
	 *
	 * @return array
	 */
	public function getMonthlyCommissions()
	{
		if (isset($this->_cache['monthlyCommissions'])) {
			return $this->_cache['monthlyCommissions'];
		}
		$date = 'COALESCE([[c.earned_at]], [[c.created_at]])';
		$query = $this->commissionQuery('c')
			->select([
				'c.currency',
				'c.status',
				'month' => "DATE_FORMAT({$date}, '%Y-%m')",
				'total' => 'SUM([[c.amount]])',
			])
			->andWhere(['not', ['c.currency' => null]])
			->groupBy(['c.currency', 'c.status', 'month']);
		$this->applyPeriod($query, $date);

		$byStatus = [];
		foreach ($query->all() as $row) {
			$byStatus[$row['currency']][(int) $row['status']][] = $row;
		}
		$series = [];
		$totals = [];
		foreach ($byStatus as $currency => $statuses) {
			$totals[$currency] = 0;
			foreach (static::chartStatuses() as $status) {
				$values = array_map(function ($value) {
					return round((float) $value, 2);
				}, $this->fillMonths($statuses[$status] ?? []));
				$series[$currency][$status] = $values;
				$totals[$currency] += array_sum($values);
			}
		}
		uksort($series, function ($a, $b) use ($totals) {
			return [$totals[$b], $a] <=> [$totals[$a], $b];
		});
		return $this->_cache['monthlyCommissions'] = $series;
	}

	/**
	 * The affiliates ranked by the net revenue (commission base) they generated in one
	 * currency, most first: `[['label' => string, 'uuid' => string|null, 'amount' => float,
	 * 'other' => bool], ...]`, the rest past [[limit]] folded into "Other". Affiliates
	 * netting to zero are left out.
	 *
	 * @param string $currency
	 * @return array
	 */
	public function getAffiliateRevenue($currency)
	{
		$key = 'affiliateRevenue.' . $currency;
		if (isset($this->_cache[$key])) {
			return $this->_cache[$key];
		}
		$date = 'COALESCE([[c.earned_at]], [[c.created_at]])';
		$query = $this->commissionQuery('c')
			->select(['c.affiliate_id', 'total' => 'SUM([[c.base_amount]])'])
			->andWhere(['c.currency' => $currency])
			->groupBy(['c.affiliate_id'])
			->having(['!=', 'total', 0]);
		$totals = $this->applyPeriod($query, $date)->all();

		$affiliates = [];
		if ($totals) {
			$affiliates = Affiliate::find()->with('user')->where(['id' => array_column($totals, 'affiliate_id')])->indexBy(function ($model) {
				return $model->getAttribute('id');
			})->all();
		}
		$rows = [];
		foreach ($totals as $total) {
			$affiliate = $affiliates[$total['affiliate_id']] ?? null;
			$rows[] = [
				'label' => $affiliate ? static::affiliateLabel($affiliate) : Yii::t('common', 'Unknown'),
				'uuid' => $affiliate ? $affiliate->uuid : null,
				'amount' => round((float) $total['total'], 2),
				'other' => false,
			];
		}
		usort($rows, function ($a, $b) {
			return [$b['amount'], $a['label']] <=> [$a['amount'], $b['label']];
		});
		return $this->_cache[$key] = $this->foldOther($rows, 'amount');
	}

	// ------------------------------------------------------------------------ totals

	/**
	 * Referred, non-deleted subscribers who signed up in the period.
	 *
	 * @return int
	 */
	public function getSignupCount()
	{
		return $this->getCohort()['signups'];
	}

	/**
	 * What became of the period's signups, for the conversion steps:
	 * `['signups' => int, 'active' => int, 'paid' => int]` — of the signups, those with an
	 * active subscription now and those who paid at least once (a commission-generating
	 * invoice, whenever). Both are subsets of the signups, so never more than them.
	 *
	 * @return array
	 */
	public function getCohort()
	{
		if (isset($this->_cache['cohort'])) {
			return $this->_cache['cohort'];
		}
		$active = (new Query())
			->from(['sub' => Subscription::tableName()])
			->where('[[sub.subscriber_id]] = [[s.id]]')
			->andWhere(['sub.status' => Subscription::STATUS_ACTIVE, 'sub.deleted' => Subscription::NO]);
		$paid = (new Query())
			->from(['pc' => AffiliateCommission::tableName()])
			->where('[[pc.subscriber_id]] = [[s.id]]')
			->andWhere(['!=', 'pc.status', AffiliateCommission::STATUS_CANCELLED])
			->andWhere(['or', ['pc.type' => null], ['!=', 'pc.type', Invoice::TYPE_RECTIFY]]);
		$query = $this->subscriberQuery('s')->select([
			'signups' => 'COUNT(*)',
			'active' => new Expression('COALESCE(SUM(CASE WHEN EXISTS(' . $active->createCommand()->getRawSql() . ') THEN 1 ELSE 0 END), 0)'),
			'paid' => new Expression('COALESCE(SUM(CASE WHEN EXISTS(' . $paid->createCommand()->getRawSql() . ') THEN 1 ELSE 0 END), 0)'),
		]);
		$row = $this->applyPeriod($query, '[[s.created_at]]')->one();

		return $this->_cache['cohort'] = [
			'signups' => (int) ($row['signups'] ?? 0),
			'active' => (int) ($row['active'] ?? 0),
			'paid' => (int) ($row['paid'] ?? 0),
		];
	}

	/**
	 * Active subscriptions (not subscribers) of the referred, non-deleted subscribers
	 * who signed up in the period.
	 *
	 * @return int
	 */
	public function getActiveSubscriptionCount()
	{
		$query = $this->subscriberQuery('s')
			->innerJoin(['sub' => Subscription::tableName()], '[[sub.subscriber_id]] = [[s.id]]')
			->andWhere(['sub.status' => Subscription::STATUS_ACTIVE, 'sub.deleted' => Subscription::NO]);
		return (int) $this->applyPeriod($query, '[[s.created_at]]')->count();
	}

	/**
	 * Active, non-deleted affiliates (the [[affiliateId]] scope applies).
	 *
	 * @return int
	 */
	public function getActiveAffiliateCount()
	{
		$query = (new Query())
			->from(['a' => Affiliate::tableName()])
			->where(['a.status' => Affiliate::STATUS_ACTIVE, 'a.deleted' => Affiliate::NO]);
		if ($this->affiliateId !== null) {
			$query->andWhere(['a.id' => $this->affiliateId]);
		}
		return (int) $query->count();
	}

	/**
	 * The net revenue (commission base) per currency, in the period:
	 * `['RON' => '1250.00', ...]` (raw decimal strings). Each paid invoice of a referred
	 * subscriber is exactly one commission, storno invoices negative, so this is what
	 * the affiliates are paid on; cancelled (voided) invoices are left out.
	 *
	 * @return array
	 */
	public function getRevenueTotals()
	{
		if (isset($this->_cache['revenueTotals'])) {
			return $this->_cache['revenueTotals'];
		}
		$query = $this->commissionQuery('c')
			->select(['c.currency', 'total' => 'SUM([[c.base_amount]])'])
			->groupBy(['c.currency']);
		$totals = [];
		foreach ($this->applyPeriod($query, 'COALESCE([[c.earned_at]], [[c.created_at]])')->all() as $row) {
			$totals[$row['currency']] = $row['total'];
		}
		return $this->_cache['revenueTotals'] = $totals;
	}

	/**
	 * Commissions earned in the period per currency and status (cancelled left out):
	 * `['RON' => [STATUS_PAID => float, STATUS_PAYABLE => float, STATUS_PENDING => float]]`.
	 *
	 * @return array
	 */
	public function getCommissionTotals()
	{
		if (isset($this->_cache['commissionTotals'])) {
			return $this->_cache['commissionTotals'];
		}
		$query = $this->commissionQuery('c')
			->select(['c.currency', 'c.status', 'total' => 'SUM([[c.amount]])'])
			->andWhere(['not', ['c.currency' => null]])
			->groupBy(['c.currency', 'c.status']);
		$totals = [];
		foreach ($this->applyPeriod($query, 'COALESCE([[c.earned_at]], [[c.created_at]])')->all() as $row) {
			if (!isset($totals[$row['currency']])) {
				$totals[$row['currency']] = array_fill_keys(static::chartStatuses(), 0.0);
			}
			$totals[$row['currency']][(int) $row['status']] = round((float) $row['total'], 2);
		}
		return $this->_cache['commissionTotals'] = $totals;
	}

	/**
	 * The currencies the period has commissions in, the one with the largest revenue
	 * first (the default of a currency filter), then alphabetically.
	 *
	 * @return string[]
	 */
	public function getCurrencies()
	{
		$totals = [];
		foreach ($this->getRevenueTotals() as $currency => $total) {
			if ($currency !== '' && $currency !== null) {
				$totals[$currency] = abs((float) $total);
			}
		}
		foreach (array_keys($this->getCommissionTotals()) as $currency) {
			$totals += [$currency => 0];
		}
		uksort($totals, function ($a, $b) use ($totals) {
			return [$totals[$b], $a] <=> [$totals[$a], $b];
		});
		return array_map('strval', array_keys($totals));
	}

	// ------------------------------------------------------------------------ helpers

	/**
	 * The statuses a chart shows, in the fixed order of their colour slots.
	 *
	 * @return int[]
	 */
	public static function chartStatuses()
	{
		return [AffiliateCommission::STATUS_PAID, AffiliateCommission::STATUS_PAYABLE, AffiliateCommission::STATUS_PENDING];
	}

	/**
	 * How an affiliate is named in the admin: "Full Name (CODE)".
	 *
	 * @param Affiliate $affiliate
	 * @return string
	 */
	public static function affiliateLabel(Affiliate $affiliate)
	{
		return trim(($affiliate->user ? $affiliate->user->getFullName() : '') . " ({$affiliate->code})");
	}

	/**
	 * The period as DateTimeImmutable objects (null when open).
	 *
	 * @return DateTimeImmutable[]|null[]
	 */
	protected function getPeriod()
	{
		$normalize = function ($value) {
			if ($value === null || $value === '') {
				return null;
			}
			return $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : new DateTimeImmutable($value);
		};
		return [$normalize($this->from), $normalize($this->to)];
	}

	/**
	 * Referred, non-deleted master subscribers in the affiliate scope.
	 *
	 * @param string $alias
	 * @return Query
	 */
	protected function subscriberQuery($alias)
	{
		$query = (new Query())
			->from([$alias => Subscriber::tableName()])
			->where(["{$alias}.deleted" => Subscriber::NO]);
		$query->andWhere($this->affiliateId === null ? ['not', ["{$alias}.affiliate_id" => null]] : ["{$alias}.affiliate_id" => $this->affiliateId]);
		return $query;
	}

	/**
	 * Non-cancelled commissions in the affiliate scope.
	 *
	 * @param string $alias
	 * @return Query
	 */
	protected function commissionQuery($alias)
	{
		$query = (new Query())
			->from([$alias => AffiliateCommission::tableName()])
			->where(['!=', "{$alias}.status", AffiliateCommission::STATUS_CANCELLED]);
		if ($this->affiliateId !== null) {
			$query->andWhere(["{$alias}.affiliate_id" => $this->affiliateId]);
		}
		return $query;
	}

	/**
	 * The commissions that make a conversion: a subscriber's paid invoices, storno left out.
	 *
	 * @param string $alias
	 * @return Query
	 */
	protected function paidCommissionQuery($alias)
	{
		return $this->commissionQuery($alias)
			->andWhere(['not', ["{$alias}.subscriber_id" => null]])
			->andWhere(['or', ["{$alias}.type" => null], ['!=', "{$alias}.type", Invoice::TYPE_RECTIFY]]);
	}

	/**
	 * Restricts a query to the period on a date expression.
	 *
	 * @param Query $query
	 * @param string $dateExpression
	 * @return Query
	 */
	protected function applyPeriod(Query $query, $dateExpression)
	{
		list($from, $to) = $this->getPeriod();
		if ($from) {
			$query->andWhere(['>=', new Expression($dateExpression), $from->format('Y-m-d H:i:s')]);
		}
		if ($to) {
			$query->andWhere(['<', new Expression($dateExpression), $to->format('Y-m-d H:i:s')]);
		}
		return $query;
	}

	/**
	 * Spreads grouped `['month' => 'Y-m', 'total' => x]` rows over [[getMonths()]], empty
	 * months as 0.
	 *
	 * @param array $rows
	 * @return array values in month order
	 */
	protected function fillMonths(array $rows)
	{
		$values = array_fill_keys($this->getMonths(), 0);
		foreach ($rows as $row) {
			if (array_key_exists($row['month'], $values)) {
				$values[$row['month']] += $row['total'];
			}
		}
		return array_values($values);
	}

	/**
	 * Keeps the first [[limit]] ranked rows and sums the rest into one "Other" row, last.
	 *
	 * @param array $rows ranked rows with a `label` and an `other` flag
	 * @param string $valueKey the summed column
	 * @return array
	 */
	protected function foldOther(array $rows, $valueKey)
	{
		if (count($rows) <= $this->limit) {
			return $rows;
		}
		$rest = array_splice($rows, $this->limit);
		// Same keys, same order as the ranked rows; the extra columns stay empty.
		$other = array_fill_keys(array_keys($rest[0]), null);
		$sum = array_sum(array_column($rest, $valueKey));
		$other['label'] = Yii::t('common', 'Other');
		$other[$valueKey] = is_float($sum) ? round($sum, 2) : $sum;
		$other['other'] = true;
		$rows[] = $other;
		return $rows;
	}

	/**
	 * The period's months formatted with an ICU pattern in the application language.
	 *
	 * @param string $pattern ICU pattern
	 * @param string $fallback date() format, when intl is missing
	 * @return string[]
	 */
	protected function formatMonths($pattern, $fallback)
	{
		$formatter = class_exists(IntlDateFormatter::class)
			? new IntlDateFormatter(Yii::$app->language, IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'UTC', null, $pattern)
			: null;
		$labels = [];
		foreach ($this->getMonths() as $month) {
			// Mid-month noon UTC: no time zone shift can move it into another month.
			$date = new DateTimeImmutable($month . '-15 12:00:00', new DateTimeZone('UTC'));
			$labels[] = $formatter ? $formatter->format($date) : $date->format($fallback);
		}
		return $labels;
	}
}
