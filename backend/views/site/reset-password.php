<?php

/* @var $this yii\web\View */
/* @var $form yii\bootstrap\ActiveForm */
/* @var $model common\models\ResetPasswordForm */

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

$this->title = Yii::t('common', 'Reset Password');
?>

<?php $form = ActiveForm::begin([
	'id' => 'reset-password-form',
	'validateOnType' => true,
]); ?>

	<?php $secretFieldTemplate = '{label}<div class="input-secret">{input}<span class="toggle-secret fa fa-eye-slash"></span></div>{hint}{error}'; ?>
	<?= $form->field($model, 'password', ['template' => $secretFieldTemplate])->passwordInput(['autofocus' => true])->label(Yii::t('common', 'Please enter your new password')) ?>

	<div class="form-actions">
		<?= Html::submitButton(Yii::t('common', 'Save'), ['class' => 'btn btn-block btn-warning']) ?>
	</div>

	<div class="form-links text-center">
		<?= Html::a(Yii::t('common', 'Log In'), ['/site/login']) ?>
	</div>

<?php ActiveForm::end(); ?>
