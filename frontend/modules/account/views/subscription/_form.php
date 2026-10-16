<?php
/* @var $this yii\web\View */
/* @var $form common\widgets\ActiveForm */
/* @var $model common\models\Subscription */

use common\models\Company;
use common\widgets\ActiveForm;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

$shouldRenderModal = Yii::$app->request->isAjax;
?>

<?php $form = ActiveForm::begin([
	'id' => mb_strtolower($model->formName()),
	'options' => [
		'novalidate' => true,
		'class' => $shouldRenderModal ? 'modal-dialog modal-lg' : '',
	],
	'validateOnType' => true,
]); ?>
	<div class="form-body <?= $shouldRenderModal ? 'modal-content' : '' ?>">
		<?php if ($shouldRenderModal): ?>
			<div class="modal-header">
				<div class="modal-title"><?= $this->title ?></div>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
		<?php endif; ?>

		<div class="form-fields <?= $shouldRenderModal ? 'modal-body' : '' ?>">
			<?php if ($model->hasErrors() && empty(array_intersect_key($model->errors, $model->attributes))): ?>
				<?= $form->errorSummary($model, [
					'header' => false,
					'class' => 'alert alert-danger alert-icon',
				]) ?>
			<?php endif; ?>

			<?php if (Yii::$app->settings->get('enableRecurringPayment', 'payment')): ?>
				<?= $form->field($model, 'enable_recurring_payment')->checkbox() ?>
			<?php endif; ?>

			<?= $form->field($model, 'company_id')->widget(Select2::class, [
				'data' => ArrayHelper::map($model->subscriber->user->getCompanies()->active()->deleted(false)->all(), 'uuid', 'name'),
				'pluginLoading' => false,
				'pluginOptions' => [
					'allowClear' => true,
					'placeholder' => Yii::t('common', 'Choose'),
				],
			])->hint(Yii::t('frontend', 'Select a company for which invoices will be issued.')) ?>
		</div>

		<?php if ($shouldRenderModal): ?>
			<div class="modal-footer">
				<button type="button" class="btn btn-light btn-slide-right" data-bs-dismiss="modal"><?= Yii::t('common', 'Cancel') ?></button>
				<button type="submit" class="btn btn-default btn-slide-right"><?= Yii::t('common', 'Save') ?></button>
			</div>
		<?php else: ?>
			<div class="form-actions floating">
				<?= Html::submitButton('<span class="fa fa-check"></span>', [
					'class' => 'btn btn-xlg btn-fab btn-default',
					'title' => Yii::t('common', 'Save'),
					'data' => [
						'toggle' => 'tooltip',
					],
				]) ?>
			</div>
		<?php endif; ?>
	</div>
<?php ActiveForm::end(); ?>
