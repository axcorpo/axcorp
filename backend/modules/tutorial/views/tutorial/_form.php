<?php

/* @var $this yii\web\View */
/* @var $form common\widgets\ActiveForm */
/* @var $model common\models\Tutorial */

use common\models\Tutorial;
use common\models\TutorialCategory;
use common\widgets\ActiveForm;
use kartik\file\FileInput;
use kartik\select2\Select2;
use yii\bootstrap\Tabs;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\web\View;

?>

<?php $form = ActiveForm::begin([
	'id' => 'tutorial-form',
	'options' => [
		'novalidate' => true,
		'class' => Yii::$app->request->isAjax ? 'modal-dialog modal-lg' : '',
	],
	'validateOnType' => true,
]); ?>
<div class="form-body <?= Yii::$app->request->isAjax ? 'modal-content' : '' ?>">
	<?php if (Yii::$app->request->isAjax) : ?>
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
			<div class="modal-title"><?= $this->title ?></div>
		</div>
	<?php endif; ?>

	<div class="form-fields <?= Yii::$app->request->isAjax ? 'modal-body' : '' ?>">
		<div class="row">
			<div class="col-sm-4">
				<?= $form->field($model, 'status')->widget(Select2::class, [
					'data' => ArrayHelper::getColumn(Tutorial::getStatusLabels(), 'label'),
					'pluginLoading' => false,
					'pluginOptions' => [
						'placeholder' => Yii::t('common', 'Choose'),
					],
				]) ?>
			</div>
			<div class="col-sm-4">
				<?= $form->field($model, 'sort_order')->input('number') ?>
			</div>
		</div>
        <div class="row">
            <div class="col-sm-12">
                <?= $form->field($model, 'category_id')->widget(Select2::class, [
                    'options' => [
                        'multiple' => false,
                    ],
                    // andWhere, not where: where() would replace the active()/deleted()
                    // conditions those scopes just added and list every leaf category.
                    'data' => ArrayHelper::map(TutorialCategory::find()->active(true)->deleted(false)->andWhere(['leaf' => 1])->all(), 'uuid', 'treeName'),
                    'maintainOrder' => false,
                    'showToggleAll' => false,
                    'pluginLoading' => false,
                    'pluginOptions' => [
                        'allowClear' => true,
                        'placeholder' => Yii::t('common', 'Choose'),
                        'escapeMarkup' => new JsExpression('function (markup) { return markup; }'),
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
		<div class="row">
			<div class="col-sm-6">
				<?= $form->field($model, 'imageFile')->widget(FileInput::class, [
					'options' => [
						'accept' => 'image/*',
					],
					'resizeImages' => false,
					'sortThumbs' => false,
					'purifyHtml' => false,
					'pluginOptions' => [
                        'theme' => 'fa',
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
						'initialPreview' => $model->imageUrl ?: false,
						'initialPreviewConfig' => [
							[
								'downloadUrl' => $model->imageUrl,
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
			<div class="col-sm-6">
				<?= $form->field($model, 'attachmentFile')->widget(FileInput::class, [
					'options' => [
						'accept' => 'file/*',
					],
					'resizeImages' => false,
					'sortThumbs' => false,
					'purifyHtml' => false,
					'pluginOptions' => [
                        'theme' => 'fa',
						'allowedFileExtensions' => ['mp4'],
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
						'initialPreview' => $model->fileUrl ?: false,
                        'initialPreviewFileType'=> 'video',
                        'initialPreviewConfig'=> [
                            'filetype'=> 'video/mp4',
                            'downloadUrl' => $model->fileUrl,
                        ],
						'initialPreviewAsData' => true,
						'initialPreviewShowDelete' => true,
						'overwriteInitial' => true,
						'deleteUrl' => Url::to(['delete-file', 'id' => $model->id]),
						'deleteExtraData' => [
							'attribute' => 'attachment',
						],
					],
				]) ?>
			</div>

			<?php if (Yii::$app->request->isAjax) : ?>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal"><?= Yii::t('common', 'Cancel') ?></button>
					<?= Html::submitButton(Yii::t('common', 'Save'), ['class' => 'btn btn-success']) ?>
				</div>
			<?php else : ?>
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
