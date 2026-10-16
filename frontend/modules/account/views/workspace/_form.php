<?php
/* @var $this yii\web\View */
/* @var $form common\widgets\ActiveForm */
/* @var $model common\models\Workspace */

use common\models\Subscriber;
use common\widgets\ActiveForm;
use common\models\Subscription;
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

			<?php
				$subscriber = Subscriber::findOne(['user_id' => Yii::$app->user->id]);
				$subscriptions = Subscription::findAvailableSubscriptions($subscriber->id, $model->isNewRecord ? true : false);
			?>
			<?= $form->field($model, 'subscription_id')->widget(Select2::class, [
				'data' => ArrayHelper::map($subscriptions, 'uuid', 'formattedName'),
				'pluginLoading' => false,
				'pluginOptions' => [
					'allowClear' => false,
					'placeholder' => Yii::t('common', 'Choose'),
					'escapeMarkup' => new \yii\web\JsExpression('function (markup) { return markup; }'),
				],
			]) ?>
			<div class="row">
				<div class="col-sm-6">
					<?= $form->field($model, 'url', [
						'template' => '{label}<div class="input-group"><div class="input-group-addon">' . Yii::$app->request->hostInfo . '/</div>{input}</div>{hint}{error}',
					])->textInput(['class' => 'form-control text-lowercase']) ?>
				</div>
			</div>
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
