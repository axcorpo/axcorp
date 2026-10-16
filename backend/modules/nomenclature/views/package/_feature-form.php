<?php
/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model backend\modules\nomenclature\models\PackageFeatureForm */

use common\models\Feature;
use backend\widgets\ActiveForm;
use kartik\touchspin\TouchSpin;
use kartik\select2\Select2;
use yii\helpers\Html;

$this->title = $model->isNewRecord
	? Yii::t('common', 'Add {item}', ['item' => Yii::t('common', 'Feature')])
	: Yii::t('common', 'Update {item}', ['item' => Yii::t('common', 'Feature')]);

// The features a package can carry are the fixed ones declared on the Feature model
// (workspaces, users), not rows of the `feature` nomenclature. The option key is the
// feature name, which is what package_feature.name stores.
$featureOptions = Feature::getFeatureLabels();
?>

<?php $form = ActiveForm::begin([
	'id' => mb_strtolower($model->formName()),
	'action' => $model->isNewRecord
		? ['create-package-feature', 'package_id' => $model->package_id]
		: ['update-package-feature', 'id' => $model->id],
	'options' => [
		'novalidate' => true,
		'class' => Yii::$app->request->isAjax ? 'modal-dialog' : '',
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
			<?= $form->field($model, 'name')->widget(Select2::class, [
				'data' => $featureOptions,
				'disabled' => !$model->isNewRecord,
				'pluginLoading' => false,
				'pluginOptions' => [
					'allowClear' => false,
					'placeholder' => Yii::t('common', 'Choose'),
				],
			]) ?>
			<?= $form->field($model, 'value')->widget(TouchSpin::class, [
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
