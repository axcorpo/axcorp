<?php

/* @var $this yii\web\View */

use common\helpers\DateHelper;
use tws\widgets\chart\Chart;
use tws\helpers\Url;
use yii\helpers\Html;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Dashboard');
$this->params['breadcrumbs'][] = $this->title;
$this->params['bodyAttributes'] = [
	'class' => ['page-container-bg-solid'],
];
?>

<div class="row">
	<div class="col-lg-6">
		<div class="panel blue-hoki">
			<div class="panel-title">
				<div class="panel-heading"><?= Yii::t('common', 'Subscriptions') ?></div>
			</div>
			<div class="panel-body">
				<?php if (!empty($dataset['subscription'])): ?>
					<?= Chart::widget([
						'type' => Chart::TYPE_FLOT,
						'options' => [
							'style' => [
								'height' => '300px',
							],
						],
						'clientOptions' => [
							'type' => 'pie',
							'data' => $dataset['subscription'],
							'options' => [
								'series' => [
									'pie' => [
										'show' => true,
										'radius' => 1,
										'label' => [
											'show' => true,
											'radius' => 3/4,
											'formatter' => new JsExpression('function (label, series) {
												return $("<div/>", {
													"class": "chart-label",
													"html": Math.round(series.percent) + "%"
												}).prop("outerHTML");
											}'),
										],
									],
								],
								'legend' => [
									'show' => true,
									'position' => 'nw',
									'labelFormatter' => new JsExpression('function (label, series) {
										return $("<div/>", {
											"html": label + " (" + series.data[0][1] + ")"
										}).prop("outerHTML");
									}'),
								],
								'grid' => [
									'hoverable' => true,
									'clickable' => true,
								],
							],
						],
					]) ?>
				<?php else: ?>
					<div class="text-info-icon fa-info-circle"><?= Yii::t('common', 'No records found.') ?></div>
				<?php endif; ?>
			</div>
			<div class="panel-footer">
				<div class="row">
					<div class="col-sm-6">
						<a class="btn btn-default btn-block" href="<?= Url::to(['/subscriber-manager/subscriber/index']) ?>"><?= Yii::t('common', 'Go to') . ' ' . Yii::t('common', 'Subscribers') ?></a>
					</div>
					<div class="col-sm-6">
						<a class="btn btn-default btn-block" href="<?= Url::to(['/nomenclature-manager/package/index']) ?>"><?= Yii::t('common', 'Go to') . ' ' . Yii::t('common', 'Packages') ?></a>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="col-lg-6">
		<div class="panel blue-hoki">
			<div class="panel-title">
				<div class="panel-heading"><?= Yii::t('common', 'Monthly Subscriptions') ?></div>
			</div>
			<div class="panel-body">
				<?php if (!empty($dataset['subscriptionMonthly'])): ?>
					<?php
					$ticks = [];
					$monthsOfYear = DateHelper::getMonthsOfYear();
					foreach ($monthsOfYear as $monthNumber => $monthOfYear) {
						$ticks[] = [$monthNumber, $monthOfYear];
					}
					?>
					<?= Chart::widget([
						'type' => Chart::TYPE_FLOT,
						'options' => [
							'style' => [
								'height' => '300px',
							],
						],
						'clientOptions' => [
							'type' => 'pie',
							'data' => $dataset['subscriptionMonthly'],
							'options' => [
								'series' => [
									'lines' => [
										'show' => true,
										'fill' => 0.1,
									],
									'points' => [
										'show' => true,
										'radius' => 3,
									],
								],
								'yaxes' => [
									[
										'tickDecimals' => false,
										'axisLabel' => Yii::t('common', 'Subscriptions'),
										'axisLabelPadding' => 10,
										'axisLabelUseCanvas' => true,
									]
								],
								'xaxes' => [
									[
										'ticks' => $ticks,
										'tickDecimals' => false,
										'axisLabel' => Yii::t('common', 'Month'),
										'axisLabelPadding' => 20,
										'axisLabelUseCanvas' => true,
									]
								],
								'legend' => [
									'show' => true,
									'position' => 'nw',
								],
								'grid' => [
									'hoverable' => true,
									'clickable' => true,
									'borderWidth' => 0,
								],
							],
						],
					]) ?>
				<?php else: ?>
					<div class="text-info-icon fa-info-circle"><?= Yii::t('common', 'No records found.') ?></div>
				<?php endif; ?>
			</div>
			<div class="panel-footer">
				<div class="row">
					<div class="col-sm-6">
						<a class="btn btn-default btn-block" href="<?= Url::to(['/subscriber-manager/subscriber/index']) ?>"><?= Yii::t('common', 'Go to') . ' ' . Yii::t('common', 'Subscribers') ?></a>
					</div>
					<div class="col-sm-6">
						<a class="btn btn-default btn-block" href="<?= Url::to(['/nomenclature-manager/package/index']) ?>"><?= Yii::t('common', 'Go to') . ' ' . Yii::t('common', 'Packages') ?></a>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-lg-6">
		<div class="panel blue-hoki">
			<div class="panel-title">
				<div class="panel-heading"><?= Yii::t('common', 'Subscriptions Statuses') ?></div>
			</div>
			<div class="panel-body">
				<?php if (!empty($dataset['subscriptionStatus'])): ?>
					<?= Chart::widget([
						'type' => Chart::TYPE_FLOT,
						'options' => [
							'style' => [
								'height' => '300px',
							],
						],
						'clientOptions' => [
							'type' => 'pie',
							'data' => $dataset['subscriptionStatus'],
							'options' => [
								'series' => [
									'pie' => [
										'show' => true,
										'radius' => 1,
										'label' => [
											'show' => true,
											'radius' => 3/4,
											'formatter' => new JsExpression('function (label, series) {
												return $("<div/>", {
													"class": "chart-label",
													"html": Math.round(series.percent) + "%"
												}).prop("outerHTML");
											}'),
										],
									],
								],
								'legend' => [
									'show' => true,
									'position' => 'nw',
									'labelFormatter' => new JsExpression('function (label, series) {
										return $("<div/>", {
											"html": label + " (" + series.data[0][1] + ")"
										}).prop("outerHTML");
									}'),
								],
								'grid' => [
									'hoverable' => true,
									'clickable' => true,
								],
							],
						],
					]) ?>
				<?php else: ?>
					<div class="text-info-icon fa-info-circle"><?= Yii::t('common', 'No records found.') ?></div>
				<?php endif; ?>
			</div>
			<div class="panel-footer">
				<div class="row">
					<div class="col-sm-6">
						<a class="btn btn-default btn-block" href="<?= Url::to(['/subscriber-manager/subscriber/index']) ?>"><?= Yii::t('common', 'Go to') . ' ' . Yii::t('common', 'Subscribers') ?></a>
					</div>
					<div class="col-sm-6">
						<a class="btn btn-default btn-block" href="<?= Url::to(['/nomenclature-manager/package/index']) ?>"><?= Yii::t('common', 'Go to') . ' ' . Yii::t('common', 'Packages') ?></a>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="col-lg-6">
		<div class="panel blue-hoki">
			<div class="panel-title">
				<div class="panel-heading"><?= Yii::t('common', 'Workspaces') ?> / <?= Yii::t('common', 'Type') ?></div>
			</div>
			<div class="panel-body">
				<?php if (!empty($dataset['workspaceType'])): ?>
					<?= Chart::widget([
						'type' => Chart::TYPE_FLOT,
						'options' => [
							'style' => [
								'height' => '300px',
							],
						],
						'clientOptions' => [
							'type' => 'pie',
							'data' => $dataset['workspaceType'],
							'options' => [
								'series' => [
									'pie' => [
										'show' => true,
										'radius' => 1,
										'label' => [
											'show' => true,
											'radius' => 3/4,
											'formatter' => new JsExpression('function (label, series) {
												return $("<div/>", {
													"class": "chart-label",
													"html": Math.round(series.percent) + "%"
												}).prop("outerHTML");
											}'),
										],
									],
								],
								'legend' => [
									'show' => true,
									'position' => 'nw',
									'labelFormatter' => new JsExpression('function (label, series) {
										return $("<div/>", {
											"html": label + " (" + series.data[0][1] + ")"
										}).prop("outerHTML");
									}'),
								],
								'grid' => [
									'hoverable' => true,
									'clickable' => true,
								],
							],
						],
					]) ?>
				<?php else: ?>
					<div class="text-info-icon fa-info-circle"><?= Yii::t('common', 'No records found.') ?></div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
