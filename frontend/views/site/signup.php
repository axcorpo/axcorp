<?php
/* @var $this yii\web\View */
/* @var $model common\models\SignupForm */

use borales\extensions\phoneInput\PhoneInput;
use common\components\PhoneInputConfig;
use common\widgets\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;

?>

<section class="section auth-section">
	<div class="container">
		<?php if ($content = $this->context->currentPage->content): ?>
			<div class="auth-intro rich-content text-center"><?= $content ?></div>
		<?php endif; ?>

		<div class="auth-card auth-card--wide">
			<div class="form-content">
				<?php $form = ActiveForm::begin([
					'id' => mb_strtolower($model->formName()),
					'options' => [
						'novalidate' => true,
						'class' => 'login-form',
					],
					'fieldConfig' => [
						'options' => ['class' => 'mb-3'],
						'labelOptions' => ['class' => 'form-label'],
					],
					'validateOnType' => true,
					'validateOnBlur' => true,
				]); ?>
					<p class="title-form text-title-1 fw-7 letter-space--3"><?= Yii::t('common', 'User Data') ?></p>
					<div class="row">
						<div class="col-md-6">
							<?= $form->field($model, 'last_name')->textInput(['class' => 'form-control']) ?>
						</div>
						<div class="col-md-6">
							<?= $form->field($model, 'first_name')->textInput(['class' => 'form-control']) ?>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<?= $form->field($model, 'email')->input('email', ['class' => 'form-control']) ?>
						</div>
						<div class="col-md-6">
							<?= $form->field($model, 'phone')->widget(PhoneInput::class, [
								'jsOptions' => PhoneInputConfig::jsOptions(),
								'options' => PhoneInputConfig::inputClassOptions(),
							]) ?>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<?= $form->field($model, 'password')->passwordInput(['class' => 'form-control', 'autocomplete' => 'new-password']) ?>
						</div>
						<div class="col-md-6">
							<?= $form->field($model, 'password_confirm')->passwordInput(['class' => 'form-control', 'autocomplete' => 'new-password']) ?>
						</div>
					</div>

					<?= $form->field($model, 'acceptTerms')->checkbox([
						'uncheck' => null,
						'label' => Yii::t('common', 'I understand and agree to the {0} and {1} of {2}.', [
							Html::a(Yii::t('common', 'Terms and Conditions'), ['/site/terms-and-conditions'], ['target' => '_blank']),
							Html::a(Yii::t('common', 'Privacy Policy'), ['/site/privacy-policy'], ['target' => '_blank']),
							Yii::$app->name
						]),
					]) ?>
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

					<?= Html::submitButton('<span class="text-btn">' . Yii::t('common', 'Sign Up') . '</span><span class="icon-btn"><i class="icon-chevron-right"></i></span>', ['class' => 'tf-btn style-big style-gradient-2 style-w-full w-100 mt-2']) ?>

					<div class="auth-footer-text text-center mt-4">
						<p class="mb-2"><small class="me-2"><?= Yii::t('common', 'Already have an account?') ?></small> <a href="<?= Url::to(['/site/login']) ?>" class="auth-link"><?= Yii::t('common', 'Log In') ?></a></p>
						<p class="mb-0"><a href="<?= Url::to(['/site/reset-password']) ?>" class="auth-link"><?= Yii::t('common', 'Forgot Password?') ?></a></p>
					</div>
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
