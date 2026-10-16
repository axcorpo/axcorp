<?php
/* @var $this yii\web\View */
/* @var $model backend\modules\affiliate\models\AffiliateReportForm */
/* @var $action string */
/* @var $showGroup bool */
/* @var $currencies string[]|null currencies the page can show (leaderboard, dashboard) */
/* @var $showAffiliate bool|null the affiliate filter, default with the group one */

use backend\modules\affiliate\models\AffiliateReportForm;
use backend\widgets\ActiveForm;
use common\helpers\DateHelper;
use common\models\Affiliate;
use kartik\select2\Select2;
use tws\widgets\datetimepicker\DateTimePicker;
use yii\helpers\Html;

$affiliates = [];
foreach (Affiliate::find()->with('user')->where(['deleted' => Affiliate::NO])->orderBy(['code' => SORT_ASC])->all() as $affiliate) {
	$affiliates[$affiliate->uuid] = trim(($affiliate->user ? $affiliate->user->getFullName() : '') . " ({$affiliate->code})");
}
$showAffiliate = isset($showAffiliate) ? $showAffiliate : !empty($showGroup);

// The period pickers, in the `dateFormat` setting as the list date filters. An invalid value
// is shown back as typed (with its error) — the formatter would throw on it.
$dateFormat = Yii::$app->settings->get('dateFormat');
$from = DateHelper::parseDateTime($model->from);
$to = DateHelper::parseDateTime($model->to);
$maxDate = (new \DateTime)->setTime(23, 59, 59)->format(DATE_ATOM);
?>

<?php $form = ActiveForm::begin([
	'id' => 'affiliate-report',
	'method' => 'GET',
	'action' => [$action],
	'options' => [
		'novalidate' => true,
	],
]); ?>
	<div class="row">
		<div class="col-sm-6 col-md-3">
			<?= $form->field($model, 'from')->widget(DateTimePicker::class, [
				'id' => 'dp-from',
				'options' => [
					'value' => $from ? Yii::$app->formatter->asDate($from) : $model->from,
					'placeholder' => $dateFormat,
				],
				'clientOptions' => [
					'format' => 'icu:' . $dateFormat,
					'maxDate' => $maxDate,
					'ignoreReadonly' => true,
					'showTodayButton' => true,
					'showClear' => true,
					'showClose' => true,
					'allowInputToggle' => true,
					'useCurrent' => false,
				],
			]) ?>
		</div>
		<div class="col-sm-6 col-md-3">
			<?= $form->field($model, 'to')->widget(DateTimePicker::class, [
				'options' => [
					'value' => $to ? Yii::$app->formatter->asDate($to) : $model->to,
					'placeholder' => $dateFormat,
				],
				'linkedTo' => '#dp-from',
				'clientOptions' => [
					'format' => 'icu:' . $dateFormat,
					'minDate' => $from ? (new \DateTime($from))->format(DATE_ATOM) : false,
					'maxDate' => $maxDate,
					'ignoreReadonly' => true,
					'showTodayButton' => true,
					'showClear' => true,
					'showClose' => true,
					'allowInputToggle' => true,
					'useCurrent' => false,
				],
			]) ?>
		</div>
		<?php if (!empty($showGroup)): ?>
			<div class="col-sm-6 col-md-2">
				<?= $form->field($model, 'group')->widget(Select2::class, [
					'data' => AffiliateReportForm::getGroupLabels(),
					'pluginLoading' => false,
					'pluginOptions' => [
						'allowClear' => false,
					],
				]) ?>
			</div>
		<?php endif; ?>
		<?php if ($showAffiliate): ?>
			<div class="col-sm-6 col-md-3">
				<?= $form->field($model, 'affiliate_id')->widget(Select2::class, [
					'data' => $affiliates,
					'pluginLoading' => false,
					'pluginOptions' => [
						'allowClear' => true,
						'placeholder' => Yii::t('common', 'All'),
					],
				]) ?>
			</div>
		<?php endif; ?>
		<?php if (!empty($currencies)): ?>
			<div class="col-sm-6 col-md-2">
				<?= $form->field($model, 'currency')->widget(Select2::class, [
					'data' => array_combine($currencies, $currencies),
					'pluginLoading' => false,
					'pluginOptions' => [
						'allowClear' => false,
					],
				]) ?>
			</div>
		<?php endif; ?>
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
