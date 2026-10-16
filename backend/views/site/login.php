<?php

/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\LoginForm */

use backend\widgets\ActiveForm;
use yii\helpers\Html;

$this->title = Yii::t('common', 'Authentication');
?>

<?php $form = ActiveForm::begin([
	'id' => 'login-form',
	'validateOnType' => true,
]); ?>

	<?= $form->field($model, 'username')->textInput(['autofocus' => true])->label(Yii::t('label', 'Email')) ?>

	<?php $secretFieldTemplate = '{label}<div class="input-secret">{input}<span class="toggle-secret fa fa-eye-slash"></span></div>{hint}{error}'; ?>
	<?= $form->field($model, 'password', ['template' => $secretFieldTemplate])->passwordInput() ?>

	<?= $form->field($model, 'rememberMe')->checkbox() ?>

	<div class="form-actions">
		<?= Html::submitButton('<span class="fa fa-sign-in"></span> ' . Yii::t('common', 'Log In'), ['class' => 'btn btn-block btn-warning']) ?>
	</div>

	<div class="form-links text-center">
		<?= Html::a(Yii::t('common', 'Reset your password'), ['/site/reset-password']) ?>
	</div>

<?php ActiveForm::end(); ?>
