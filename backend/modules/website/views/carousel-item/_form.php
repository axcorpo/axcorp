<?php

/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\CarouselItem */

use common\models\CarouselItem;
use backend\widgets\ActiveForm;
use kartik\file\FileInput;
use kartik\select2\Select2;
use yii\bootstrap\Tabs;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;

?>

<?php $form = ActiveForm::begin([
	'id' => 'carousel-item-form',
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
				<div class="col-sm-6">
					<?= $form->field($model, 'status')->widget(Select2::class, [
						'data' => ArrayHelper::getColumn(CarouselItem::getStatusLabels(), 'label'),
						'pluginLoading' => false,
						'pluginOptions' => [
							'placeholder' => Yii::t('common', 'Choose'),
						],
					]) ?>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-6">
					<?= $form->field($model, 'imageFile', [
						'options' => [
							'class' => 'form-group' . ($model->isNewRecord ? ' required' : ''),
						],
					])->widget(FileInput::class, [
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
							'required' => $model->isNewRecord,
							'msgFileRequired' => Yii::t('yii', 'Please upload a file.'),
							'allowedFileExtensions' => ['jpeg', 'jpg', 'png', 'gif'],
							'maxFileSize' => Yii::$app->settings->get('maxFileSize'),
							'dropZoneEnabled' => false,
							'showClose' => false,
							'showUpload' => false,
							'showCaption' => true,
							'showRemove' => false,
							'showPreview' => true,
							'fileActionSettings' => [
								'showDownload' => true,
								'showRemove' => true,
								'showUpload' => false,
								'showZoom' => true,
								'showDrag' => false,
							],
							'defaultPreviewContent' => $model->image ? Html::img($model->getImageUrl(), ['class' => 'img-responsive', 'alt' => '']) : null,
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
				'id' => 'tabs-carousel-item-translations',
				'items' => $i18nFields,
			]) ?>

			<div class="row">
				<div class="col-sm-6">
					<?= $form->field($model, 'target')->widget(Select2::class, [
						'data' => CarouselItem::getTargetLabels(),
						'pluginLoading' => false,
						'pluginOptions' => [
							'allowClear' => true,
							'placeholder' => Yii::t('common', 'Choose'),
						],
					]) ?>
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
