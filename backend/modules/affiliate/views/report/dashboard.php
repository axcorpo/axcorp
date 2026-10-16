<?php
/* @var $this yii\web\View */
/* @var $model backend\modules\affiliate\models\AffiliateReportForm */
/* @var $stats common\services\AffiliateStatsService|null null when the filters are invalid */
/* @var $currencies string[] currencies with figures in the period, the default first */

use common\helpers\ChartHelper;
use common\models\AffiliateCommission;
use yii\helpers\Html;

$this->title = Yii::t('common', 'Affiliate Overview');
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Affiliates'),
		'url' => ['affiliate/index'],
	],
	$this->title,
];

$formatter = Yii::$app->formatter;
$currency = $model->currency;
$this->registerCss(<<<CSS
.affiliate-kpi .widget-thumb { background: #fff; margin-bottom: 20px; padding: 15px 20px; }
.affiliate-kpi .widget-thumb-heading { margin-bottom: 8px; }
.affiliate-kpi .widget-thumb-body-stat { font-size: 24px; line-height: 1.3; word-break: break-word; }
.affiliate-kpi .widget-thumb-subtitle { font-weight: normal; font-size: 13px; }
CSS);
?>

<?= $this->render('_filters', [
	'model' => $model,
	'action' => 'dashboard',
	'showGroup' => false,
	'showAffiliate' => true,
	'currencies' => $currencies,
]) ?>

<hr/>

<?php if (!$stats || !$stats->getMonths()): ?>
	<div class="text-info-icon fa-info-circle"><?= Yii::t('common', 'Choose a valid period to see the figures.') ?></div>
	<?php return; ?>
<?php endif; ?>

<?php
// ------------------------------------------------------------------ KPI tiles
$cohort = $stats->getCohort();
$revenue = $stats->getRevenueTotals();
$commissionTotals = $currency ? ($stats->getCommissionTotals()[$currency] ?? []) : [];
$money = function ($amount) use ($currency) {
	return $currency ? ChartHelper::formatAmount($amount, $currency) : '—';
};
$pending = $commissionTotals[AffiliateCommission::STATUS_PENDING] ?? 0;
$payable = $commissionTotals[AffiliateCommission::STATUS_PAYABLE] ?? 0;
$tiles = [
	[
		'heading' => Yii::t('common', 'Active affiliates'),
		'stat' => $formatter->asInteger($stats->getActiveAffiliateCount()),
		'subtitle' => Yii::t('common', 'status active, now'),
	],
	[
		'heading' => Yii::t('common', 'Signups'),
		'stat' => $formatter->asInteger($cohort['signups']),
		'subtitle' => Yii::t('common', 'in the period'),
	],
	[
		'heading' => Yii::t('common', 'Conversion Rate'),
		'stat' => $cohort['signups'] ? $formatter->asPercent($cohort['paid'] / $cohort['signups'], 1) : '—',
		'subtitle' => Yii::t('common', '{converted} of {signups} signups paid at least once', [
			'converted' => $formatter->asInteger($cohort['paid']),
			'signups' => $formatter->asInteger($cohort['signups']),
		]),
	],
	[
		'heading' => Yii::t('common', 'Revenue (net)'),
		'stat' => $money($currency ? ($revenue[$currency] ?? 0) : 0),
		'subtitle' => Yii::t('common', 'VAT excluded, storno deducted'),
	],
	[
		'heading' => Yii::t('common', 'Commissions outstanding'),
		'stat' => $money($pending + $payable),
		'subtitle' => Yii::t('common', '{pending} pending, {payable} payable', ['pending' => $money($pending), 'payable' => $money($payable)]),
	],
	[
		'heading' => Yii::t('common', 'Commissions paid'),
		'stat' => $money($commissionTotals[AffiliateCommission::STATUS_PAID] ?? 0),
		'subtitle' => Yii::t('common', 'earned in the period'),
	],
];
?>
<div class="row affiliate-kpi">
	<?php foreach ($tiles as $tile): ?>
		<div class="col-xs-12 col-sm-6 col-md-4">
			<div class="widget-thumb bordered">
				<h4 class="widget-thumb-heading"><?= Html::encode($tile['heading']) ?></h4>
				<div class="widget-thumb-body">
					<span class="widget-thumb-body-stat"><?= Html::encode($tile['stat']) ?></span>
					<span class="widget-thumb-subtitle"><?= Html::encode($tile['subtitle']) ?></span>
				</div>
			</div>
		</div>
	<?php endforeach; ?>
</div>
<?php if (count($currencies) > 1): ?>
	<p class="help-block"><?= Html::encode(Yii::t('common', 'Amounts in {currency}; the period also has figures in {others} (choose the currency above).', [
		'currency' => $currency,
		'others' => implode(', ', array_diff($currencies, [$currency])),
	])) ?></p>
<?php endif; ?>

<div class="row">
	<div class="col-lg-6">
		<?= $this->render('_chart-signups', ['stats' => $stats, 'id' => 'chart-signups']) ?>
	</div>
	<div class="col-lg-6">
		<?= $this->render('_chart-commissions', ['stats' => $stats, 'id' => 'chart-commissions', 'currency' => $currency]) ?>
	</div>
</div>

<div class="row">
	<div class="col-lg-6">
		<?php
		// ------------------------------------------------ top affiliates by net revenue
		$affiliates = $currency ? $stats->getAffiliateRevenue($currency) : [];
		$title = Yii::t('common', 'Top affiliates by net revenue');
		$panes = [];
		if ($affiliates) {
			$top = $affiliates[0];
			$total = array_sum(array_column($affiliates, 'amount'));
			$finding = count($affiliates) === 1
				? Yii::t('common', 'All the net revenue comes from {affiliate}', ['affiliate' => $top['label']])
				: Yii::t('common', '{affiliate} brought the most: {amount} of {total}', [
					'affiliate' => $top['label'],
					'amount' => ChartHelper::formatAmount($top['amount'], $currency),
					'total' => ChartHelper::formatAmount($total, $currency),
				]);
			$links = [];
			$rows = [];
			foreach ($affiliates as $row) {
				$url = $row['uuid'] ? \yii\helpers\Url::to(['affiliate/view', 'id' => $row['uuid']]) : null;
				$links[] = $url;
				$rows[] = [
					$url ? Html::a(Html::encode($row['label']), $url) : Html::encode($row['label']),
					Html::encode(ChartHelper::formatAmount($row['amount'], $currency)),
				];
			}
			$panes[] = [
				'key' => $currency,
				'finding' => Html::encode($finding),
				'chart' => ChartHelper::widget(ChartHelper::bar(array_column($affiliates, 'label'), [
					['label' => Yii::t('common', 'Revenue (net)'), 'data' => array_column($affiliates, 'amount'), 'color' => ChartHelper::BLUE],
				], [
					'horizontal' => true,
					'allLabels' => true,
					'format' => ChartHelper::FORMAT_CURRENCY,
					'currency' => $currency,
					'links' => $links,
				]), ['aria-label' => $title . ' (' . $currency . ')']),
				'table' => [
					'caption' => $title . ' (' . $currency . ')',
					'head' => [Yii::t('label', 'Affiliate'), Yii::t('common', 'Revenue (net)')],
					'rows' => $rows,
					'foot' => [Yii::t('common', 'Total'), Html::encode(ChartHelper::formatAmount($total, $currency))],
				],
			];
		}
		echo $this->render('_chart', [
			'id' => 'chart-affiliates',
			'title' => $title,
			'panes' => $panes,
			'empty' => Yii::t('common', 'No revenue in this period.'),
		]);
		?>
	</div>
	<div class="col-lg-6">
		<?php
		// --------------------------------------------- conversion steps (a funnel)
		// Widths are counts, every step a share of the signups; one series, blue.
		$title = Yii::t('common', 'Conversion steps');
		$steps = [
			Yii::t('common', 'Signups') => $cohort['signups'],
			Yii::t('common', 'With an active subscription now') => $cohort['active'],
			Yii::t('common', 'Paid at least once') => $cohort['paid'],
		];
		$panes = [];
		if ($cohort['signups']) {
			$labels = [];
			$rows = [];
			foreach ($steps as $label => $count) {
				$share = $formatter->asPercent($count / $cohort['signups'], 1);
				// Two-line axis label: the step, then its count and share of the signups.
				$labels[] = [$label, $formatter->asInteger($count) . ' · ' . $share];
				$rows[] = [Html::encode($label), $formatter->asInteger($count), Html::encode($share)];
			}
			$panes[] = [
				'key' => null,
				'finding' => Html::encode(Yii::t('common', '{share} of the signups paid at least once', [
					'share' => $formatter->asPercent($cohort['paid'] / $cohort['signups'], 1),
				])),
				'chart' => ChartHelper::widget(ChartHelper::bar($labels, [
					['label' => Yii::t('common', 'Subscribers'), 'data' => array_values($steps), 'color' => ChartHelper::BLUE],
				], ['horizontal' => true, 'allLabels' => true, 'labelLength' => [24, 32]]), ['aria-label' => $title]),
				'table' => [
					'caption' => $title,
					'head' => [Yii::t('common', 'Step'), Yii::t('common', 'Subscribers'), Yii::t('common', 'Share of signups')],
					'rows' => $rows,
				],
			];
		}
		echo $this->render('_chart', [
			'id' => 'chart-funnel',
			'title' => $title,
			'panes' => $panes,
			'empty' => Yii::t('common', 'No signups in this period.'),
		]);
		?>
	</div>
</div>

<p class="help-block">
	<?= Yii::t('common', 'Signups by signup date; conversions in the month of the first paid invoice; the conversion rate and steps follow the period\'s signups. Revenue (VAT excluded, storno deducted) and commissions by the date they were earned, cancelled ones left out. One currency at a time, never converted.') ?>
</p>
