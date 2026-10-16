<?php
/* @var $this yii\web\View */
/* @var $model common\models\AssumeIdentityForm */

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
				<p class="title-form text-title-1 fw-7 letter-space--3"><?= Yii::t('common', 'Log In') ?></p>
				<?php $form = ActiveForm::begin([
					'id' => mb_strtolower($model->formName()),
					'options' => [
						'novalidate' => true,
						'class' => 'login-form mt-4',
					],
					'validateOnType' => true,
					'validateOnBlur' => false,
				]); ?>
					<div class="auth-options d-flex align-items-center justify-content-between flex-wrap g-10">
						<div class="form-check">
							<?= $form->field($model, 'rememberMe', [
								'template' => '
									<div class="form-check p-0">
										{input}
										<label class="form-check-label" for="' . Html::getInputId($model, 'rememberMe') . '">' . Yii::t('common', 'Remember me') . '</label>
									</div>
									{error}',
							])->checkbox([
								'class' => 'form-check-input',
							], false) ?>
						</div>
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
