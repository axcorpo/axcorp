<?php
/* @var $this yii\web\View */
/* @var $model common\models\LoginForm */

use common\widgets\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;

?>

<section class="section auth-section">
	<div class="container">
		<div class="auth-card">
			<div class="form-content">
				<p class="title-form text-title-1 fw-7 letter-space--3"><?= Yii::t('common', 'Login') ?></p>
				<?php $form = ActiveForm::begin([
					'id' => mb_strtolower($model->formName()),
					'options' => [
						'novalidate' => true,
						'class' => 'login-form mt-4',
					],
					'validateOnType' => true,
					'validateOnBlur' => false,
				]); ?>
					<fieldset>
						<?php $usernameInputId = Html::getInputId($model, 'username'); ?>
						<?= $form->field($model, 'username', [
							'template' => '
								<label class="form-label title-input" for="' . $usernameInputId . '">' . Yii::t('label', 'Email') . '</label>
									<div class="form-icon position-relative">
										{input}
									</div>
									{error}',
						])->textInput([
							'type' => 'email',
							'class' => 'form-control',
							'placeholder' => Yii::t('label', 'Email'),
							'required' => true,
						]) ?>
					</fieldset>

					<fieldset>
						<?php $passwordInputId = Html::getInputId($model, 'password'); ?>
						<?= $form->field($model, 'password', [
							'template' => '
								<label class="form-label title-input" for="' . $passwordInputId . '">' . Yii::t('label', 'Password') . '</label>
									<div class="form-icon position-relative">
										{input}
									</div>
									{error}',
						])->textInput([
							'type' => 'password',
							'class' => 'form-control',
							'placeholder' => Yii::t('label', 'Password'),
							'required' => true,
						]) ?>
					</fieldset>

					<div class="auth-options d-flex align-items-center justify-content-between flex-wrap g-10">
						<div class="form-check">
							<?= $form->field($model, 'rememberMe', [
								'template' => '
									<div class="form-check p-0">
										{input}
										<label class="form-check-label" for="flexCheckDefault">' . Yii::t('label', 'Remember Me') . '</label>
									</div>
									{error}
								',
							])->checkbox([
								'class' => 'form-check-input',
								'id' => 'flexCheckDefault',
							], false) ?>
						</div>
						<p class="forgot-pass mb-0"><a href="<?= Url::to(['/site/reset-password']) ?>" class="auth-link"><?= Yii::t('common', 'Forgot Password?') ?></a></p>
					</div>
					<?= $form->field($model, 'workEmail', [
						'options' => [
							'class' => 'work-email',
						],
						'template' => '{input}',
					])->input('email', ['required' => 'required', 'autocomplete' => 'off', 'tabindex' => '-1', 'aria-hidden' => 'true'])->label(false) ?>
					<?php if (Yii::$app->settings->get('reCaptchaSiteKey', 'general')): ?>
						<div class="hidden g-recaptcha" data-sitekey="<?= Yii::$app->settings->get('reCaptchaSiteKey', 'general') ?>" data-badge="inline" data-size="invisible" data-callback="setResponse"></div>
						<?= $form->field($model, 'captchaResponse', [
							'template' => '{input}',
						])->hiddenInput(['id' => 'captcha-response'])->label(false) ?>
					<?php endif; ?>

					<?= Html::submitButton('<span class="text-btn">' . Yii::t('common', 'Log In') . '</span><span class="icon-btn"><i class="icon-chevron-right"></i></span>', ['class' => 'tf-btn style-big style-gradient-2 style-w-full w-100']) ?>

					<p class="auth-footer-text text-center mb-0 mt-3"><small class="me-2"><?= Yii::t('common', 'Don\'t have an account yet?') ?></small> <a href="<?= Url::to(['/site/signup']) ?>" class="auth-link"><?= Yii::t('common', 'Sign Up')?></a></p>
				<?php ActiveForm::end(); ?>
			</div>
		</div>
	</div>
</section>

<?php
$this->registerJs('
		$( "#' . Html::getInputId($model, 'workEmail') . '").removeAttr("required");
	');
?>
