<?php
/* @var $this yii\web\View */
/* @var $model frontend\modules\account\models\PaymentInvoiceForm */

use common\models\Feature;
use common\models\FeatureModule;
use common\models\PaymentMetadata;
use common\widgets\ActiveForm;
use tws\helpers\Url;
use yii\helpers\Html;

$this->title = Yii::t('frontend', 'Payment for {item}', ['item' => Yii::t('common', 'Invoice')]);
$this->params['breadcrumbs'][] = Html::encode($this->title);
\frontend\modules\account\assets\PaymentAsset::register($this);

$featureModuleLabels = FeatureModule::getModuleLabels();
$featureLabels = Feature::getFeatureLabels();
$paymentSettings = Yii::$app->settings->getCategory('payment');
$activePaymentMethods = array_intersect_key(PaymentMetadata::getPaymentMethodLabels(), (array) $paymentSettings['paymentMethods']);
$activePaymentProcessors = array_intersect_key(PaymentMetadata::getPaymentProcessorLabels(), (array) $paymentSettings['paymentProcessors'][PaymentMetadata::PAYMENT_METHOD_CARD]);
?>

<div class="section section-md">
	<div class="container-fluid">
		<header class="section-header">
			<p><?= Yii::t('frontend', 'Please pay this invoice in order to use the features from its related subscription.') ?></p>
		</header>

		<?php $form = ActiveForm::begin([
			'id' => 'payment-form',
			'options' => [
				'novalidate' => true,
			],
			'validateOnType' => true,
		]); ?>
			<?php
				$invoice = $model->getInvoice();
				$subscription = $model->getSubscription();
			?>
			<div class="form-group gap-b-lg">
				<label class="control-label"><?= $model->getAttributeLabel('invoice_id') ?></label>
				<div class="table-responsive">
					<table class="table table-bordered table-striped">
						<tbody>
							<tr>
								<th class="col-autowidth">#</th>
								<td><?= Html::a($invoice->getDocumentSeriesNumber(), ['invoice/view', 'id' => $invoice->id], ['target' => '_blank']) ?></td>
							</tr>
							<tr>
								<th class="col-autowidth"><?= Yii::t('label', 'Issued At') ?></th>
								<td><?= $invoice->issued_at ? Yii::$app->formatter->asDatetime($invoice->issued_at) : '&mdash;' ?></td>
							</tr>
							<tr>
								<th class="col-autowidth"><?= Yii::t('label', 'Due At') ?></th>
								<td><?= $invoice->due_at ? Yii::$app->formatter->asDatetime($invoice->due_at) : '&mdash;' ?></td>
							</tr>
							<tr>
								<th class="col-autowidth"><?= Yii::t('label', 'Subscription') ?></th>
								<td>
									<a href="<?= Url::to(['/account/subscription/view', 'id' => $subscription->id]) ?>" data-popup-action=""><?= $subscription->formattedName ?></a>
								</td>
							</tr>
							<tr>
								<th class="col-autowidth"><?= Yii::t('label', 'Amount') ?></th>
								<td><?= Yii::$app->formatter->asCurrency($invoice->getTotalAmount(), $invoice->currency) ?> <?= mb_strtolower($subscription->getFormattedBillingCycle()) ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<?= $this->render('_shared-form-fields', [
				'form' => $form,
				'model' => $model,
			]) ?>

			<div class="form-group text-center gap-t-lg">
				<button type="submit" class="btn btn-lg btn-default btn-slide-right" id="btn-submit-payment">
					<span class="btn-submit-label-payment <?= $model->payment_method == PaymentMetadata::PAYMENT_METHOD_CARD ? '' : 'hidden' ?>">
						<?= Yii::t('common', 'Pay') ?>
						<span class="btn-submit-amount"><?= Yii::$app->formatter->asCurrency($invoice->getTotalAmount(), $invoice->currency) ?></span>
					</span>
					<?php if (array_key_exists(PaymentMetadata::PAYMENT_METHOD_BANK, $activePaymentMethods)): ?>
						<span class="btn-submit-label-submit <?= $model->payment_method != PaymentMetadata::PAYMENT_METHOD_CARD ? '' : 'hidden' ?>"><?= Yii::t('common', 'Submit') ?></span>
					<?php endif; ?>
				</button>
			</div>
		<?php ActiveForm::end(); ?>
	</div>
</div>
