<?php
/* @var $this yii\web\View */
/* @var $model backend\modules\affiliate\models\AffiliateReportForm */

use backend\modules\affiliate\models\AffiliateReportForm;
use backend\modules\affiliate\models\AffiliateSearch;
use common\helpers\ChartHelper;
use common\models\AffiliateCommission;
use yii\helpers\Html;

$this->title = Yii::t('common', 'Affiliate Statistics');
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Affiliates'),
		'url' => ['affiliate/index'],
	],
	[
		'label' => Yii::t('common', 'Reports'),
		'url' => ['report/index'],
	],
	$this->title,
];

$rows = $model->getRows();
$isMonthly = $model->group === AffiliateReportForm::GROUP_MONTH;

$sumAmounts = function (array $target, array $amounts) {
	foreach ($amounts as $currency => $amount) {
		$target[$currency] = ($target[$currency] ?? 0) + $amount;
	}
	return $target;
};
$total = ['signups' => 0, 'conversions' => 0, 'activeSubscriptions' => 0, 'revenue' => [], 'commissions' => []];
foreach ($rows as $row) {
	$total['signups'] += $row['signups'];
	$total['conversions'] += $row['conversions'];
	$total['activeSubscriptions'] += $row['activeSubscriptions'];
	$total['revenue'] = $sumAmounts($total['revenue'], $row['revenue']);
	foreach ($row['commissions'] as $status => $amounts) {
		$total['commissions'][$status] = $sumAmounts($total['commissions'][$status] ?? [], $amounts);
	}
}
$rate = function ($row) {
	return $row['signups'] ? Yii::$app->formatter->asPercent($row['conversions'] / $row['signups'], 1) : '&mdash;';
};
$statuses = [
	AffiliateCommission::STATUS_PENDING => Yii::t('label', 'Pending'),
	AffiliateCommission::STATUS_PAYABLE => Yii::t('label', 'Payable'),
	AffiliateCommission::STATUS_PAID => Yii::t('label', 'Paid'),
];
?>

<?= $this->render('_filters', [
	'model' => $model,
	'action' => 'statistics',
	'showGroup' => true,
]) ?>

<hr/>

<?php
// Signups and conversions (counts: one axis). Monthly: a line over every month of the
// period, empty ones included. Per affiliate: grouped horizontal bars, the
// 10 with most signups and the rest folded into "Other".
$formatter = Yii::$app->formatter;
$title = $isMonthly ? Yii::t('common', 'Signups and conversions by signup month') : Yii::t('common', 'Signups and conversions per {group}', [
	'group' => mb_strtolower(AffiliateReportForm::getGroupLabels()[$model->group] ?? ''),
]);
$labels = [];
$titles = [];
$signups = [];
$conversions = [];
if ($isMonthly) {
	$stats = $model->createStats();
	if ($stats && $stats->from && $stats->to) {
		$labels = $stats->getMonthLabels();
		$titles = $stats->getMonthTitles();
		foreach ($stats->getMonths() as $month) {
			$signups[] = $rows[$month]['signups'] ?? 0;
			$conversions[] = $rows[$month]['conversions'] ?? 0;
		}
	}
} else {
	$limit = 10;
	foreach (array_values($rows) as $i => $row) {
		if ($i < $limit) {
			$labels[] = $row['label'];
			$signups[] = $row['signups'];
			$conversions[] = $row['conversions'];
		} else {
			$signups[$limit] = ($signups[$limit] ?? 0) + $row['signups'];
			$conversions[$limit] = ($conversions[$limit] ?? 0) + $row['conversions'];
		}
	}
	if (count($rows) > $limit) {
		$labels[] = Yii::t('common', 'Other');
	}
	$titles = $labels;
}
$panes = [];
if (ChartHelper::hasData([$signups, $conversions])) {
	$tableRows = [];
	foreach ($titles as $i => $label) {
		$tableRows[] = [Html::encode($label), $formatter->asInteger($signups[$i]), $formatter->asInteger($conversions[$i])];
	}
	$series = [
		['label' => Yii::t('common', 'Signups'), 'data' => $signups, 'color' => ChartHelper::BLUE],
		['label' => Yii::t('common', 'Conversions'), 'data' => $conversions, 'color' => ChartHelper::ORANGE],
	];
	$panes[] = [
		'key' => null,
		'finding' => Html::encode(Yii::t('common', '{signups, plural, =1{1 signup} other{# signups}}, {rate} of them converted', [
			'signups' => $total['signups'],
			'rate' => $total['signups'] ? $formatter->asPercent($total['conversions'] / $total['signups'], 1) : '0%',
		])),
		'chart' => $isMonthly
			? ChartHelper::widget(ChartHelper::line($labels, $series, ['titles' => $titles]), ['aria-label' => $title])
			: ChartHelper::widget(ChartHelper::bar($labels, $series, ['horizontal' => true, 'allLabels' => true]), ['aria-label' => $title], max(280, 30 * count($labels) + 60)),
		'table' => [
			'caption' => $title,
			'head' => [AffiliateReportForm::getGroupLabels()[$model->group] ?? '', Yii::t('common', 'Signups'), Yii::t('common', 'Conversions')],
			'rows' => $tableRows,
		],
	];
}
?>
<?= $this->render('_chart', [
	'id' => 'chart-statistics',
	'title' => $title,
	'panes' => $panes,
	'empty' => Yii::t('common', 'No records found.'),
]) ?>

