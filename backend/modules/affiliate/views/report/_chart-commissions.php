<?php
/* @var $this yii\web\View */
/* @var $stats common\services\AffiliateStatsService with a complete period */
/* @var $id string card id */
/* @var $currency string|null only this currency (the page's filter); null = every currency, with a switcher */

use common\helpers\ChartHelper;
use common\models\AffiliateCommission;
use common\services\AffiliateStatsService;
use yii\helpers\Html;

// Commissions per month stacked by status, in fixed colours (paid blue, payable orange,
// pending aqua), one currency per chart.
$series = $stats->getMonthlyCommissions();
if (!empty($currency)) {
	$series = isset($series[$currency]) ? [$currency => $series[$currency]] : [];
}
$labels = $stats->getMonthLabels();
$titles = $stats->getMonthTitles();
$statusLabels = AffiliateCommission::getStatusLabels();
$statuses = AffiliateStatsService::chartStatuses();
$title = Yii::t('common', 'Commissions per month');
$panes = [];
foreach ($series as $code => $byStatus) {
	if (!ChartHelper::hasData(array_values($byStatus))) {
		continue;
	}
	$chartSeries = [];
	$head = [Yii::t('label', 'Month')];
	foreach ($statuses as $status) {
		$label = trim($statusLabels[$status]['label']);
		$chartSeries[] = ['label' => $label, 'data' => $byStatus[$status], 'color' => ChartHelper::statusColor($status)];
		$head[] = $label;
	}
	$head[] = Yii::t('common', 'Total');
	$rows = [];
	foreach ($titles as $m => $monthTitle) {
		$row = [Html::encode($monthTitle)];
		$sum = 0;
		foreach ($statuses as $status) {
			$sum += $byStatus[$status][$m];
			$row[] = Html::encode(ChartHelper::formatAmount($byStatus[$status][$m], $code));
		}
		$row[] = Html::encode(ChartHelper::formatAmount($sum, $code));
		$rows[] = $row;
	}
	$foot = [Yii::t('common', 'Total')];
	foreach ($statuses as $status) {
		$foot[] = Html::encode(ChartHelper::formatAmount(array_sum($byStatus[$status]), $code));
	}
	$total = array_sum(array_map('array_sum', $byStatus));
	$foot[] = Html::encode(ChartHelper::formatAmount($total, $code));
	$unpaid = array_sum($byStatus[AffiliateCommission::STATUS_PAYABLE]) + array_sum($byStatus[AffiliateCommission::STATUS_PENDING]);

	$panes[] = [
		'key' => $code,
		'finding' => Html::encode(Yii::t('common', '{total} in commissions, of which {unpaid} not paid yet', [
			'total' => ChartHelper::formatAmount($total, $code),
			'unpaid' => ChartHelper::formatAmount($unpaid, $code),
		])),
		'chart' => ChartHelper::widget(ChartHelper::bar($labels, $chartSeries, [
			'stacked' => true,
			'format' => ChartHelper::FORMAT_CURRENCY,
			'currency' => $code,
			'titles' => $titles,
			'totalLabel' => Yii::t('common', 'Total'),
		]), ['aria-label' => $title . ' (' . $code . ')']),
		'table' => [
			'caption' => $title . ' (' . $code . ')',
			'head' => $head,
			'rows' => $rows,
			'foot' => $foot,
		],
	];
}

echo $this->render('/report/_chart', [
	'id' => $id,
	'title' => $title,
	'panes' => $panes,
	'empty' => empty($currency)
		? Yii::t('common', 'No commissions in this period.')
		: Yii::t('common', 'No commissions in {currency} in this period.', ['currency' => $currency]),
]);
