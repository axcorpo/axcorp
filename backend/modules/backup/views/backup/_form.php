<?php
/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\Backup */

use common\models\Backup;
use backend\widgets\ActiveForm;
use kartik\select2\Select2;
use kartik\touchspin\TouchSpin;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
?>

<?php $form = ActiveForm::begin([
	'id' => mb_strtolower($model->formName()),
	'options' => [
		'novalidate' => true,
	],
	'validateOnType' => true,
]); ?>
	<div class="row">
		<div class="col-sm-4">
			<?= $form->field($model, 'status')->widget(Select2::class, [
				'data' => ArrayHelper::getColumn(Backup::getStatusLabels(), 'label'),
				'pluginLoading' => false,
				'pluginOptions' => [
					'allowClear' => false,
					'placeholder' => Yii::t('common', 'Choose'),
				],
			]) ?>
		</div>
		<div class="col-sm-4">
			<?= $form->field($model, 'type')->widget(Select2::class, [
				'data' => Backup::getTypeLabels(),
				'pluginLoading' => false,
				'pluginOptions' => [
					'allowClear' => false,
					'placeholder' => Yii::t('common', 'Choose'),
				],
			]) ?>
		</div>
		<div class="col-sm-6">
			<?php $cycleField = $form->field($model, 'cycle', [
				'options' => [
					'class' => 'col-xs-6',
				],
				'template' => '{input}',
			])->widget(Select2::class, [
				'data' => \common\models\ScheduledTask::getCycleLabels('adjective'),
				'pluginLoading' => false,
				'pluginOptions' => [
					'placeholder' => Yii::t('common', 'Choose'),
				],
			]) ?>
			<?= $form->field($model, 'frequency', [
				'template' => '{label}<div class="row row-no-gutters row-custom"><div class="col-xs-6">{input}</div><div class="col-addon">/</div>' . $cycleField . '</div>{error}{hint}',
			])->widget(TouchSpin::class, [
				'pluginOptions' => [
					'min' => 1,
					'max' => PHP_INT_MAX,
					'step' => 1,
					'decimals' => 0,
					'boostat' => 5,
					'maxboostedstep' => 10,
					'verticalbuttons' => true,
					'postfix' => mb_strtolower($model->frequency == 1 ? Yii::t('label', 'Message') : Yii::t('label', 'Messages')),
				],
			])->hint(Yii::t('backend', 'The number of messages to be sent at an interval.')) ?>
		</div>
	</div>

	<div class="form-actions floating">
		<?= Html::submitButton('<span class="fa fa-check"></span>', [
			'class' => 'btn btn-xlg btn-fab btn-success',
			'title' => Yii::t('common', 'Save'),
			'data' => [
				'toggle' => 'tooltip',
			],
		]) ?>
	</div>
<?php ActiveForm::end(); ?>
