<?php
/* @var $this yii\web\View */
/* @var $twoFactorAuthenticationModel common\models\TwoFactorAuthenticationForm */

use common\widgets\ActiveForm;
use tws\helpers\Url;
use yii\helpers\Html;
?>

<section class="section auth-section">
	<div class="container">
		<?php if ($content = $this->context->currentPage->translation->content): ?>
			<div class="auth-intro rich-content text-center"><?= $content ?></div>
		<?php endif; ?>

		<div class="auth-card">
			<div class="form-content">
				<p class="title-form text-title-1 fw-7 letter-space--3"><?= Yii::t('common', 'Confirm Login') ?></p>
				<?php $form = ActiveForm::begin([
					'id' => mb_strtolower($twoFactorAuthenticationModel->formName()),
					'options' => [
						'novalidate' => true,
						'class' => 'login-form mt-4',
					],
					'validateOnType' => true,
					'validateOnBlur' => false,
				]); ?>
					<fieldset>
						<?= $form->field($twoFactorAuthenticationModel, 'token')->textInput() ?>
					</fieldset>
					<?= $form->field($twoFactorAuthenticationModel, 'verifyCode', [
						'options' => [
							'class' => 'form-verify-field',
						],
						'template' => '{input}',
					])->textInput()->label(false) ?>

					<?= Html::submitButton('<span class="text-btn">' . Yii::t('common', 'Confirm Login') . '</span><span class="icon-btn"><i class="icon-chevron-right"></i></span>', ['class' => 'tf-btn style-big style-gradient-2 style-w-full w-100']) ?>
				<?php ActiveForm::end(); ?>
			</div>
		</div>
	</div>
</section>
