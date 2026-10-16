<?php
/* @var $this yii\web\View */
/* @var $model backend\modules\subscriber\modules\report\models\SubscriptionMonthlyReportForm */

use backend\widgets\ActiveForm;
use common\helpers\DateHelper;
use kartik\touchspin\TouchSpin;
use tws\widgets\chart\Chart;
use yii\helpers\Html;

$this->title = Yii::t('common', 'Monthly Subscriptions');
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
	'id' => 'subscription-monthly-report',
	'method' => 'GET',
	'action' => ['subscription-monthly'],
	'options' => [
		'novalidate' => true,
	],
	'validateOnType' => true,
]); ?>
	<div class="row">
		<div class="col-sm-4">
			<?= $form->field($model, 'year')->widget(TouchSpin::class, [
				'pluginOptions' => [
					'min' => 1900,
					'max' => (new \DateTime)->format('Y'),
					'step' => 1,
					'decimals' => 0,
					'boostat' => 5,
					'maxboostedstep' => 10,
					'verticalbuttons' => true,
				],
			]) ?>
		</div>
	</div>

	<hr/>

	<div class="panel blue-hoki">
		<div class="panel-body">
			<?php if (!empty($dataset)): ?>
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
						'data' => $dataset,
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
