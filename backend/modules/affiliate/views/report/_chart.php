<?php
/* @var $this yii\web\View */
/* @var $id string unique id of the card */
/* @var $title string chart title (plain text) */
/* @var $panes array [['key' => string|null, 'finding' => string (HTML), 'chart' => string (HTML),
 *   'table' => ['caption' => string, 'head' => string[], 'rows' => string[][] (HTML), 'foot' => string[]|null]], ...];
 *   more than one pane = a currency switcher, the first pane shown */
/* @var $empty string message when there are no panes (plain text) */

use yii\helpers\Html;

// One chart card of the affiliate reports: title, a finding-style subtitle, the chart, the
// same numbers under "View as table" (the aqua slot is below 3:1 contrast) and an empty
// state. Several panes (one per currency) get a switcher: one currency per chart.
$panes = isset($panes) ? array_values($panes) : [];
$tableLabel = Yii::t('common', 'View as table');
// Text in ink colours (ChartHelper::TEXT), never a series colour.
$this->registerCss(<<<CSS
.affiliate-chart .panel-heading { position: relative; }
.affiliate-chart .affiliate-chart-title { display: inline-block; margin: 0; font-size: 16px; line-height: 22px; color: inherit; }
.affiliate-chart .btn-group { margin-left: 10px; }
.affiliate-chart .affiliate-chart-finding { color: #52514e; margin: 0 0 10px; }
.affiliate-chart .affiliate-chart-table { margin-top: 10px; }
.affiliate-chart .affiliate-chart-table summary { cursor: pointer; color: #52514e; }
.affiliate-chart .affiliate-chart-table .table { margin: 8px 0 0; }
.affiliate-chart .affiliate-chart-table th.text-normal { font-weight: normal; }
.affiliate-chart .text-break { word-break: break-word; }
CSS, [], 'affiliate-chart-css');

if (count($panes) > 1) {
	// A chart drawn while hidden has no size, so it is resized when shown.
	$this->registerJs(<<<JS
$(document).on('click', '[data-chart-currency-switch]', function () {
	var button = $(this), card = button.closest('[data-chart-switcher]');
	card.find('[data-chart-currency-switch]').removeClass('active').attr('aria-pressed', 'false');
	button.addClass('active').attr('aria-pressed', 'true');
	card.find('[data-chart-currency]').addClass('hidden');
	var pane = card.find('[data-chart-currency="' + button.data('chart-currency-switch') + '"]').removeClass('hidden').get(0);
	if (window.Chart && pane) {
		Chart.helpers.each(Chart.instances, function (chart) {
			if (pane.contains(chart.canvas)) {
				chart.resize();
				chart.update();
			}
		});
	}
});
JS, \yii\web\View::POS_READY, 'affiliate-chart-currency-switch');
}
?>
<section class="panel blue-hoki affiliate-chart" id="<?= Html::encode($id) ?>" aria-labelledby="<?= Html::encode($id) ?>-title"<?= count($panes) > 1 ? ' data-chart-switcher' : '' ?>>
	<div class="panel-title">
		<div class="panel-heading clearfix">
			<h3 class="affiliate-chart-title" id="<?= Html::encode($id) ?>-title"><?= Html::encode($title) ?></h3>
			<?php if (count($panes) > 1): ?>
				<div class="btn-group btn-group-xs pull-right" role="group" aria-label="<?= Html::encode(Yii::t('common', 'Chart currency')) ?>">
					<?php foreach ($panes as $i => $pane): ?>
						<?= Html::button(Html::encode($pane['key']), [
							'class' => 'btn btn-default' . ($i === 0 ? ' active' : ''),
							'aria-pressed' => $i === 0 ? 'true' : 'false',
							'data-chart-currency-switch' => $pane['key'],
						]) ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<div class="panel-body">
		<?php if (!$panes): ?>
			<div class="text-info-icon fa-info-circle affiliate-chart-empty"><?= Html::encode($empty) ?></div>
		<?php endif; ?>
		<?php foreach ($panes as $i => $pane): ?>
			<div<?= count($panes) > 1 ? ' data-chart-currency="' . Html::encode($pane['key']) . '"' . ($i === 0 ? '' : ' class="hidden"') : '' ?>>
				<?php if (!empty($pane['finding'])): ?>
					<p class="affiliate-chart-finding"><?= $pane['finding'] ?></p>
				<?php endif; ?>
				<?= $pane['chart'] ?>
				<?php if (!empty($pane['table'])): ?>
					<?php $table = $pane['table']; ?>
					<details class="affiliate-chart-table">
						<summary><?= Html::encode($tableLabel) ?></summary>
						<div class="table-responsive">
							<table class="table table-bordered table-condensed">
								<caption class="sr-only"><?= Html::encode($table['caption']) ?></caption>
								<thead>
									<tr>
										<?php foreach ($table['head'] as $c => $head): ?>
											<th scope="col"<?= $c ? ' class="text-right"' : '' ?>><?= Html::encode($head) ?></th>
										<?php endforeach; ?>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($table['rows'] as $row): ?>
										<tr>
											<?php foreach (array_values($row) as $c => $cell): ?>
												<?= $c ? '<td class="text-right">' . $cell . '</td>' : '<th scope="row" class="text-normal">' . $cell . '</th>' ?>
											<?php endforeach; ?>
										</tr>
									<?php endforeach; ?>
								</tbody>
								<?php if (!empty($table['foot'])): ?>
									<tfoot>
										<tr>
											<?php foreach (array_values($table['foot']) as $c => $cell): ?>
												<th<?= $c ? ' class="text-right"' : '' ?>><?= $cell ?></th>
											<?php endforeach; ?>
										</tr>
									</tfoot>
								<?php endif; ?>
							</table>
						</div>
					</details>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</section>