<?php if ($rows): ?>
	<div class="table-responsive">
		<table class="table table-bordered table-hover table-condensed">
			<thead>
				<tr>
					<th><?= Html::encode(AffiliateReportForm::getGroupLabels()[$model->group] ?? '') ?></th>
					<th class="text-right"><?= Yii::t('common', 'Signups') ?></th>
					<th class="text-right"><?= Yii::t('common', 'Conversions') ?></th>
					<th class="text-right"><?= Yii::t('common', 'Conversion Rate') ?></th>
					<th class="text-right"><?= Yii::t('common', 'Active Subscriptions') ?></th>
					<th class="text-right"><?= Yii::t('common', 'Revenue (net)') ?></th>
					<?php foreach ($statuses as $label): ?>
						<th class="text-right"><?= Yii::t('common', 'Commissions') ?> &middot; <?= $label ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($rows as $row): ?>
					<tr>
						<td>
							<?= !empty($row['uuid']) && Yii::$app->user->can('viewAffiliate')
								? Html::a(Html::encode($row['label']), ['affiliate/view', 'id' => $row['uuid']])
								: Html::encode($row['label']) ?>
						</td>
						<td class="text-right"><?= $row['signups'] ?></td>
						<td class="text-right"><?= $row['conversions'] ?></td>
						<td class="text-right"><?= $rate($row) ?></td>
						<td class="text-right"><?= $row['activeSubscriptions'] ?></td>
						<td class="text-right"><?= AffiliateSearch::formatAmounts($row['revenue']) ?></td>
						<?php foreach (array_keys($statuses) as $status): ?>
							<td class="text-right"><?= AffiliateSearch::formatAmounts($row['commissions'][$status] ?? []) ?></td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th><?= Yii::t('common', 'Total') ?></th>
					<th class="text-right"><?= $total['signups'] ?></th>
					<th class="text-right"><?= $total['conversions'] ?></th>
					<th class="text-right"><?= $rate($total) ?></th>
					<th class="text-right"><?= $total['activeSubscriptions'] ?></th>
					<th class="text-right"><?= AffiliateSearch::formatAmounts($total['revenue']) ?></th>
					<?php foreach (array_keys($statuses) as $status): ?>
						<th class="text-right"><?= AffiliateSearch::formatAmounts($total['commissions'][$status] ?? []) ?></th>
					<?php endforeach; ?>
				</tr>
			</tfoot>
		</table>
	</div>
	<p class="help-block">
		<?= Yii::t('common', 'Signups and conversions by signup date; active subscriptions by start date; revenue (VAT excluded, storno deducted) by payment date; commissions by the date they were earned. Amounts are per currency, never converted.') ?>
	</p>
<?php endif; ?>
