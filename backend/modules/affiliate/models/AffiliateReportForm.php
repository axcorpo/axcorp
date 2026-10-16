<?php

namespace backend\modules\affiliate\models;

use common\helpers\DateHelper;
use common\helpers\UuidHelper;
use common\models\Affiliate;
use common\models\AffiliateCommission;
use common\models\Invoice;
use common\models\Subscriber;
use common\models\Subscription;
use common\services\AffiliateStatsService;
use Yii;
use yii\base\Model;
use yii\db\Expression;
use yii\db\Query;

/**
 * Affiliate statistics grouped per month or affiliate:
 *
 * - signups: referred master subscribers (subscriber with an affiliate_id) by signup date;
 * - conversions: those signups with at least one paid master invoice (type INVOICE, PAID);
 * - active subscriptions: ACTIVE subscription rows of referred subscribers, by start;
 * - revenue: NET amount (VAT excluded, as the commission base) of the referred
 *   subscribers' paid master invoices minus their storno (RECTIFY) invoices, per currency;
 * - commissions pending / payable / paid per currency, by earned date (cancelled left out).
 *
 * Amounts are never converted between currencies (decision: totals per currency).
 * The leaderboard is the affiliate grouping ordered by revenue in one currency.
 */
class AffiliateReportForm extends Model
{
	const GROUP_MONTH = 'month';
	const GROUP_AFFILIATE = 'affiliate';

	/**
	 * @var string first day of the period, inclusive: the `dateFormat` setting as the picker
	 * sends it, "Y-m-d" once validated
	 */
	public $from;

	/**
	 * @var string last day of the period, inclusive (same formats as [[from]])
	 */
	public $to;

	/**
	 * @var string affiliate uuid
	 */
	public $affiliate_id;

	/**
	 * @var string one of the GROUP_* constants
	 */
	public $group = self::GROUP_MONTH;

	/**
	 * @var string currency the leaderboard ranks by
	 */
	public $currency;

