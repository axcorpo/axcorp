<?php
/* @var $this yii\web\View */
/* @var $resetPasswordRequestModel common\models\ResetPasswordRequestForm */
/* @var $resetPasswordModel common\models\ResetPasswordForm */

use common\models\ResetPasswordForm;
use common\widgets\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;
?>

<section class="section auth-section">
	<div class="container">
		<?php if ($content = $this->context->currentPage->content): ?>
			<div class="auth-intro rich-content text-center"><?= $content ?></div>
		<?php endif; ?>

		<?php if ($resetPasswordModel->scenario == ResetPasswordForm::SCENARIO_PASSWORD): ?>
			<div class="auth-card">
				<div class="form-content">
					<p class="title-form text-title-1 fw-7 letter-space--3"><?= Yii::t('common', 'Set your new password') ?></p>
					<?php $form = ActiveForm::begin([
						'id' => mb_strtolower($resetPasswordModel->formName()),
						'options' => [
							'novalidate' => true,
							'class' => 'login-form mt-4',
						],
						'validateOnType' => true,
						'validateOnBlur' => false,
					]); ?>
						<fieldset>
							<?= $form->field($resetPasswordModel, 'password')->passwordInput(['autocomplete' => 'new-password']) ?>
						</fieldset>
						<fieldset>
							<?= $form->field($resetPasswordModel, 'password_confirm')->passwordInput(['autocomplete' => 'new-password']) ?>
						</fieldset>
						<?= $form->field($resetPasswordModel, 'workEmail', [
							'options' => [
								'class' => 'work-email',
							],
							'template' => '{input}',
						])->input('email', ['required' => 'required', 'autocomplete' => 'off', 'tabindex' => '-1', 'aria-hidden' => 'true'])->label(false) ?>
						<?php if (Yii::$app->settings->get('reCaptchaSiteKey', 'general')): ?>
							<div class="hidden g-recaptcha" data-sitekey="<?= Yii::$app->settings->get('reCaptchaSiteKey', 'general') ?>" data-badge="inline" data-size="invisible" data-callback="setResponse"></div>
							<?= $form->field($resetPasswordModel, 'captchaResponse', [
								'template' => '{input}',
							])->hiddenInput(['id' => 'captcha-response'])->label(false) ?>
						<?php endif; ?>

						<?= Html::submitButton('<span class="text-btn">' . Yii::t('common', 'Reset Password') . '</span><span class="icon-btn"><i class="icon-chevron-right"></i></span>', ['class' => 'tf-btn style-big style-gradient-2 style-w-full w-100']) ?>

						<p class="auth-footer-text text-center mb-0 mt-3"><small class="me-2"><?= Yii::t('common', 'Already have an account?') ?></small> <a href="<?= Url::to(['/site/login']) ?>" class="auth-link"><?= Yii::t('common', 'Log In') ?></a></p>
					<?php ActiveForm::end(); ?>
				</div>
			</div>
		<?php else: ?>  <!--SCENARIO TOKEN -->
			<div class="row g-4 justify-content-center">
				<div class="col-lg-6">
					<div class="auth-card auth-card--fluid h-100">
						<div class="form-content">
							<p class="title-form text-title-1 fw-7 letter-space--3"><?= Yii::t('common', 'Request a password reset code') ?></p>
							<?php $form = ActiveForm::begin([
								'id' => mb_strtolower($resetPasswordRequestModel->formName()),
								'options' => [
									'novalidate' => true,
									'class' => 'login-form mt-4',
								],
								'validateOnType' => true,
								'validateOnBlur' => false,
							]); ?>
								<fieldset>
									<?php $usernameInputId = Html::getInputId($resetPasswordRequestModel, 'username'); ?>
									<?= $form->field($resetPasswordRequestModel, 'username', [
										'template' => '
											<label class="form-label title-input" for="' . $usernameInputId . '">' . Yii::t('label', 'Email') . '</label>
												<div class="form-icon position-relative">
													{input}
												</div>
												{error}',
									])->textInput([
										'type' => 'text',
										'class' => 'form-control',
										'placeholder' => Yii::t('label', 'Email'),
										'required' => true,
									]) ?>
									<?= $form->field($resetPasswordRequestModel, 'workEmail', [
										'options' => [
											'class' => 'work-email',
										],
										'template' => '{input}',
									])->input('email', ['required' => 'required', 'autocomplete' => 'off', 'tabindex' => '-1', 'aria-hidden' => 'true'])->label(false) ?>
									<?php if (Yii::$app->settings->get('reCaptchaSiteKey', 'general')): ?>
										<div class="hidden g-recaptcha" data-sitekey="<?= Yii::$app->settings->get('reCaptchaSiteKey', 'general') ?>" data-badge="inline" data-size="invisible" data-callback="setResponse"></div>
										<?= $form->field($resetPasswordRequestModel, 'captchaResponse', [
											'template' => '{input}',
										])->hiddenInput(['id' => 'captcha-response'])->label(false) ?>
									<?php endif; ?>
								</fieldset>

								<?= Html::submitButton('<span class="text-btn">' . Yii::t('common', 'Request Code') . '</span><span class="icon-btn"><i class="icon-chevron-right"></i></span>', ['class' => 'tf-btn style-big style-gradient-2 style-w-full w-100']) ?>
							<?php ActiveForm::end(); ?>
						</div>
					</div>
				</div>

				<div class="col-lg-6">
					<div class="auth-card auth-card--fluid h-100">
						<div class="form-content">
							<p class="title-form text-title-1 fw-7 letter-space--3"><?= Yii::t('common', 'Already have a password reset code?') ?></p>
							<?php $form = ActiveForm::begin([
								'id' => mb_strtolower($resetPasswordModel->formName()),
								'options' => [
									'novalidate' => true,
									'class' => 'login-form mt-4',
								],
								'validateOnType' => true,
								'validateOnBlur' => false,
							]); ?>
								<fieldset>
									<?= $form->field($resetPasswordModel, 'token', [
										'template' => '
											<label class="form-label title-input">' . Yii::t('label', 'Token') . '</label>
												<div class="form-icon position-relative">
													{input}
												</div>
												{error}',
									])->textInput([
										'type' => 'text',
										'class' => 'form-control',
										'placeholder' => Yii::t('label', 'Token'),
										'required' => true,
									]) ?>
									<?= $form->field($resetPasswordModel, 'workEmail', [
										'options' => [
											'class' => 'work-email',
										],
										'template' => '{input}',
									])->input('email', ['required' => 'required', 'autocomplete' => 'off', 'tabindex' => '-1', 'aria-hidden' => 'true'])->label(false) ?>
									<?php if (Yii::$app->settings->get('reCaptchaSiteKey', 'general')): ?>
										<div class="hidden g-recaptcha" data-sitekey="<?= Yii::$app->settings->get('reCaptchaSiteKey', 'general') ?>" data-badge="inline" data-size="invisible" data-callback="setResponse"></div>
										<?= $form->field($resetPasswordModel, 'captchaResponse', [
											'template' => '{input}',
										])->hiddenInput(['id' => 'captcha-response'])->label(false) ?>
									<?php endif; ?>
								</fieldset>

								<?= Html::submitButton('<span class="text-btn">' . Yii::t('common', 'Validate Code') . '</span><span class="icon-btn"><i class="icon-chevron-right"></i></span>', ['class' => 'tf-btn style-big style-gradient-2 style-w-full w-100']) ?>
							<?php ActiveForm::end(); ?>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php
$this->registerJs('
		$( "#' . Html::getInputId($resetPasswordModel, 'workEmail') . '").removeAttr("required");
	');
?>
<?php
$this->registerJs('
		$( "#' . Html::getInputId($resetPasswordRequestModel, 'workEmail') . '").removeAttr("required");
	');
?>
