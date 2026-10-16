<?php
/* @var $this yii\web\View */
/* @var $model backend\modules\affiliate\models\AffiliateReportForm */
/* @var $rows array AffiliateReportForm::getLeaderboard() */

use backend\modules\affiliate\models\AffiliateSearch;
use common\helpers\ChartHelper;
use common\models\AffiliateCommission;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = Yii::t('common', 'Affiliate Leaderboard');
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

$currency = $model->currency;
$currencies = $model->getCurrencies($rows);
if ($currency && !in_array($currency, $currencies, true)) {
	$currencies[] = $currency;
}
?>

<?= $this->render('_filters', [
	'model' => $model,
	'action' => 'leaderboard',
	'showGroup' => false,
	'currencies' => $currencies,
]) ?>

<hr/>

<?php
// Net revenue in the chosen currency: one series (blue), the 10 best affiliates and the
// rest folded into "Other"; clicking a bar opens the affiliate.
$title = Yii::t('common', 'Top affiliates by net revenue');
$limit = 10;
$labels = [];
$amounts = [];
$links = [];
if ($currency) {
	foreach ($rows as $i => $row) {
		$amount = (float) ($row['revenue'][$currency] ?? 0);
		if ($i < $limit) {
			$labels[] = $row['label'];
			$amounts[] = round($amount, 2);
			$links[] = !empty($row['uuid']) ? Url::to(['affiliate/view', 'id' => $row['uuid']]) : null;
		} else {
			$amounts[$limit] = round(($amounts[$limit] ?? 0) + $amount, 2);
		}
	}
	if (count($rows) > $limit) {
		$labels[] = Yii::t('common', 'Other');
		$links[] = null;
	}
}
$panes = [];
if (ChartHelper::hasData([$amounts])) {
	$total = array_sum($amounts);
	$tableRows = [];
	foreach ($labels as $i => $label) {
		$tableRows[] = [
			$links[$i] ? Html::a(Html::encode($label), $links[$i]) : Html::encode($label),
			Html::encode(ChartHelper::formatAmount($amounts[$i], $currency)),
		];
	}
	$panes[] = [
		'key' => $currency,
		'finding' => Html::encode(Yii::t('common', '{affiliate} brought the most: {amount} of {total}', [
			'affiliate' => $labels[0],
			'amount' => ChartHelper::formatAmount($amounts[0], $currency),
			'total' => ChartHelper::formatAmount($total, $currency),
		])),
		'chart' => ChartHelper::widget(ChartHelper::bar($labels, [
			['label' => Yii::t('common', 'Revenue (net)'), 'data' => $amounts, 'color' => ChartHelper::BLUE],
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
			'rows' => $tableRows,
			'foot' => [Yii::t('common', 'Total'), Html::encode(ChartHelper::formatAmount($total, $currency))],
		],
	];
}
?>
<?= $this->render('_chart', [
	'id' => 'chart-leaderboard',
	'title' => $title,
	'panes' => $panes,
	'empty' => Yii::t('common', 'No revenue in this period.'),
]) ?>

<?php if ($rows): ?>
	<div class="table-responsive">
		<table class="table table-bordered table-hover table-condensed">
			<thead>
				<tr>
					<th class="col-autowidth">#</th>
					<th><?= Yii::t('label', 'Affiliate') ?></th>
					<th class="text-right"><?= Yii::t('common', 'Revenue (net)') ?> <?= Html::encode($currency) ?></th>
					<th class="text-right"><?= Yii::t('common', 'Signups') ?></th>
					<th class="text-right"><?= Yii::t('common', 'Conversions') ?></th>
					<th class="text-right"><?= Yii::t('common', 'Active Subscriptions') ?></th>
					<th class="text-right"><?= Yii::t('common', 'Revenue (net)') ?></th>
					<th class="text-right"><?= Yii::t('common', 'Commissions') ?> &middot; <?= Yii::t('label', 'Payable') ?></th>
					<th class="text-right"><?= Yii::t('common', 'Commissions') ?> &middot; <?= Yii::t('label', 'Paid') ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($rows as $i => $row): ?>
					<tr>
						<td><?= $i + 1 ?></td>
						<td>
							<?= !empty($row['uuid'])
								? Html::a(Html::encode($row['label']), ['affiliate/view', 'id' => $row['uuid']])
								: Html::encode($row['label']) ?>
						</td>
						<td class="text-right"><strong><?= Yii::$app->formatter->asDecimal($row['revenue'][$currency] ?? 0, 2) ?></strong></td>
						<td class="text-right"><?= $row['signups'] ?></td>
						<td class="text-right"><?= $row['conversions'] ?></td>
						<td class="text-right"><?= $row['activeSubscriptions'] ?></td>
						<td class="text-right"><?= AffiliateSearch::formatAmounts($row['revenue']) ?></td>
						<td class="text-right"><?= AffiliateSearch::formatAmounts($row['commissions'][AffiliateCommission::STATUS_PAYABLE] ?? []) ?></td>
						<td class="text-right"><?= AffiliateSearch::formatAmounts($row['commissions'][AffiliateCommission::STATUS_PAID] ?? []) ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
