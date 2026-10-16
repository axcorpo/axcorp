<?php
/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model frontend\modules\account\models\PaymentSubscriptionForm|frontend\modules\account\models\PaymentInvoiceForm */

use common\models\Company;
use common\models\Feature;
use common\models\FeatureModule;
use common\models\PaymentMetadata;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

$featureModuleLabels = FeatureModule::getModuleLabels();
$featureLabels = Feature::getFeatureLabels();
$paymentSettings = Yii::$app->settings->getCategory('payment');
$activePaymentMethods = array_intersect_key(PaymentMetadata::getPaymentMethodLabels(), (array) $paymentSettings['paymentMethods']);
$activePaymentProcessors = array_intersect_key(PaymentMetadata::getPaymentProcessorLabels(), (array) $paymentSettings['paymentProcessors'][PaymentMetadata::PAYMENT_METHOD_CARD]);
?>

<div class="row">
	<div class="col-sm-6">
		<?php
		$companies = $model->getUser()->getCompanies()->active()->deleted(false)->all();
		$createBtn = Html::a('<span class="fa fa-plus"></span>', ['/account/company/create'], [
			'class' => 'btn btn-default btn-slide-right',
			'title' => Yii::t('common', 'Create'),
			'data' => [
				'toggle' => 'tooltip',
				'popup-action' => '',
				'popup-done' => ['reload' => '#' . Html::getInputId($model, 'company_id')],
			],
		]);
		?>
		<?= $form->field($model, 'company_id')->widget(Select2::class, [
			'options' => [
				'options' => ArrayHelper::map($companies, 'uuid', function (Company $company) {
					return [
						'data' => [
							'email' => $company->email,
						],
					];
				}),
			],
			'data' => ArrayHelper::map($companies, 'uuid', 'name'),
			'addon' => [
				'append' => [
					'content' => $createBtn,
					'asButton' => true,
				],
			],
			'pluginLoading' => false,
			'pluginOptions' => [
				'allowClear' => true,
				'placeholder' => Yii::t('common', 'Choose'),
			],
		])->hint(Yii::t('frontend', 'Select a company for which invoices will be issued.')) ?>
	</div>
</div>


<?php if (count($activePaymentMethods) > 0): ?>
	<div class="form-group required gap-b-sm gap-t-sm">
		<label class="control-label"><?= Yii::t('common', 'Choose your payment method') ?></label>
		<div>
			<?= $form->field($model, 'payment_method', [
				'options' => [
					'class' => '',
				],
			])->radioList($activePaymentMethods, [
				'class' => 'radio-inline',
				'unselect' => null,
				'item' => function ($index, $label, $name, $checked, $value) use ($model) {
					$itemOptions = [
						'label' => $label,
						'value' => $value,
					];
					if ($value == PaymentMetadata::PAYMENT_METHOD_CARD) {
						$itemOptions['data']['requires-payment-processor'] = 'true';
					}
					return Html::tag('div', Html::radio($name, $checked, $itemOptions), ['class' => 'radio']);
				},
			])->label(false) ?>
			<div id="pm-hints" class="alert alert-icon alert-info">
				<?php if (array_key_exists(PaymentMetadata::PAYMENT_METHOD_CARD, $activePaymentMethods)): ?>
					<div id="pm-hint-<?= PaymentMetadata::PAYMENT_METHOD_CARD ?>" class="<?= $model->payment_method == PaymentMetadata::PAYMENT_METHOD_CARD ? '' : 'hidden' ?>"><?= Yii::t('frontend', 'This payment method requires a valid credit or debit card. The payment transaction is secured.') ?></div>
				<?php endif; ?>
				<?php if (array_key_exists(PaymentMetadata::PAYMENT_METHOD_BANK, $activePaymentMethods)): ?>
					<div id="pm-hint-<?= PaymentMetadata::PAYMENT_METHOD_BANK ?>" class="<?= $model->payment_method == PaymentMetadata::PAYMENT_METHOD_BANK ? '' : 'hidden' ?>">
						<?= Yii::t('frontend', 'This payment method requires a manual bank transfer. Your subscription will be manually activated after the payment is completed.') ?>
						<div class="table table-condensed table-borderless table-break-sm gap-t-sm">
							<div class="tbody">
								<div class="tr">
									<div class="th col-autowidth"><?= Yii::t('label', 'Bank Name') ?></div>
									<div class="td"><?= $paymentSettings['bankName'] ?></div>
								</div>
								<div class="tr">
									<div class="th col-autowidth"><?= Yii::t('label', 'Bank Account') ?></div>
									<div class="td"><?= $paymentSettings['bankAccount'] ?></div>
								</div>
								<div class="tr">
									<div class="th col-autowidth"><?= Yii::t('label', 'Bank BIC') ?></div>
									<div class="td"><?= $paymentSettings['bankBic'] ?></div>
								</div>
								<div class="tr">
									<div class="th col-autowidth"><?= Yii::t('label', 'Bank SWIFT') ?></div>
									<div class="td"><?= $paymentSettings['bankSwift'] ?></div>
								</div>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
<?php else: ?>
	<?php
	$itemOptions = [];
	if ($model->payment_method == PaymentMetadata::PAYMENT_METHOD_CARD) {
		$itemOptions['data']['requires-payment-processor'] = 'true';
	}
	?>
	<?= $form->field($model, 'payment_method', [
		'options' => [
			'tag' => false,
		],
		'template' => '{input}',
	])->hiddenInput($itemOptions) ?>
<?php endif; ?>


<?php if (count($activePaymentProcessors) > 1): ?>
	<div class="gap-b-sm gap-t-sm">
		<?= $form->field($model, 'payment_processor', [
			'options' => [
				'id' => 'pp-container',
				'class' => 'gap-t-sm',
			],
		])->radioList($activePaymentProcessors, [
			'class' => 'radio-inline',
			'uncheck' => null,
			'item' => function ($index, $label, $name, $checked, $value, $activePaymentProcessors) use ($model) {
				$itemOptions = [
					'label' => $label,
					'value' => $value,
				];
				return Html::tag('div', Html::radio($name, $checked, $itemOptions), ['class' => 'radio']);
			},
		])->inline(true) ?>
	</div>
<?php else: ?>
	<?= $form->field($model, 'payment_processor', [
		'options' => [
			'tag' => false,
		],
		'template' => '{input}',
	])->hiddenInput() ?>
<?php endif; ?>
