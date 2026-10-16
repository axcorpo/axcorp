<?php

/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\Partner */

use common\models\Partner;
use backend\widgets\ActiveForm;
use kartik\file\FileInput;
use kartik\select2\Select2;
use kartik\touchspin\TouchSpin;
use yii\bootstrap\Tabs;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;

?>

<?php $form = ActiveForm::begin([
	'id' => 'partner-form',
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
			<div class="row">
				<div class="col-sm-4">
					<?= $form->field($model, 'status')->widget(Select2::class, [
						'data' => ArrayHelper::getColumn(Partner::getStatusLabels(), 'label'),
						'pluginLoading' => false,
						'pluginOptions' => [
							'allowClear' => false,
							'placeholder' => Yii::t('common', 'Choose'),
						],
					]) ?>
				</div>
				<div class="col-sm-4">
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
			</div>
			<div class="row">
				<div class="col-sm-6">
					<?= $form->field($model, 'imageFile')->widget(FileInput::class, [
						'options' => [
							'accept' => 'image/*',
							'data' => [
								'operation-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
							],
						],
						'resizeImages' => false,
						'sortThumbs' => false,
						'purifyHtml' => false,
						'pluginOptions' => [
							'allowedFileExtensions' => ['jpeg', 'jpg', 'png', 'gif'],
							'maxFileSize' => Yii::$app->settings->get('maxFileSize'),
							'dropZoneEnabled' => false,
							'showClose' => false,
							'showUpload' => false,
							'showCaption' => true,
							'showRemove' => true,
							'showPreview' => true,
							'fileActionSettings' => [
								'showDownload' => true,
								'showRemove' => true,
								'showUpload' => false,
								'showZoom' => true,
								'showDrag' => false,
							],
							'initialPreview' => $model->getImageUrl() ?: false,
							'initialPreviewConfig' => [
								[
									'caption' => $model->image,
									'downloadUrl' => $model->getImageUrl(),
								],
							],
							'initialPreviewAsData' => true,
							'initialPreviewShowDelete' => true,
							'overwriteInitial' => true,
							'deleteUrl' => Url::to(['delete-file', 'id' => $model->id]),
							'deleteExtraData' => [
								'attribute' => 'image',
							],
						],
					]) ?>
				</div>
			</div>
			<?= $form->field($model, 'name')->textInput() ?>
			<?= $form->field($model, 'url')->input('url', [
				'placeholder' => Yii::t('common', 'Example') . ': https://www.example.com',
			]) ?>

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
