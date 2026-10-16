<?php

/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\ResetPasswordRequestForm */

use backend\widgets\ActiveForm;
use yii\helpers\Html;

$this->title = Yii::t('common', 'Request Password Reset');
?>

<?php $form = ActiveForm::begin([
	'id' => 'request-password-reset-form',
	'validateOnType' => true,
]); ?>

	<p><?= Yii::t('common', 'Please fill out your email. A link to reset password will be sent there.') ?></p>

	<?= $form->field($model, 'email')->input('email', ['autofocus' => true]) ?>

	<div class="form-actions">
		<?= Html::submitButton(Yii::t('common', 'Send'), ['class' => 'btn btn-block btn-warning']) ?>
	</div>

	<div class="form-links text-center">
		<?= Html::a(Yii::t('common', 'Log In'), ['/site/login']) ?>
	</div>

<?php ActiveForm::end(); ?>
