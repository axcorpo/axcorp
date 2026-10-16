<?php

/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\SupportTicketPriority */

use common\models\SupportTicketPriority;
use backend\widgets\ActiveForm;
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
		<div class="col-sm-3">
			<?= $form->field($model, 'status')->widget(Select2::class, [
				'data' => ArrayHelper::getColumn(SupportTicketPriority::getStatusLabels(), 'label'),
				'pluginLoading' => false,
				'pluginOptions' => [
					'allowClear' => false,
					'placeholder' => Yii::t('common', 'Choose'),
				],
			]) ?>
		</div>
		<div class="col-sm-3">
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
		<div class="col-sm-3">
			<div class="control-label hidden-xs">&nbsp;</div>
			<?= $form->field($model, 'default')->checkbox() ?>
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
