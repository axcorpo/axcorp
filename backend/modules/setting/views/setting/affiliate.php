<?php

/* @var $this yii\web\View */
/* @var $model backend\modules\setting\models\AffiliateSettingForm */
/* @var $form backend\widgets\ActiveForm */

use backend\widgets\ActiveForm;
use kartik\number\NumberControl;
use kartik\touchspin\TouchSpin;
use yii\helpers\Html;

$this->title = Yii::t('backend', 'Affiliate Settings');
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Settings'),
		'url' => ['index'],
	],
	$this->title,
];
?>

<?php $form = ActiveForm::begin([
	'options' => [
		'novalidate' => true,
	],
	'validateOnType' => true,
]); ?>
	<div class="row">
		<div class="col-sm-6 col-md-3">
			<?= $form->field($model, 'holdDays')->widget(TouchSpin::class, [
				'pluginOptions' => [
					'min' => 0,
					'max' => 365,
					'step' => 1,
					'decimals' => 0,
					'verticalbuttons' => true,
					'postfix' => mb_strtolower(Yii::t('label', 'Days')),
				],
			])->hint(Yii::t('backend', 'Days a new commission stays pending before it becomes payable, so a storno can still cancel it.')) ?>
		</div>
		<div class="col-sm-6 col-md-3">
			<?= $form->field($model, 'defaultPercentage')->widget(TouchSpin::class, [
				'pluginOptions' => [
					'min' => 0,
					'max' => 100,
					'step' => 0.5,
					'decimals' => 2,
					'verticalbuttons' => true,
					'postfix' => '%',
				],
			])->hint(Yii::t('backend', 'Proposed for new affiliates; each affiliate keeps their own percentage.')) ?>
		</div>
		<div class="col-sm-6 col-md-3">
			<?php // A money amount: the masked input of the package / feature prices (nomenclature/package/_form.php). ?>
			<?= $form->field($model, 'minimumPayout')->widget(NumberControl::class, [
				'maskedInputOptions' => [
					'groupSeparator' => '.',
					'radixPoint' => ',',
					'allowMinus' => false,
				],
				'displayOptions' => [
					'autocomplete' => 'off',
					'placeholder' => '0,00',
				],
			])->hint(Yii::t('backend', 'Smallest payable total (in each currency) a payout can be created for; 0 = no minimum.')) ?>
		</div>
		<div class="col-sm-6 col-md-3">
			<?= $form->field($model, 'cookieDays')->widget(TouchSpin::class, [
				'pluginOptions' => [
					'min' => 1,
					'max' => 365,
					'step' => 1,
					'decimals' => 0,
					'verticalbuttons' => true,
					'postfix' => mb_strtolower(Yii::t('label', 'Days')),
				],
			])->hint(Yii::t('backend', 'How long a visitor who came through a referral link (?aff=CODE) is still attributed to the affiliate when signing up.')) ?>
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
