<?php
/* @var $this yii\web\View */
/* @var $dashboard frontend\modules\account\models\AffiliateDashboard */

use common\helpers\ChartHelper;
use common\models\AffiliateCommission;
use yii\helpers\Html;

// The dashboard charts (last 12 months). Every chart has a table
// of the same numbers under "View as table": the aqua slot is below 3:1 contrast.
$formatter = Yii::$app->formatter;
$monthLabels = $dashboard->getChartMonthLabels();
$monthTitles = $dashboard->getChartMonthTitles();
$monthly = $dashboard->getMonthlySignups();
$commissions = $dashboard->getMonthlyCommissions();
$statusLabels = AffiliateCommission::getStatusLabels();
$statuses = [AffiliateCommission::STATUS_PAID, AffiliateCommission::STATUS_PAYABLE, AffiliateCommission::STATUS_PENDING];
$tableLabel = Yii::t('frontend', 'View as table');
// The currencies come largest 12-month total first: that one is shown by default.
$defaultCurrency = key($commissions);

// Currency switcher: one chart per currency, only the selected one visible. A chart
// drawn while hidden has no size, so it is resized when shown.
$this->registerJs(<<<JS
$(document).on('click', '[data-chart-currency-switch]', function () {
	var button = $(this), card = button.closest('[data-chart-switcher]');
	card.find('[data-chart-currency-switch]').removeClass('active').attr('aria-pressed', 'false');
	button.addClass('active').attr('aria-pressed', 'true');
	card.find('[data-chart-currency]').addClass('d-none');
	var pane = card.find('[data-chart-currency="' + button.data('chart-currency-switch') + '"]').removeClass('d-none').get(0);
	if (window.Chart && pane) {
		Chart.helpers.each(Chart.instances, function (chart) {
			if (pane.contains(chart.canvas)) {
				chart.resize();
				chart.update();
			}
		});
	}
});
JS);
?>

