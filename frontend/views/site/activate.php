<?php
/* @var $this yii\web\View */
/* @var $model common\models\ActivateAccountForm */

use common\widgets\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;
?>

<section class="section auth-section">
	<div class="container">
		<?php if ($content = $this->context->currentPage->content): ?>
			<div class="auth-intro rich-content text-center"><?= $content ?></div>
		<?php endif; ?>

		<div class="auth-card">
			<div class="form-content">
				<p class="title-form text-title-1 fw-7 letter-space--3"><?= Yii::t('common', 'Account Activation') ?></p>
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
						<?php $tokenInputId = Html::getInputId($model, 'token'); ?>
						<?= $form->field($model, 'token', [
							'template' => '
								<label class="form-label title-input" for="' . $tokenInputId . '">' . Yii::t('label', 'Token') . '</label>
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
					</fieldset>
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

					<?= Html::submitButton('<span class="text-btn">' . Yii::t('common', 'Activate') . '</span><span class="icon-btn"><i class="icon-chevron-right"></i></span>', ['class' => 'tf-btn style-big style-gradient-2 style-w-full w-100']) ?>

					<p class="auth-footer-text text-center mb-0 mt-3"><small class="me-2"><?= Yii::t('common', 'Already have an account?') ?> </small> <a href="<?= Url::to(['/site/login']) ?>" class="auth-link"><?= Yii::t('common', 'Log In') ?></a></p>
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
