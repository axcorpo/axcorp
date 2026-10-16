<?php
/* @var $this yii\web\View */
/* @var $stats common\services\AffiliateStatsService with a complete period */
/* @var $id string card id */

use common\helpers\ChartHelper;
use yii\helpers\Html;

// Signups and conversions per month (line, 2 series: signups blue, conversions orange).
$formatter = Yii::$app->formatter;
$monthly = $stats->getMonthlySignups();
$titles = $stats->getMonthTitles();
$title = Yii::t('common', 'Signups and conversions per month');
$panes = [];
if (ChartHelper::hasData([$monthly['signups'], $monthly['conversions']])) {
	$best = array_search(max($monthly['signups']), $monthly['signups'], true);
	$finding = Yii::t('common', '{signups, plural, =1{1 signup} other{# signups}} and {conversions, plural, =1{1 conversion} other{# conversions}}', [
		'signups' => array_sum($monthly['signups']),
		'conversions' => array_sum($monthly['conversions']),
	]);
	if ($monthly['signups'][$best] > 0) {
		$finding .= '; ' . Yii::t('common', 'most signups in {month}', ['month' => $titles[$best]]);
	}
	$rows = [];
	foreach ($titles as $m => $monthTitle) {
		$rows[] = [Html::encode($monthTitle), $formatter->asInteger($monthly['signups'][$m]), $formatter->asInteger($monthly['conversions'][$m])];
	}
	$panes[] = [
		'key' => null,
		'finding' => Html::encode($finding),
		'chart' => ChartHelper::widget(ChartHelper::line($stats->getMonthLabels(), [
			['label' => Yii::t('common', 'Signups'), 'data' => $monthly['signups'], 'color' => ChartHelper::BLUE],
			['label' => Yii::t('common', 'Conversions (first paid invoice)'), 'data' => $monthly['conversions'], 'color' => ChartHelper::ORANGE],
		], ['titles' => $titles]), ['aria-label' => $title]),
		'table' => [
			'caption' => $title,
			'head' => [Yii::t('label', 'Month'), Yii::t('common', 'Signups'), Yii::t('common', 'Conversions (first paid invoice)')],
			'rows' => $rows,
			'foot' => [Yii::t('common', 'Total'), $formatter->asInteger(array_sum($monthly['signups'])), $formatter->asInteger(array_sum($monthly['conversions']))],
		],
	];
}

echo $this->render('/report/_chart', [
	'id' => $id,
	'title' => $title,
	'panes' => $panes,
	'empty' => Yii::t('common', 'No signups or conversions in this period.'),
]);