<h5 class="mt-4 mb-3"><?= Yii::t('frontend', 'Last 12 months') ?></h5>
<div class="row">
	<?php /* ------------------------------------------------- commissions per month */ ?>
	<div class="col-12 mb-3">
		<section class="card border-0 shadow-sm rounded p-3 h-100" data-chart-switcher aria-labelledby="chart-commissions-title">
			<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
				<h6 class="mb-0" id="chart-commissions-title"><?= Yii::t('frontend', 'Commissions per month') ?></h6>
				<?php if (count($commissions) > 1): ?>
					<div class="btn-group btn-group-sm" role="group" aria-label="<?= Html::encode(Yii::t('frontend', 'Chart currency')) ?>">
						<?php foreach (array_keys($commissions) as $currency): ?>
							<?php $selected = $currency === $defaultCurrency; ?>
							<?= Html::button(Html::encode($currency), [
								'class' => 'btn btn-outline-secondary' . ($selected ? ' active' : ''),
								'aria-pressed' => $selected ? 'true' : 'false',
								'data-chart-currency-switch' => $currency,
							]) ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
			<?php if ($commissions): ?>
				<?php foreach ($commissions as $currency => $byStatus): ?>
					<?php
					$series = [];
					foreach ($statuses as $status) {
						$series[] = ['label' => trim($statusLabels[$status]['label']), 'data' => $byStatus[$status], 'color' => ChartHelper::statusColor($status)];
					}
					$total = array_sum(array_map('array_sum', $byStatus));
					?>
					<div data-chart-currency="<?= Html::encode($currency) ?>"<?= $currency === $defaultCurrency ? '' : ' class="d-none"' ?>>
						<p class="text-muted small mb-2"><?= Yii::t('frontend', '{amount} in commissions in the last 12 months', ['amount' => $formatter->asCurrency($total, $currency)]) ?></p>
						<?= ChartHelper::widget(ChartHelper::bar($monthLabels, $series, [
							'stacked' => true,
							'format' => ChartHelper::FORMAT_CURRENCY,
							'currency' => $currency,
							'titles' => $monthTitles,
							'totalLabel' => Yii::t('label', 'Total'),
						]), ['aria-label' => Yii::t('frontend', 'Commissions per month') . ' (' . $currency . ')'], 300) ?>
						<details class="mt-2">
							<summary class="small"><?= Html::encode($tableLabel) ?></summary>
							<div class="table-responsive mt-2">
								<table class="table table-sm table-bordered mb-0">
									<caption class="visually-hidden"><?= Html::encode(Yii::t('frontend', 'Commissions per month') . ' (' . $currency . ')') ?></caption>
									<thead>
										<tr>
											<th scope="col"><?= Yii::t('label', 'Month') ?></th>
											<?php foreach ($statuses as $status): ?>
												<th scope="col" class="text-end"><?= Html::encode(trim($statusLabels[$status]['label'])) ?></th>
											<?php endforeach; ?>
											<th scope="col" class="text-end"><?= Yii::t('label', 'Total') ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($monthTitles as $m => $title): ?>
											<tr>
												<th scope="row" class="fw-normal"><?= Html::encode($title) ?></th>
												<?php $rowTotal = 0; foreach ($statuses as $status): $rowTotal += $byStatus[$status][$m]; ?>
													<td class="text-end"><?= $formatter->asCurrency($byStatus[$status][$m], $currency) ?></td>
												<?php endforeach; ?>
												<td class="text-end"><?= $formatter->asCurrency($rowTotal, $currency) ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</details>
					</div>
				<?php endforeach; ?>
			<?php else: ?>
				<p class="text-muted mb-0"><?= Yii::t('frontend', 'No commissions in the last 12 months yet.') ?></p>
			<?php endif; ?>
		</section>
	</div>

	<?php /* ---------------------------------------- signups and conversions per month */ ?>
	<div class="col-12 mb-3">
		<section class="card border-0 shadow-sm rounded p-3 h-100" aria-labelledby="chart-signups-title">
			<h6 class="mb-0" id="chart-signups-title"><?= Yii::t('frontend', 'Signups and conversions per month') ?></h6>
			<?php if (ChartHelper::hasData([$monthly['signups'], $monthly['conversions']])): ?>
				<p class="text-muted small mb-2"><?= Yii::t('frontend', '{signups, plural, =1{1 signup} other{# signups}} and {conversions, plural, =1{1 conversion} other{# conversions}} in the last 12 months', [
					'signups' => array_sum($monthly['signups']),
					'conversions' => array_sum($monthly['conversions']),
				]) ?></p>
				<?= ChartHelper::widget(ChartHelper::line($monthLabels, [
					['label' => Yii::t('frontend', 'Signups'), 'data' => $monthly['signups'], 'color' => ChartHelper::BLUE],
					['label' => Yii::t('frontend', 'Converted (first paid invoice)'), 'data' => $monthly['conversions'], 'color' => ChartHelper::ORANGE],
				], ['titles' => $monthTitles]), ['aria-label' => Yii::t('frontend', 'Signups and conversions per month')]) ?>
				<details class="mt-2">
					<summary class="small"><?= Html::encode($tableLabel) ?></summary>
					<div class="table-responsive mt-2">
						<table class="table table-sm table-bordered mb-0">
							<caption class="visually-hidden"><?= Yii::t('frontend', 'Signups and conversions per month') ?></caption>
							<thead>
								<tr>
									<th scope="col"><?= Yii::t('label', 'Month') ?></th>
									<th scope="col" class="text-end"><?= Yii::t('frontend', 'Signups') ?></th>
									<th scope="col" class="text-end"><?= Yii::t('frontend', 'Converted (first paid invoice)') ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($monthTitles as $m => $title): ?>
									<tr>
										<th scope="row" class="fw-normal"><?= Html::encode($title) ?></th>
										<td class="text-end"><?= $formatter->asInteger($monthly['signups'][$m]) ?></td>
										<td class="text-end"><?= $formatter->asInteger($monthly['conversions'][$m]) ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</details>
			<?php else: ?>
				<p class="text-muted mt-2 mb-0"><?= Yii::t('frontend', 'No signups in the last 12 months yet.') ?></p>
			<?php endif; ?>
		</section>
	</div>
</div>
