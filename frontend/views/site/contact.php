<?php
/* @var $this yii\web\View */
/* @var $model frontend\models\ContactForm */
/* @var $contact array */
/* @var $address string */
/* @var $map dosamigos\google\maps\Map */

use borales\extensions\phoneInput\PhoneInput;
use common\components\PhoneInputConfig;
use frontend\models\AcceptCookiesForm;
use tws\helpers\Url;
use yii\bootstrap\ActiveForm;
use yii\helpers\Html;

$labelClass = 'form-label title-input text-body-2 letter-space--2 fw-5';
?>

<section class="section-contact p-contact section site-contact" id="contact">
	<div class="container">
		<div class="row rg-60">
			<div class="col-lg-6 order-lg-2">
				<div class="section-content">
					<?php if (!empty($contact['company'])): ?>
						<div class="heading-section mb-30">
							<p class="text-body-1 fw-5 letter-space--2 ff-2 sub-title mb-10">
								<i class="icon-sparkle"></i>
								<?= Yii::t('label', 'Company') ?>
							</p>
							<h2 class="h2 title fw-6"><?= Html::encode($contact['company']) ?></h2>
						</div>
					<?php endif; ?>

					<?php
					// The company's identification and contact details, as on the about page.
					$companyDetails = array_filter([
						Yii::t('label', 'Tin') => Html::encode($contact['tin'] ?? ''),
						Yii::t('label', 'Registration Number') => Html::encode($contact['registrationNumber'] ?? ''),
						Yii::t('label', 'Email') => !empty($contact['email']) ? Html::a(Html::encode($contact['email']), 'mailto:' . $contact['email'], ['class' => 'text-primary']) : '',
						Yii::t('label', 'Phone') => !empty($contact['mobilePhone']) ? Html::a(Html::encode($contact['mobilePhone']), 'tel:' . $contact['mobilePhone'], ['class' => 'text-primary']) : '',
						Yii::t('label', 'Address') => Html::encode($address),
					]);
					?>
					<div class="list-mission-card d-flex flex-column g-20">
						<?php foreach ($companyDetails as $label => $value): ?>
							<div class="mission-card">
								<div class="top">
									<i class="icon-sparkle"></i>
									<span class="h4 fw-7"><?= $label ?></span>
								</div>
								<p class="desc ff-2 mb-0"><?= $value ?></p>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div><!--end col-->

			<div class="col-lg-6 order-lg-1">
				<div class="form-contact">
					<div class="form-content">
						<?php $form = ActiveForm::begin([
							'method' => 'post',
							'id' => mb_strtolower($model->formName()),
							'options' => [
							],
						]); ?>
						<div class="row">
							<div class="col-md-6">
								<fieldset>
									<?php $nameInputId = Html::getInputId($model, 'name'); ?>
									<?= $form->field($model, 'name', [
										'template' => '
											<label class="' . $labelClass . '" for="' . $nameInputId . '">' . Yii::t('label', 'Name') . '</label>
												<div class="form-icon position-relative">
													{input}
												</div>
											{error}',
									])->textInput([
										'type' => 'text',
										'class' => 'form-control',
										'placeholder' => Yii::t('label', 'Name'),
									]) ?>
								</fieldset>
							</div>

							<div class="col-md-6">
								<fieldset>
									<?php $emailInputId = Html::getInputId($model, 'email'); ?>
									<?= $form->field($model, 'email', [
										'template' => '
											<label class="' . $labelClass . '" for="' . $emailInputId . '">' . Yii::t('label', 'Email') . '</label>
												<div class="form-icon position-relative">
													{input}
												</div>
											{error}',
									])->textInput([
										'type' => 'email',
										'class' => 'form-control',
										'placeholder' => Yii::t('label', 'Email'),
									]) ?>
								</fieldset>
							</div><!--end col-->

							<div class="col-md-6">
								<fieldset>
									<?php $phoneInputId = Html::getInputId($model, 'phone'); ?>
									<?= $form->field($model, 'phone', [
										'template' => '
											<label class="' . $labelClass . '" for="' . $phoneInputId . '">' . Yii::t('label', 'Phone') . '</label>
												<div class="form-icon position-relative">
													{input}
												</div>
											{error}',
									])->widget(PhoneInput::class, [
										'jsOptions' => PhoneInputConfig::jsOptions(),
										'options' => array_merge(PhoneInputConfig::inputClassOptions(), [
											'placeholder' => Yii::t('label', 'Phone'),
										]),
									]) ?>
								</fieldset>
							</div><!--end col-->

							<div class="col-md-6">
								<fieldset>
									<?= $form->field($model, 'subject', [
										'template' => '
											<label class="' . $labelClass . '" for="subject">' . Yii::t('label', 'Subject') . '</label>
												<div class="form-icon position-relative">
													{input}
												</div>
											{error}',
									])->textInput([
										'class' => 'form-control',
										'placeholder' => Yii::t('label', 'Subject'),
									]) ?>
								</fieldset>
							</div><!--end col-->

							<div class="col-12">
								<fieldset class="mess-input">
									<?= $form->field($model, 'message', [
										'template' => '
											<label class="' . $labelClass . '" for="comments">' . Yii::t('label', 'Message') . '</label>
												<div class="form-icon position-relative">
													{input}
												</div>
											{error}',
									])->textarea([
										'rows' => 4,
										'class' => 'form-control',
										'style' => 'min-height: 10rem;',
									]) ?>
								</fieldset>
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

						<?= Html::submitButton('<span class="text-btn">' . Yii::t('common', 'Submit') . '</span><span class="icon-btn"><i class="icon-chevron-right"></i></span>', [
							'class' => 'tf-btn style-big style-w-full style-gradient-2',
							'name' => 'contact-button',
						]) ?>
						<?php ActiveForm::end(); ?>
					</div><!--end form-content-->
				</div><!--end form-contact-->
			</div><!--end col-->
		</div><!--end row-->
	</div><!--end container-->

	<?php if ($map): ?>
		<div class="container mt-100 mt-60">
			<?php // Google Maps sets cookies on google.com: the map is loaded only with marketing cookies accepted. ?>
			<?php if (AcceptCookiesForm::allows(AcceptCookiesForm::CATEGORY_MARKETING)): ?>
				<div class="wg-map site-contact-map">
					<?= $map->display() ?>
				</div>
			<?php else: ?>
				<div class="wg-map site-contact-map map-consent-placeholder">
					<p><?= Yii::t('frontend', 'The map is shown only if you accept marketing cookies.') ?></p>
					<a href="#cookiebar" class="cookiebar-open tf-btn">
						<span class="text-btn"><?= Yii::t('frontend', 'Cookie settings') ?></span>
						<span class="icon-btn"><i class="icon-chevron-right"></i></span>
					</a>
				</div>
			<?php endif; ?>
		</div><!--end container-->
	<?php endif; ?>
</section><!--end section-->

<?php
	$this->registerJs('
		$( "#' . Html::getInputId($model, 'workEmail') . '").removeAttr("required");
	');
?>
