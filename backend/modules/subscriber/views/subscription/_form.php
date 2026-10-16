<?php
/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\Subscription */

use backend\widgets\ActiveForm;
use common\models\Feature;
use common\models\Package;
use common\models\FeatureModule;
use common\models\ScheduledTask;
use common\models\Subscription;
use kartik\number\NumberControl;
use kartik\select2\Select2;
use kartik\touchspin\TouchSpin;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
?>


<?php $form = ActiveForm::begin([
	'id' => 'subscription-form',
	'options' => [
		'novalidate' => true,
		'class' => Yii::$app->request->isAjax ? 'modal-dialog modal-lg' : '',
	],
	'validateOnType' => true,
]); ?>
	<div class="form-body <?= Yii::$app->request->isAjax ? 'modal-content' : '' ?>">
		<?php if (Yii::$app->request->isAjax): ?>
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
				<div class="modal-title"><?= $this->title ?></div>
			</div>
		<?php endif; ?>

		<div class="form-fields <?= Yii::$app->request->isAjax ? 'modal-body' : '' ?>">
			<?php $packages = Package::findPaidPackages(); ?>
			<?= $form->field($model, 'package_id')->widget(Select2::class, [
				'disabled' => !$model->isNewRecord,
				'options' => [
					'options' => ArrayHelper::map($packages, 'uuid', function (Package $package) {
						return [
							'data' => [
								'type' => $package->type,
								'price' => $package->price,
								'currency' => $package->currency,
								'trial_period' => $package->trial_period,
								'trial_cycle' => $package->trial_cycle,
								'billing_period' => $package->billing_period,
								'billing_cycle' => $package->billing_cycle,
							],
						];
					}),
					'data' => [
						'autofill-target' => '#custom-package-fields',
						'autofill-data' => Json::encode([
							'price' => '#' . Html::getInputId($model, 'price'),
							'currency' => '#' . Html::getInputId($model, 'currency'),
							'trial_period' => '#' . Html::getInputId($model, 'trial_period'),
							'trial_cycle' => '#' . Html::getInputId($model, 'trial_cycle'),
							'billing_period' => '#' . Html::getInputId($model, 'billing_period'),
							'billing_cycle' => '#' . Html::getInputId($model, 'billing_cycle'),
						]),
						'autofill-trigger-event' => 'change',
						'autofill-clear-empty' => 'true',
					],
				],
				'data' => ArrayHelper::map($packages, 'uuid', 'translation.name', function (Package $package) {
					return Package::getTypeLabels()[$package->type];
				}),
				'pluginLoading' => false,
				'pluginOptions' => [
					'allowClear' => false,
					'placeholder' => Yii::t('common', 'Choose'),
				],
				'pluginEvents' => [
					'change' => new \yii\web\JsExpression('function (e) {
						var $currentTarget = $(e.currentTarget),
							$selectedOption = $currentTarget.find("option:selected"),
							$customPackageFields = $("#custom-package-fields"),
							$excludedInputs = $();
						$excludedInputs = $excludedInputs.add($("#' . Html::getInputId($model, 'billing_period') . '"));
						$excludedInputs = $excludedInputs.add($("#' . Html::getInputId($model, 'billing_period') . '").closest(".bootstrap-touchspin").find("button"));
						$excludedInputs = $excludedInputs.add($("#' . Html::getInputId($model, 'billing_cycle') . '"));
						var $standardInputs = $customPackageFields.find(":input").filter(function (index, input) {
							return !$excludedInputs.is(input);
						});
						$customPackageFields.toggleClass("hidden", !$currentTarget.val());
						$standardInputs.prop("disabled", $selectedOption.data("type") == "' . Package::TYPE_STANDARD . '");
					}'),
				],
			]) ?>
			<?php $isCustomPackageFieldDisabled = ($model->type == Subscription::TYPE_STANDARD || $model->type == Subscription::TYPE_FEATURE); ?>
			<div id="custom-package-fields" class="<?= $model->isNewRecord ? 'hidden' : '' ?>">
				<div class="row">
					<div class="col-sm-4">
						<?php $periodField = $form->field($model, 'trial_cycle', [
							'options' => [
								'class' => 'col-xs-6',
							],
							'template' => '{input}',
						])->widget(Select2::class, [
							'disabled' => $isCustomPackageFieldDisabled,
							'data' => Package::getCycleLabels((!$model->trial_period || $model->trial_period > 1) ? 'plural' : null),
							'pluginLoading' => false,
							'pluginOptions' => [
								'allowClear' => false,
								'placeholder' => Yii::t('common', 'Choose'),
							],
						]) ?>
						<?= $form->field($model, 'trial_period', [
							'template' => '{label}<div class="row row-no-gutters row-custom"><div class="col-xs-6">{input}</div>' . $periodField . '</div>{error}{hint}',
						])->widget(TouchSpin::class, [
							'disabled' => $isCustomPackageFieldDisabled,
							'pluginOptions' => [
								'min' => 1,
								'max' => PHP_INT_MAX,
								'step' => 1,
								'decimals' => 0,
								'boostat' => 5,
								'maxboostedstep' => 10,
								'verticalbuttons' => true,
							],
						]) ?>
					</div>
					<div class="col-sm-4">
						<?php $periodField = $form->field($model, 'billing_cycle', [
							'options' => [
								'class' => 'col-xs-6',
							],
							'template' => '{input}',
						])->widget(Select2::class, [
							'data' => Package::getCycleLabels($model->billing_period > 1 ? 'plural' : null),
							'pluginLoading' => false,
							'pluginOptions' => [
								'allowClear' => false,
								'placeholder' => Yii::t('common', 'Choose'),
							],
						]) ?>
						<?= $form->field($model, 'billing_period', [
							'template' => '{label}<div class="row row-no-gutters row-custom"><div class="col-addon input-group-addon">' . Yii::t('common', 'At Each') . '</div><div class="col-xs-6">{input}</div>' . $periodField . '</div>{error}{hint}',
						])->widget(TouchSpin::class, [
							'pluginOptions' => [
								'min' => 1,
								'max' => PHP_INT_MAX,
								'step' => 1,
								'decimals' => 0,
								'boostat' => 5,
								'maxboostedstep' => 10,
								'verticalbuttons' => true,
							],
						]) ?>
					</div>
					<div class="col-sm-4">
						<?php $currencyField = $form->field($model, 'currency')->widget(Select2::class, [
							'disabled' => $isCustomPackageFieldDisabled,
							'data' => ArrayHelper::map(\common\models\Currency::findAllCurrencies(), 'iso_code', 'formattedName'),
							'pluginLoading' => false,
							'pluginOptions' => [
								'allowClear' => false,
								'placeholder' => Yii::t('common', 'Currency'),
							],
						])->label(false) ?>
						<?= $form->field($model, 'price', [
							'template' => '{label}<div class="input-group">{input}<div class="input-group-btn with-control" style="min-width: 150px;">' . $currencyField . '</div></div>{hint}{error}',
						])->widget(NumberControl::class, [
							'disabled' => $isCustomPackageFieldDisabled,
							'maskedInputOptions' => [
								'groupSeparator' => '.',
								'radixPoint' => ',',
								'allowMinus' => false,
							],
							'displayOptions' => [
								'autocomplete' => 'off',
								'placeholder' => '0.0000',
							],
						]) ?>
					</div>
				</div>

				<div class="panel blue-hoki">
					<div class="panel-title">
						<div class="panel-heading"><?= Yii::t('label', 'Features') ?></div>
					</div>
					<div class="panel-body">
						<div class="row">
							<div class="col-sm-6 col-lg-6">
								<?= $form->field($model, Feature::WORKSPACES)->widget(TouchSpin::class, [
									'disabled' => $isCustomPackageFieldDisabled,
									'pluginOptions' => [
										'min' => 1,
										'max' => PHP_INT_MAX,
										'step' => 1,
										'decimals' => 0,
										'boostat' => 5,
										'maxboostedstep' => 10,
										'verticalbuttons' => true,
									],
								]) ?>
							</div>
						</div>
                    </div>
				</div>
			</div>
		</div>

		<?php if (Yii::$app->request->isAjax): ?>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal"><?= Yii::t('common', 'Cancel') ?></button>
				<?= Html::submitButton(Yii::t('common', 'Save'), ['class' => 'btn btn-success']) ?>
			</div>
		<?php else: ?>
			<div class="form-actions floating">
				<?= Html::submitButton('<span class="fa fa-check"></span>', [
					'class' => 'btn btn-xlg btn-fab btn-success',
					'title' => Yii::t('common', 'Save'),
					'data' => [
						'toggle' => 'tooltip',
					],
				]) ?>
			</div>
		<?php endif; ?>
	</div>
<?php ActiveForm::end(); ?>
