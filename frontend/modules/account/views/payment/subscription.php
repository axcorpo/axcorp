<?php
/* @var $this yii\web\View */
/* @var $model frontend\modules\account\models\PaymentSubscriptionForm */

use common\models\Feature;
use common\models\FeatureModule;
use common\models\PaymentMetadata;
use common\widgets\ActiveForm;
use tws\helpers\Url;
use yii\helpers\Html;

$this->title = Yii::t('frontend', 'Payment for {item}', ['item' => Yii::t('common', 'Subscription')]);
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
			<p><?= Yii::t('frontend', 'Please review your subscription and make a payment in order to use its features.') ?></p>
		</header>

		<?php $form = ActiveForm::begin([
			'id' => 'payment-form',
			'options' => [
				'novalidate' => true,
			],
			'validateOnType' => true,
		]); ?>
			<?php
			$subscription = $model->getSubscription();
			$package = $subscription->package;
			$packageTranslation = $package->getTranslation();
			?>
			<div class="form-group gap-b-md">
				<label class="control-label"><?= $model->getAttributeLabel('subscription_id') ?></label>
				<div>
					<a href="<?= Url::to(['/account/subscription/view', 'id' => $subscription->id]) ?>" data-popup-action=""><?= $subscription->formattedName ?></a>
				</div>
			</div>
			<div class="form-group gap-b-lg">
				<label class="control-label"><?= Yii::t('label', 'Features') ?></label>
				<?= $this->render('/subscription/_features-list', [
					'model' => $subscription,
				]) ?>
			</div>

			<?= $this->render('_shared-form-fields', [
				'form' => $form,
				'model' => $model,
			]) ?>

			<div class="form-group text-center gap-t-lg">
				<button type="submit" class="btn btn-lg btn-default btn-slide-right" id="btn-submit-payment">
					<span class="btn-submit-label-payment <?= $model->payment_method == PaymentMetadata::PAYMENT_METHOD_CARD ? '' : 'hidden' ?>">
						<?= Yii::t('common', 'Pay') ?>
						<span class="btn-submit-amount"><?= Yii::$app->formatter->asCurrency($subscription->price, $subscription->currency) ?></span>
					</span>
					<?php if (array_key_exists(PaymentMetadata::PAYMENT_METHOD_BANK, $activePaymentMethods)): ?>
						<span class="btn-submit-label-submit <?= $model->payment_method != PaymentMetadata::PAYMENT_METHOD_CARD ? '' : 'hidden' ?>"><?= Yii::t('common', 'Submit') ?></span>
					<?php endif; ?>
				</button>
			</div>
		<?php ActiveForm::end(); ?>
	</div>
</div>
