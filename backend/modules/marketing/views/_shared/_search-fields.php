<?php
/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\MarketingRecipientSearchForm */

use common\models\Subscriber;
use tws\widgets\datetimepicker\DateTimePicker;
use kartik\select2\Select2;
use kartik\touchspin\TouchSpin;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
?>
<div class="row">
    <div class="col-sm-6">
        <?= $form->field($model, 'first_name')->textInput() ?>
    </div>
    <div class="col-sm-6">
        <?= $form->field($model, 'last_name')->textInput() ?>
    </div>
</div>
<div class="row">
	<div class="col-sm-4">
		<?= $form->field($model, 'date_of_birth')->widget(DateTimePicker::class, [
			'options' => [
				'value' => $model->date_of_birth ? Yii::$app->formatter->asDate($model->date_of_birth) : null,
				'placeholder' => Yii::$app->settings->get('dateFormat'),
			],
			'clientOptions' => [
				'format' => 'icu:' . Yii::$app->settings->get('dateFormat'),
				'maxDate' => (new DateTime)->format(DATE_ATOM),
				'ignoreReadonly' => true,
				'showTodayButton' => false,
				'showClear' => true,
				'showClose' => true,
				'allowInputToggle' => true,
				'useCurrent' => false,
			],
		]) ?>
	</div>
	<div class="col-sm-4">
		<?= $form->field($model, 'age')->widget(TouchSpin::class, [
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
		<?= $form->field($model, 'age_category')->widget(Select2::class, [
			'options' => [
				'multiple' => true,
			],
			'data' => Subscriber::getAgeCategoryLabels(),
			'toggleAllSettings' => [
				'selectLabel' => '<span class="glyphicon glyphicon-unchecked"></span> ' . Yii::t('common', 'Select All'),
				'unselectLabel' => '<span class="glyphicon glyphicon-check"></span> ' . Yii::t('common', 'Unselect All'),
			],
			'pluginLoading' => false,
			'pluginOptions' => [
				'allowClear' => true,
				'placeholder' => Yii::t('common', 'Choose'),
			],
		]) ?>
	</div>
</div>
<div class="row">
	<div class="col-sm-4">
		<?= $form->field($model, 'gender')->widget(Select2::class, [
			'data' => Subscriber::getGenderLabels(),
			'pluginLoading' => false,
			'pluginOptions' => [
				'allowClear' => true,
				'placeholder' => Yii::t('common', 'Choose'),
			],
		]) ?>
	</div>
	<div class="col-sm-8">
		<?= $form->field($model, 'source')->widget(Select2::class, [
			'options' => [
				'multiple' => true,
			],
			'data' => ArrayHelper::map(\common\models\Source::findAllSources(), 'uuid', 'translation.name'),
			'toggleAllSettings' => [
				'selectLabel' => '<span class="glyphicon glyphicon-unchecked"></span> ' . Yii::t('common', 'Select All'),
				'unselectLabel' => '<span class="glyphicon glyphicon-check"></span> ' . Yii::t('common', 'Unselect All'),
			],
			'pluginLoading' => false,
			'pluginOptions' => [
				'allowClear' => true,
				'placeholder' => Yii::t('common', 'Choose'),
			],
		])->label($model->getAttributeLabel('source'). ' (' . Yii::t('label', 'How did you hear about us?') . ')') ?>
	</div>
</div>
<div class="row">
		<div class="col-sm-4">
			<?= $form->field($model, 'country')->widget(Select2::class, [
				'options' => [
					'multiple' => true,
				],
				'data' => ArrayHelper::map(\common\models\Country::findAllCountries(), 'iso_alpha2', 'translation.name'),
				'toggleAllSettings' => [
					'selectLabel' => '<span class="glyphicon glyphicon-unchecked"></span> ' . Yii::t('common', 'Select All'),
					'unselectLabel' => '<span class="glyphicon glyphicon-check"></span> ' . Yii::t('common', 'Unselect All'),
				],
				'pluginLoading' => false,
				'pluginOptions' => [
					'allowClear' => true,
					'placeholder' => Yii::t('common', 'Choose'),
				],
			]) ?>
		</div>
		<div class="col-sm-4">
			<?= $form->field($model, 'county')->textInput() ?>
		</div>
		<div class="col-sm-4">
			<?= $form->field($model, 'locality')->textInput() ?>
		</div>
	</div>
