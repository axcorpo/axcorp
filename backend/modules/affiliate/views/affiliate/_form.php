<?php
/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model backend\modules\affiliate\models\AffiliateForm */

use backend\widgets\ActiveForm;
use borales\extensions\phoneInput\PhoneInput;
use common\components\PhoneInputConfig;
use common\models\Affiliate;
use common\models\User;
use kartik\select2\Select2;
use kartik\touchspin\TouchSpin;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

?>

<?php $form = ActiveForm::begin([
	'id' => 'affiliate-form',
	'options' => [
		'novalidate' => true,
	],
	'validateOnType' => true,
]); ?>
<div class="form-body">
	<div class="form-fields">
		<?php if ($model->hasErrors() && empty(array_intersect_key($model->getErrors(), array_flip($model->safeAttributes())))): ?>
			<?= $form->errorSummary($model, [
				'header' => false,
				'class' => 'alert alert-danger alert-icon',
			]) ?>
		<?php endif; ?>

		<div class="panel blue-hoki">
			<div class="panel-title">
				<div class="panel-heading"><?= Yii::t('common', 'Account') ?></div>
			</div>
			<div class="panel-body">
				<?php if ($model->isNewRecord): ?>
					<p class="help-block"><?= Yii::t('common', 'A user account is created for the affiliate, without any administration role. If the e-mail already belongs to a front-end account, that account is linked instead.') ?></p>
				<?php endif; ?>
				<div class="row">
					<div class="col-sm-4">
						<?= $form->field($model, 'email')->input('email') ?>
					</div>
					<div class="col-sm-4">
						<?= $form->field($model, 'phone')->widget(PhoneInput::class, [
							'jsOptions' => PhoneInputConfig::jsOptions(),
							'options' => PhoneInputConfig::inputClassOptions(),
						]) ?>
					</div>
					<div class="col-sm-4">
						<?= $form->field($model, 'gender')->inline()->radioList(User::getGenderLabels()) ?>
					</div>
				</div>
				<div class="row">
					<div class="col-sm-4">
						<?= $form->field($model, 'last_name')->textInput() ?>
					</div>
					<div class="col-sm-4">
						<?= $form->field($model, 'first_name')->textInput() ?>
					</div>
					<div class="col-sm-4">
						<?= $form->field($model, 'middle_name')->textInput() ?>
					</div>
				</div>
			</div>
		</div>

		<div class="panel blue-hoki">
			<div class="panel-title">
				<div class="panel-heading"><?= Yii::t('common', 'Affiliate') ?></div>
			</div>
			<div class="panel-body">
				<div class="row">
					<div class="col-sm-3">
						<?= $form->field($model, 'code')->textInput(['maxlength' => 32, 'style' => 'text-transform: uppercase;'])
							->hint(Yii::t('backend', 'Used in the referral links (?aff=CODE). 4 to 32 letters or digits.')) ?>
					</div>
					<div class="col-sm-3">
						<?= $form->field($model, 'commission_percentage')->widget(TouchSpin::class, [
							'pluginOptions' => [
								'min' => 0,
								'max' => 100,
								'step' => 0.5,
								'decimals' => 2,
								'verticalbuttons' => true,
								'postfix' => '%',
							],
						]) ?>
					</div>
					<div class="col-sm-3">
						<?= $form->field($model, 'commission_months')->widget(TouchSpin::class, [
							'options' => [
								'placeholder' => Yii::t('common', 'Lifetime'),
							],
							'pluginOptions' => [
								'min' => 1,
								'max' => 600,
								'step' => 1,
								'decimals' => 0,
								'verticalbuttons' => true,
								'postfix' => mb_strtolower(Yii::t('label', 'Months')),
							],
						])->hint(Yii::t('backend', 'Empty = commission for the whole lifetime of the subscriber.')) ?>
					</div>
					<div class="col-sm-3">
						<?= $form->field($model, 'status')->widget(Select2::class, [
							'data' => ArrayHelper::getColumn(Affiliate::getStatusLabels(), 'label'),
							'pluginLoading' => false,
							'pluginOptions' => [
								'allowClear' => false,
								'placeholder' => Yii::t('common', 'Choose'),
							],
						]) ?>
					</div>
				</div>
				<?= $form->field($model, 'notes')->textarea(['rows' => 3]) ?>
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
</div>
<?php ActiveForm::end(); ?>