	/**
	 * @var array|null memoized [[getRows()]]
	 */
	private $_rows;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		// Default period: the last 12 months up to today, so the monthly chart is readable.
		$this->from = (new \DateTime('first day of this month'))->modify('-11 months')->format('Y-m-d');
		$this->to = date('Y-m-d');
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			// The pickers send the settings date format, as the list date filters: normalize it to
			// the day, leaving garbage for the check below.
			[['from', 'to'], 'filter', 'filter' => function ($value) {
				$date = DateHelper::parseDateTime($value);
				return $date ? substr($date, 0, 10) : $value;
			}],
			[['from', 'to'], 'date', 'format' => 'php:Y-m-d'],
			[['affiliate_id'], \common\validators\UuidValidator::class, 'allowBinary' => false],
			[['group'], 'in', 'range' => [static::GROUP_MONTH, static::GROUP_AFFILIATE]],
			[['currency'], 'match', 'pattern' => '/^[A-Z]{3}$/'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return [
			static::SCENARIO_DEFAULT => ['from', 'to', 'affiliate_id', 'group', 'currency'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'from' => Yii::t('label', 'From'),
			// Not 'To': its shared translation ("Pentru a") does not read as the end of a period.
			'to' => Yii::t('label', 'Until'),
			'affiliate_id' => Yii::t('label', 'Affiliate'),
			'group' => Yii::t('label', 'Group By'),
			'currency' => Yii::t('label', 'Currency'),
		];
	}

	/**
	 * @inheritdoc
	 */
	public function formName()
	{
		return '';
	}

	/**
	 * @return array
	 */
	public static function getGroupLabels()
	{
		return [
			static::GROUP_MONTH => Yii::t('label', 'Month'),
			static::GROUP_AFFILIATE => Yii::t('label', 'Affiliate'),
		];
	}

	/**
	 * The shared figures (AffiliateStatsService) for the filters: the period runs from
	 * [[from]] to [[to]], both included. Null when the filters are invalid.
	 *
	 * @param int $limit ranked rows named before "Other"
	 * @return AffiliateStatsService|null
	 */
	public function createStats($limit = 10)
	{
		if (!$this->validate()) {
			return null;
		}
		return new AffiliateStatsService([
			'affiliateId' => UuidHelper::isValid($this->affiliate_id) ? UuidHelper::toBytes($this->affiliate_id) : null,
			'from' => $this->from ? $this->from . ' 00:00:00' : null,
			// [[to]] is included (the whole day): the service's end is exclusive.
			'to' => $this->to ? DateHelper::formatAsDateTime($this->to, '+1 day') : null,
			'limit' => $limit,
		]);
	}

	/**
	 * The report rows, keyed by group key, sorted (months ascending, others by signups):
	 * `['key' => .., 'label' => .., 'signups' => int, 'conversions' => int,
	 *   'activeSubscriptions' => int, 'revenue' => [cur => float],
	 *   'commissions' => [status => [cur => float]]]`.
	 * Invalid filters yield no rows.
	 *
	 * @return array
	 */
	public function getRows()
	{
		if ($this->_rows !== null) {
			return $this->_rows;
		}
		if (!$this->validate()) {
			return $this->_rows = [];
		}

		$rows = [];
		$touch = function ($key) use (&$rows) {
			if (!isset($rows[$key])) {
				$rows[$key] = [
					'key' => $key,
					'label' => $key,
					'signups' => 0,
					'conversions' => 0,
					'activeSubscriptions' => 0,
					'revenue' => [],
					'commissions' => [],
				];
			}
		};

		foreach ($this->querySignups() as $row) {
			$touch($row['k']);
			$rows[$row['k']]['signups'] = (int) $row['signups'];
			$rows[$row['k']]['conversions'] = (int) $row['conversions'];
		}
		foreach ($this->queryActiveSubscriptions() as $row) {
			$touch($row['k']);
			$rows[$row['k']]['activeSubscriptions'] = (int) $row['total'];
		}
		foreach ($this->queryRevenue() as $row) {
			$touch($row['k']);
			$rows[$row['k']]['revenue'][$row['currency']] = round((float) $row['total'], 2);
		}
		foreach ($this->queryCommissions() as $row) {
			$touch($row['k']);
			$rows[$row['k']]['commissions'][(int) $row['status']][$row['currency']] = round((float) $row['total'], 2);
		}

		$this->applyLabels($rows);
		if ($this->group === static::GROUP_MONTH) {
			ksort($rows);
		} else {
			uasort($rows, function ($a, $b) {
				return $b['signups'] <=> $a['signups'];
			});
		}
		return $this->_rows = $rows;
	}

	/**
	 * The affiliates ranked by revenue in [[currency]] (default: the most common currency).
	 *
	 * @return array rows of [[getRows()]] with the affiliate grouping
	 */
	public function getLeaderboard()
	{
		$this->group = static::GROUP_AFFILIATE;
		$this->_rows = null;
		$rows = $this->getRows();
		if (!$this->currency) {
			$this->currency = $this->getMainCurrency($rows);
		}
		$currency = $this->currency;
		uasort($rows, function ($a, $b) use ($currency) {
			return ($b['revenue'][$currency] ?? 0) <=> ($a['revenue'][$currency] ?? 0)
				?: $b['conversions'] <=> $a['conversions'];
		});
		return array_values($rows);
	}

	/**
	 * Every currency present in the rows' revenue or commissions.
	 *
	 * @param array|null $rows
	 * @return string[]
	 */
	public function getCurrencies($rows = null)
	{
		$currencies = [];
		foreach ($rows === null ? $this->getRows() : $rows as $row) {
			$currencies = array_merge($currencies, array_keys($row['revenue']));
			foreach ($row['commissions'] as $byCurrency) {
				$currencies = array_merge($currencies, array_keys($byCurrency));
			}
		}
		$currencies = array_values(array_unique(array_filter($currencies)));
		sort($currencies);
		return $currencies;
	}

	/**
	 * @param array $rows
	 * @return string|null the currency with the largest revenue total
	 */
	protected function getMainCurrency(array $rows)
	{
		$totals = [];
		foreach ($rows as $row) {
			foreach ($row['revenue'] as $currency => $amount) {
				$totals[$currency] = ($totals[$currency] ?? 0) + $amount;
			}
		}
		arsort($totals);
		return $totals ? (string) key($totals) : null;
	}

	/**
	 * The group-by expression of a query.
	 *
	 * @param string $dateColumn
	 * @param string $affiliateColumn
	 * @return Expression|string
	 */
	protected function groupExpression($dateColumn, $affiliateColumn)
	{
		if ($this->group === static::GROUP_AFFILIATE) {
			return $affiliateColumn;
		}
		return new Expression("DATE_FORMAT({$dateColumn}, '%Y-%m')");
	}

	/**
	 * Applies the period and affiliate filters.
	 *
	 * @param Query $query
	 * @param string $dateColumn
	 * @param string $affiliateColumn
	 * @return Query
	 */
	protected function applyFilters(Query $query, $dateColumn, $affiliateColumn)
	{
		// Validated values are "Y-m-d" days; both days are included.
		$query->andFilterWhere(['>=', new Expression($dateColumn), $this->from ? $this->from . ' 00:00:00' : null]);
		$query->andFilterWhere(['<', new Expression($dateColumn), $this->to ? DateHelper::formatAsDateTime($this->to, '+1 day') : null]);
		if (UuidHelper::isValid($this->affiliate_id)) {
			$query->andWhere([$affiliateColumn => UuidHelper::toBytes($this->affiliate_id)]);
		}
		return $query;
	}

	/**
	 * @return array
	 */
	protected function querySignups()
	{
		$paid = (new Query())
			->from(['pi' => Invoice::tableName()])
			->where('[[pi.subscriber_id]] = [[s.id]]')
			->andWhere(['pi.type' => Invoice::TYPE_INVOICE, 'pi.status' => Invoice::STATUS_PAID, 'pi.deleted' => Invoice::NO]);

		$query = (new Query())
			->select([
				'k' => $this->groupExpression('[[s.created_at]]', 's.affiliate_id'),
				'signups' => 'COUNT(*)',
				'conversions' => new Expression('SUM(CASE WHEN EXISTS(' . $paid->createCommand()->getRawSql() . ') THEN 1 ELSE 0 END)'),
			])
			->from(['s' => Subscriber::tableName()])
			->where(['s.deleted' => Subscriber::NO])
			->andWhere(['IS NOT', 's.affiliate_id', null])
			->groupBy('k');
		return $this->applyFilters($query, '[[s.created_at]]', 's.affiliate_id')->all();
	}

	/**
	 * @return array
	 */
	protected function queryActiveSubscriptions()
	{
		$query = (new Query())
			->select([
				'k' => $this->groupExpression('COALESCE([[sub.start_at]], [[sub.created_at]])', 's.affiliate_id'),
				'total' => 'COUNT(*)',
			])
			->from(['sub' => Subscription::tableName()])
			->innerJoin(['s' => Subscriber::tableName()], '[[s.id]] = [[sub.subscriber_id]]')
			->where([
				'sub.status' => Subscription::STATUS_ACTIVE,
				'sub.deleted' => Subscription::NO,
				's.deleted' => Subscriber::NO,
			])
			->andWhere(['IS NOT', 's.affiliate_id', null])
			->groupBy('k');
		return $this->applyFilters($query, 'COALESCE([[sub.start_at]], [[sub.created_at]])', 's.affiliate_id')->all();
	}

	/**
	 * @return array
	 */
	protected function queryRevenue()
	{
		$date = 'COALESCE([[i.paid_at]], [[i.issued_at]], [[i.created_at]])';
		// Net like the commission base (AffiliateCommissionService::netAmount()); a storno
		// counts negative whatever sign was stored.
		$net = 'ROUND(ABS([[i.amount]]) / (1 + COALESCE([[i.vat]], 0) / 100), 2)';
		$query = (new Query())
			->select([
				'k' => $this->groupExpression($date, 's.affiliate_id'),
				'i.currency',
				'total' => new Expression("SUM(CASE WHEN [[i.type]] = " . (int) Invoice::TYPE_RECTIFY . " THEN -{$net} ELSE {$net} END)"),
			])
			->from(['i' => Invoice::tableName()])
			->innerJoin(['s' => Subscriber::tableName()], '[[s.id]] = [[i.subscriber_id]]')
			->where(['i.deleted' => Invoice::NO])
			->andWhere(['IS NOT', 's.affiliate_id', null])
			->andWhere(['or',
				['and', ['i.type' => Invoice::TYPE_INVOICE, 'i.status' => Invoice::STATUS_PAID]],
				['i.type' => Invoice::TYPE_RECTIFY],
			])
			->groupBy(['k', 'i.currency']);
		return $this->applyFilters($query, $date, 's.affiliate_id')->all();
	}

	/**
	 * @return array
	 */
	protected function queryCommissions()
	{
		$date = 'COALESCE([[c.earned_at]], [[c.created_at]])';
		$query = (new Query())
			->select([
				'k' => $this->groupExpression($date, 'c.affiliate_id'),
				'c.currency',
				'c.status',
				'total' => 'SUM([[c.amount]])',
			])
			->from(['c' => AffiliateCommission::tableName()])
			->where(['!=', 'c.status', AffiliateCommission::STATUS_CANCELLED])
			->groupBy(['k', 'c.currency', 'c.status']);
		return $this->applyFilters($query, $date, 'c.affiliate_id')->all();
	}

	/**
	 * Replaces affiliate keys (raw ids) by readable labels.
	 *
	 * @param array $rows
	 */
	protected function applyLabels(array &$rows)
	{
		$keys = array_keys($rows);
		if ($this->group === static::GROUP_AFFILIATE && $keys) {
			$affiliates = Affiliate::find()->with('user')->where(['id' => $keys])->indexBy(function ($model) {
				return $model->getAttribute('id');
			})->all();
			foreach ($rows as $key => &$row) {
				$affiliate = $affiliates[$key] ?? null;
				$row['label'] = $affiliate ? trim(($affiliate->user ? $affiliate->user->getFullName() : '') . " ({$affiliate->code})") : Yii::t('common', 'Unknown');
				$row['uuid'] = $affiliate ? $affiliate->uuid : null;
			}
			unset($row);
		} else {
			foreach ($rows as $key => &$row) {
				$row['label'] = $key ? Yii::$app->formatter->asDate($key . '-01', 'MMM yyyy') : Yii::t('common', 'Unknown');
			}
			unset($row);
		}
	}
}
