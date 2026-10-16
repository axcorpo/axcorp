<?php
/* @var $this yii\web\View */
/* @var $model backend\modules\subscriber\modules\report\models\SubscriptionReportForm */

use backend\widgets\ActiveForm;
use tws\widgets\chart\Chart;
use tws\widgets\datetimepicker\DateTimePicker;
use yii\helpers\Html;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Subscriptions');
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Subscribers'),
		'url' => ['/subscriber-manager/subscriber/index'],
	],
	[
		'label' => Yii::t('common', 'Reports'),
		'url' => ['default/index'],
	],
	$this->title,
];

$dataset = $model->getDataset();
?>

<?php $form = ActiveForm::begin([
	'id' => 'subscription-report',
	'method' => 'GET',
	'action' => ['subscription'],
	'options' => [
		'novalidate' => true,
	],
	'validateOnType' => true,
]); ?>
	<div class="row">
		<div class="col-sm-4">
			<?= $form->field($model, 'from')->widget(DateTimePicker::class, [
				'id' => 'dp-from',
				'options' => [
					'value' => $model->from ? Yii::$app->formatter->asDatetime($model->from) : null,
					'placeholder' => Yii::$app->settings->get('datetimeFormat'),
				],
				'clientOptions' => [
					'format' => 'icu:' . Yii::$app->settings->get('datetimeFormat'),
					'maxDate' => (new \DateTime)->setTime(23, 59, 59)->format(DATE_ATOM),
					'ignoreReadonly' => true,
					'showTodayButton' => true,
					'showClear' => true,
					'showClose' => true,
					'allowInputToggle' => true,
					'useCurrent' => false,
				],
			]) ?>
		</div>
		<div class="col-sm-4">
			<?= $form->field($model, 'to')->widget(DateTimePicker::class, [
				'options' => [
					'value' => $model->to ? Yii::$app->formatter->asDatetime($model->to) : null,
					'placeholder' => Yii::$app->settings->get('datetimeFormat'),
				],
				'linkedTo' => '#dp-from',
				'clientOptions' => [
					'format' => 'icu:' . Yii::$app->settings->get('datetimeFormat'),
					'minDate' => $model->from ? (new \DateTime($model->from))->format(DATE_ATOM) : false,
					'maxDate' => (new DateTime)->setTime(23, 59, 59)->format(DATE_ATOM),
					'ignoreReadonly' => true,
					'showTodayButton' => true,
					'showClear' => true,
					'showClose' => true,
					'allowInputToggle' => true,
					'useCurrent' => false,
				],
			]) ?>
		</div>
	</div>

	<hr/>

	<div class="panel blue-hoki">
		<div class="panel-body">
			<?php if (!empty($dataset)): ?>
				<?= Chart::widget([
					'type' => Chart::TYPE_FLOT,
					'options' => [
						'style' => [
							'height' => '300px',
						],
					],
					'clientOptions' => [
						'type' => 'pie',
						'data' => $dataset,
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

	<div class="form-actions floating">
		<?= Html::submitButton('<span class="fa fa-filter"></span>', [
			'class' => 'btn btn-xlg btn-fab btn-success',
			'title' => Yii::t('common', 'Filtration'),
			'data' => [
				'toggle' => 'tooltip',
			],
		]) ?>
	</div>
<?php ActiveForm::end(); ?>
