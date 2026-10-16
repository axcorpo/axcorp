<?php

/* @var $this yii\web\View */
/* @var $model backend\modules\setting\models\EmailSettingForm */
/* @var $form backend\widgets\ActiveForm */

use backend\widgets\ActiveForm;
use common\models\PaymentMetadata;
use kartik\touchspin\TouchSpin;
use yii\helpers\Html;

$this->title = Yii::t('backend', 'Payment Settings');
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Settings'),
		'url' => ['index'],
	],
	$this->title,
];
?>

<?php $form = ActiveForm::begin([
	'options' => [
		'novalidate' => true,
	],
	'validateOnType' => true,
]); ?>
	<?= $form->field($model, 'enableRecurringPayment')->checkbox() ?>
	<?= $form->field($model, 'enableInvoiceEmailNotification')->checkbox() ?>
	<?= $form->field($model, 'attachInvoiceToEmail')->checkbox() ?>
	<div class="row">
		<div class="col-sm-6">
			<?= $form->field($model, 'invoiceIssuanceBeforePeriod')->widget(TouchSpin::class, [
				'options' => [
					'placeholder' => Yii::t('common', 'Example') . ': 3',
				],
				'pluginOptions' => [
					'min' => 0,
					'max' => PHP_INT_MAX,
					'step' => 1,
					'decimals' => 0,
					'boostat' => 5,
					'maxboostedstep' => 10,
					'verticalbuttons' => true,
					'postfix' => mb_strtolower(Yii::t('label', 'Days')),
				],
			])->hint(Yii::t('backend', 'The number of days prior subscription expiration for invoice issuance.')) ?>
		</div>
		<div class="col-sm-6">
			<?= $form->field($model, 'allowedPaymentOverduePeriod')->widget(TouchSpin::class, [
				'options' => [
					'placeholder' => Yii::t('common', 'Example') . ': 3',
				],
				'pluginOptions' => [
					'min' => 0,
					'max' => PHP_INT_MAX,
					'step' => 1,
					'decimals' => 0,
					'boostat' => 5,
					'maxboostedstep' => 10,
					'verticalbuttons' => true,
					'postfix' => mb_strtolower(Yii::t('label', 'Days')),
				],
			])->hint(Yii::t('backend', 'The number of days for the account availability in case of overdue payments.')) ?>
		</div>
	</div>
	<?php
	$paymentMethodLabels = PaymentMetadata::getPaymentMethodLabels();
	$paymentProcessorLabels = PaymentMetadata::getPaymentProcessorLabels();
	?>
	<div class="panel blue-hoki">
		<div class="panel-title">
			<div class="panel-heading"><?= Yii::t('backend', 'Payment Methods') ?></div>
		</div>
		<div class="panel-body field-<?= Html::getInputId($model, 'paymentMethods') ?>">
			<?php $paymentMethod = PaymentMetadata::PAYMENT_METHOD_CARD; ?>
			<div class="panel panel-default">
				<div class="panel-heading">
					<?= $form->field($model, "paymentMethods[{$paymentMethod}]", [
						'options' => [
							'tag' => false,
						],
						'selectors' => [
							'container' => '.field-' . Html::getInputId($model, 'paymentMethods'),
							'input' => '.input-' . Html::getInputId($model, 'paymentMethods'),
							'error' => '.error-' . Html::getInputId($model, 'paymentMethods'),
						],
					])->checkbox([
						'id' => Html::getInputId($model, 'paymentMethods') . "-{$paymentMethod}",
						'class' => 'input-' . Html::getInputId($model, 'paymentMethods'),
						'value' => $paymentMethod,
						'label' => $paymentMethodLabels[$paymentMethod],
						'labelOptions' => [
							'class' => 'mt-checkbox mt-checkbox-outline m0',
						],
						'uncheck' => null,
						'data' => [
							'toggle-visibility' => "#pm-{$paymentMethod}-container",
						],
					]) ?>
				</div>
                <div id="pm-<?= $paymentMethod ?>-container" class="panel-body field-<?= Html::getInputId($model, 'paymentProcessors') ?> <?= isset($model->paymentMethods[$paymentMethod]) ? '' : 'hidden' ?>">
					<?php $paymentProcessor = PaymentMetadata::PAYMENT_PROCESSOR_STRIPE; ?>
                    <fieldset>
                        <legend>
							<?= $form->field($model, "paymentProcessors[{$paymentMethod}][{$paymentProcessor}]", [
								'options' => [
									'tag' => false,
								],
								'selectors' => [
									'container' => '.field-' . Html::getInputId($model, 'paymentProcessors'),
									'input' => '.input-' . Html::getInputId($model, 'paymentProcessors'),
									'error' => '.error-' . Html::getInputId($model, 'paymentProcessors'),
								],
							])->checkbox([
								'id' => Html::getInputId($model, 'paymentProcessors') . "-{$paymentProcessor}",
								'class' => 'input-' . Html::getInputId($model, 'paymentProcessors'),
								'value' => $paymentProcessor,
								'label' => $paymentProcessorLabels[$paymentProcessor],
								'uncheck' => null,
								'data' => [
									'toggle-visibility' => "#pp-{$paymentProcessor}-fields",
								],
							]) ?>
                        </legend>
                        <div id="pp-<?= $paymentProcessor ?>-fields" class="<?= in_array($paymentProcessor, (array) $model->paymentProcessors[$paymentMethod]) ? '' : 'hidden' ?>">
                            <div class="text-info-icon fa-info-circle text-muted margin-bottom-10">
                                <a href="https://stripe.com/docs" target="_blank">
									<?= Yii::t('backend', 'Follow the official documentation for more information.') ?>
                                </a>
                            </div>
	                        <div class="row">
		                        <div class="col-lg-6">
									<?= $form->field($model, 'stripeBaseUrl')->input('url')->hint(Yii::t('backend', 'The API endpoint.') . ' ' . Yii::t('common', 'Example') . ': https://api.stripe.com/v1/') ?>
		                        </div>
		                        <div class="col-lg-6">
			                        <?= $form->field($model, 'stripeWebhookIPNKey')->textInput()->hint(Yii::t('backend', 'The Webhook key.') . ' ' . Yii::t('common', 'Example') . ': whsec_fV9KeJMHDro7RsMulaYBwZNH1bbUFPBC') ?>
		                        </div>
	                        </div>
	                        <div class="row">
                                <div class="col-lg-6">
									<?php $secretFieldTemplate = '{label}<div class="input-secret">{input}<span class="toggle-secret fa fa-eye-slash"></span></div>{hint}{error}'; ?>
									<?= $form->field($model, 'stripePrivateKey', ['template' => $secretFieldTemplate])->passwordInput(['value' => '', 'placeholder' => $model->stripePrivateKey ? Yii::t('backend', 'Unchanged. Type a new value to replace it.') : ''])->hint(Yii::t('backend', 'The API private key.') . ' ' . Yii::t('common', 'Example') . ': sk_test_abcdefghijklmnopqrstuvwxyz') ?>
                                </div>
                                <div class="col-lg-6">
									<?= $form->field($model, 'stripePublicKey', ['template' => $secretFieldTemplate])->passwordInput()->hint(Yii::t('backend', 'The API public key.') . ' ' . Yii::t('common', 'Example') . ': pk_test_abcdefghijklmnopqrstuvwxyz') ?>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                    <div class="error-<?= Html::getInputId($model, 'paymentProcessors') ?> help-block help-block-error"></div>
                </div>
			</div>
			<?php $paymentMethod = PaymentMetadata::PAYMENT_METHOD_BANK; ?>
			<div class="panel panel-default m0">
				<div class="panel-heading">
					<?= $form->field($model, "paymentMethods[{$paymentMethod}]", [
						'options' => [
							'tag' => false,
						],
						'selectors' => [
							'container' => '.field-' . Html::getInputId($model, 'paymentMethods'),
							'input' => '.input-' . Html::getInputId($model, 'paymentMethods'),
							'error' => '.error-' . Html::getInputId($model, 'paymentMethods'),
						],
					])->checkbox([
						'id' => Html::getInputId($model, 'paymentMethods') . "-{$paymentMethod}",
						'class' => 'input-' . Html::getInputId($model, 'paymentMethods'),
						'value' => $paymentMethod,
						'label' => $paymentMethodLabels[$paymentMethod],
						'labelOptions' => [
							'class' => 'mt-checkbox mt-checkbox-outline m0',
						],
						'uncheck' => null,
						'data' => [
							'toggle-visibility' => "#pm-{$paymentMethod}-container",
						],
					]) ?>
				</div>
				<div id="pm-<?= $paymentMethod ?>-container" class="panel-body <?= isset($model->paymentMethods[$paymentMethod]) ? '' : 'hidden' ?>">
					<div class="row">
						<div class="col-lg-6">
							<?= $form->field($model, 'bankName')->textInput() ?>
						</div>
						<div class="col-lg-6">
							<?= $form->field($model, 'bankAccount')->textInput() ?>
						</div>
					</div>
					<div class="row">
						<div class="col-lg-6">
							<?= $form->field($model, 'bankBic')->textInput() ?>
						</div>
						<div class="col-lg-6">
							<?= $form->field($model, 'bankSwift')->textInput() ?>
						</div>
					</div>
				</div>
			</div>
			<div class="error-<?= Html::getInputId($model, 'paymentMethods') ?> help-block help-block-error"></div>
		</div>
	</div>

	<div class="form-actions floating">
		<?= Html::submitButton('<span class="fa fa-check"></span>', [
			'class' => 'btn btn-xlg btn-fab btn-success',
			'title' => Yii::t('common', 'Save'),
			'data' => [
				'toggle' => 'tooltip',
			],
		]) ?>
	</div>
<?php ActiveForm::end(); ?>
