<?php
/* @var $this yii\web\View */
/* @var $model frontend\modules\account\models\PaymentPackageForm */

use common\models\Feature;
use common\models\Package;
use common\models\PackageFeature;
use common\models\FeatureModule;
use common\models\PaymentMetadata;
use common\widgets\ActiveForm;
use yii\helpers\Html;

$this->title = Yii::t('frontend', 'Payment for {item}', ['item' => Yii::t('common', 'Package')]);
$this->params['breadcrumbs'][] = Html::encode($this->title);
\frontend\modules\account\assets\PaymentAsset::register($this);

$featureModuleLabels = FeatureModule::getModuleLabels();
$featureLabels = Feature::getFeatureLabels();
$paymentSettings = Yii::$app->settings->getCategory('payment');
$activePaymentMethods = array_intersect_key(PaymentMetadata::getPaymentMethodLabels(), (array) $paymentSettings['paymentMethods']);
$activePaymentProcessors = array_intersect_key(PaymentMetadata::getPaymentProcessorLabels(), (array) $paymentSettings['paymentProcessors'][PaymentMetadata::PAYMENT_METHOD_CARD]);
Yii::$app->formatter->locale = Yii::$app->language;
?>

<div class="tab-pane fade shadow show active rounded p-4" id="payment" role="tabpanel">
    <p class="text-muted"><?= Yii::t('frontend', 'Buy a package and enjoy its features.') ?> <?= Yii::t('frontend', 'Please contact us if you have other requirements.') ?></p>

    <?php $form = ActiveForm::begin([
        'id' => 'payment-form',
        'options' => [
            'novalidate' => true,
            'class' => 'login-form',
        ],
        'validateOnType' => true,
    ]); ?>

        <div class="mb-3 form-group field-<?= Html::getInputId($model, 'package_id') ?> required" role="radiogroup" aria-required="true">
            <label class="form-label"><?= $model->getAttributeLabel('package_id') ?></label>
            <?php $packages = Package::findPackagesByType([Package::TYPE_STANDARD, Package::TYPE_CUSTOM]); ?>
            <div class="row">
                <?php foreach ($packages as $package): ?>
                    <?php
                    $packageTranslation = $package->getTranslation();
                    /** @var PackageFeature[] $packageFeatures */
                    $packageFeatures = $package->getPackageFeatures()->indexBy('name')->all();
                    ?>
                    <div class="col-lg-4 col-md-6 col-12 mt-4">
                        <?php if ($package->type == Package::TYPE_STANDARD): ?>
                            <div class="card pricing-rates business-rate shadow bg-white border-0 rounded h-100 <?= $package->uuid == $model->package_id ? 'active' : '' ?>" data-package="<?= $package->uuid ?>">
                                <div class="card-body">
                                    <h6 class="title fw-bold text-uppercase mb-3"><?= $packageTranslation->name ?></h6>
                                    <div class="d-flex mb-3">
                                        <span class="price h4 mb-0" data-package-price="<?= Yii::$app->formatter->asCurrency($package->price, $package->currency) ?>"><?= Yii::$app->formatter->asCurrency($package->price, $package->currency) ?></span>
                                    </div>
                                    <?php if (!empty($packageTranslation->content)): ?>
                                        <div class="text-muted"><?= $packageTranslation->content ?></div>
                                    <?php endif; ?>
                                    <span class="btn btn-outline-primary w-100 mt-4 package-choose-btn">
                                        <span class="choose-label"><?= Yii::t('common', 'Choose') ?></span>
                                        <span class="selected-label"><i class="uil uil-check-circle"></i> <?= Yii::t('common', 'Selected') ?></span>
                                    </span>
                                    <div class="hidden">
                                        <?= $form->field($model, 'package_id', [
                                            'options' => [
                                                'tag' => false,
                                            ],
                                            'selectors' => [
                                                'container' => '.field-' . Html::getInputId($model, 'package_id'),
                                                'input' => '.input-' . Html::getInputId($model, 'package_id'),
                                                'error' => '.error-' . Html::getInputId($model, 'package_id'),
                                            ],
                                        ])->radio([
                                            'id' => null,
                                            'class' => 'input-' . Html::getInputId($model, 'package_id'),
                                            'value' => $package->uuid,
                                            'data' => [
                                                'name' => $packageTranslation->name,
                                                'price' => $package->price,
                                                'currency' => $package->currency,
                                            ],
                                            'uncheck' => null,
                                            'label' => '',
                                            'labelOptions' => [
                                                'class' => 'hidden',
                                            ],
                                        ]) ?>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($package->type == Package::TYPE_CUSTOM): ?>
                            <div class="card pricing-rates business-rate shadow bg-white border-0 rounded h-100">
                                <div class="card-body">
                                    <h6 class="title fw-bold text-uppercase mb-3"><?= $packageTranslation->name ?></h6>
                                    <div class="d-flex mb-3">
                                        <span class="price h4 mb-0"><?= Yii::t('common', 'Custom') ?></span>
                                    </div>
                                    <ul class="list-unstyled mb-0 ps-0">
                                        <li class="h6 text-muted mb-0"><span class="icon h5 me-2"><i class="uil uil-check-circle align-middle"></i></span><?= Yii::t('common', 'Billed') ?>: <?= Yii::t('common', 'Custom') ?></li>
                                        <li class="h6 text-muted mb-0"><span class="icon h5 me-2"><i class="uil uil-check-circle align-middle"></i></span><?= $featureLabels[Feature::WORKSPACES] ?>: <?= Yii::t('common', 'Custom') ?></li>
                                        <li class="h6 text-muted mb-0"><span class="icon h5 me-2"><i class="uil uil-check-circle align-middle"></i></span><?= $featureLabels[Feature::USERS] ?>: <?= Yii::t('common', 'Custom') ?></li>
                                    </ul>
                                    <?= Html::a(Yii::t('common', 'Contact Us'), ['/site/contact', 'request' => 'enterprise'], ['class' => 'btn btn-outline-primary w-100 mt-4']) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="error-<?= Html::getInputId($model, 'package_id') ?> help-block help-block-error text-danger mt-2"></div>
        </div>

        <?= $this->render('_shared-form-fields', [
            'form' => $form,
            'model' => $model,
        ]) ?>

        <div class="text-center mt-4">
            <button type="submit" class="btn btn-primary" id="btn-submit-payment">
                <span class="btn-submit-label-payment <?= $model->payment_method == PaymentMetadata::PAYMENT_METHOD_CARD ? '' : 'hidden' ?>">
                    <?= Yii::t('common', 'Pay') ?>
                    <?php if ($package = $model->getPackage()): ?>
                        <span class="btn-submit-amount"><?= Yii::$app->formatter->asCurrency($package->price, $package->currency) ?></span>
                    <?php endif; ?>
                </span>
                <?php if (array_key_exists(PaymentMetadata::PAYMENT_METHOD_BANK, $activePaymentMethods)): ?>
                    <span class="btn-submit-label-submit <?= $model->payment_method != PaymentMetadata::PAYMENT_METHOD_CARD ? '' : 'hidden' ?>"><?= Yii::t('common', 'Submit') ?></span>
                <?php endif; ?>
            </button>
        </div>
    <?php ActiveForm::end(); ?>
</div>
