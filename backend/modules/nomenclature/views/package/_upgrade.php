<?php
/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\Package */

use common\models\Feature;
use common\models\Package;
use backend\widgets\ActiveForm;
use common\models\FeatureModule;
use common\models\ScheduledTask;
use kartik\number\NumberControl;
use kartik\select2\Select2;
use kartik\touchspin\TouchSpin;
use yii\bootstrap\Tabs;
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
	<div class="panel blue-hoki">
		<div class="panel-title">
			<div class="panel-heading"><?= Yii::t('label', 'Features') ?></div>
		</div>
		<div class="panel-body">
			<div class="row">
				<div class="col-sm-6 col-lg-3">
					<?= $form->field($model, Feature::WORKSPACES)->widget(TouchSpin::class, [
						'pluginOptions' => [
							'min' => 1,
							'max' => PHP_INT_MAX,
							'step' => 1,
							'decimals' => 0,
							'boostat' => 5,
							'maxboostedstep' => 10,
							'verticalbuttons' => true,
						],
					]) ?>
				</div>
				<div class="col-sm-6 col-lg-3">
					<?= $form->field($model, Feature::USERS)->widget(TouchSpin::class, [
						'pluginOptions' => [
							'min' => 1,
							'max' => PHP_INT_MAX,
							'step' => 1,
							'decimals' => 0,
							'boostat' => 5,
							'maxboostedstep' => 10,
							'verticalbuttons' => true,
						],
					]) ?>
				</div>
			</div>
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
