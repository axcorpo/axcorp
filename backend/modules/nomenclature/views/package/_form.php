<?php
/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\Package */

use common\models\Feature;
use common\models\Package;
use backend\widgets\ActiveForm;
use common\models\FeatureModule;
use common\models\ScheduledTask;
use kartik\number\NumberControl;
use kartik\select2\Select2;
use kartik\touchspin\TouchSpin;
use yii\bootstrap\Tabs;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
?>

<?php $form = ActiveForm::begin([
	'id' => mb_strtolower($model->formName()),
	'options' => [
		'novalidate' => true,
	],
	'validateOnType' => true,
]); ?>
	<div class="row">
		<div class="col-sm-2">
			<?= $form->field($model, 'status')->widget(Select2::class, [
				'data' => ArrayHelper::getColumn(Package::getStatusLabels(), 'label'),
				'pluginLoading' => false,
				'pluginOptions' => [
					'allowClear' => false,
					'placeholder' => Yii::t('common', 'Choose'),
				],
			]) ?>
		</div>
		<div class="col-sm-3">
			<?= $form->field($model, 'type')->widget(Select2::class, [
				'options' => [
					'data' => [
						'toggle-visibility' => '',
						'toggle-visibility-val' => [
							Package::TYPE_STANDARD => '.paid-package-field',
							Package::TYPE_CUSTOM => '.paid-package-field',
						],
					],
				],
				'data' => Package::getTypeLabels(),
				'pluginLoading' => false,
				'pluginOptions' => [
					'allowClear' => false,
					'placeholder' => Yii::t('common', 'Choose'),
				],
			]) ?>
		</div>
		<div class="col-sm-2">
			<?= $form->field($model, 'sort_order')->widget(TouchSpin::class, [
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
        <div class="col-sm-2">
            <div class="control-label hidden-xs">&nbsp;</div>
            <?= $form->field($model, 'default')->checkbox(['uncheck' => null]) ?>
        </div>
	</div>
	<div class="row">
		<!-- The billing cycle is offered to every type: on a free package it is the term after
		     which the subscription expires (left empty, the free subscription is lifetime). -->
		<div class="col-sm-4 paid-package-field <?= $model->type == Package::TYPE_FREE ? 'hidden' : '' ?>">
			<?php $periodField = $form->field($model, 'trial_cycle', [
				'options' => [
					'class' => 'col-xs-6',
				],
				'template' => '{input}',
			])->widget(Select2::class, [
				'data' => Package::getCycleLabels($model->trial_period > 1 ? 'plural' : null),
				'pluginLoading' => false,
				'pluginOptions' => [
					'allowClear' => false,
					'placeholder' => Yii::t('common', 'Choose'),
				],
			]) ?>
			<?= $form->field($model, 'trial_period', [
				'options' => [
					'class' => 'form-group required',
				],
				'template' => '{label}<div class="row row-no-gutters row-custom"><div class="col-xs-6">{input}</div>' . $periodField . '</div>{error}{hint}',
			])->widget(TouchSpin::class, [
				'pluginOptions' => [
					'min' => 0,
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
				'options' => [
					'class' => 'form-group' . ($model->type != Package::TYPE_FREE ? ' required' : ''),
				],
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
		<div class="col-sm-4 paid-package-field <?= $model->type == Package::TYPE_FREE ? 'hidden' : '' ?>">
			<?php $currencyField = $form->field($model, 'currency', [
				'template' => '{input}',
			])->widget(Select2::class, [
				'data' => ArrayHelper::map(\common\models\Currency::findAllCurrencies(), 'iso_code', 'formattedName'),
				'pluginLoading' => false,
				'pluginOptions' => [
					'allowClear' => false,
					'placeholder' => Yii::t('common', 'Currency'),
				],
			]) ?>
			<?= $form->field($model, 'price', [
				'options' => [
					'class' => 'form-group required',
				],
				'template' => '{label}<div class="input-group">{input}<div class="input-group-btn with-control" style="min-width: 150px;">' . $currencyField . '</div></div>{hint}{error}',
			])->widget(NumberControl::class, [
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
    <div class="row">
        <div class="col-sm-3">
            <?= $form->field($model, 'translator')->inline(true)->checkboxList([1 => Yii::t('label', 'Translator'), 2 => Yii::t('label', 'Overwrite')]) ?>
        </div>
    </div>
	<?php
		$i18nFields = [];
		foreach (\common\models\Language::findAllLanguages() as $language) {
			$i18nFields[] = [
				'label' => mb_strtoupper($language->language),
				'content' => $this->render('_i18n-fields', [
					'model' => $model,
					'form' => $form,
					'language' => $language,
				]),
				'active' => $language->language_id === Yii::$app->language,
			];
		}
	?>
	<?= Tabs::widget([
		'items' => $i18nFields,
	]) ?>

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
