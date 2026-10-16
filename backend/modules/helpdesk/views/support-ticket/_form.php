<?php
/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\SupportTicket */

use backend\widgets\ActiveForm;
use kartik\file\FileInput;
use tws\helpers\Url;
use tws\widgets\tinymce\TinyMCE;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\web\JsExpression;
?>

<?php $form = ActiveForm::begin([
	'id' => mb_strtolower($model->formName()),
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
					<?= $form->field($model, 'support_ticket_status_id')->widget(Select2::class, [
						'data' => ArrayHelper::map(\common\models\SupportTicketStatus::findAllSupportTicketStatuses(), 'uuid', 'formattedName'),
						'pluginLoading' => false,
						'pluginOptions' => [
							'allowClear' => false,
							'escapeMarkup' => new JsExpression('function (markup) { return markup; }'),
							'placeholder' => Yii::t('common', 'Choose'),
						],
					]) ?>
				</div>
				<div class="col-sm-4">
					<?= $form->field($model, 'support_ticket_department_id')->widget(Select2::class, [
						'data' => ArrayHelper::map(\common\models\SupportTicketDepartment::findAllSupportTicketDepartments(), 'uuid', 'translation.name'),
						'pluginLoading' => false,
						'pluginOptions' => [
							'allowClear' => false,
							'escapeMarkup' => new JsExpression('function (markup) { return markup; }'),
							'placeholder' => Yii::t('common', 'Choose'),
						],
					]) ?>
				</div>
				<div class="col-sm-4">
					<?= $form->field($model, 'support_ticket_priority_id')->widget(Select2::class, [
						'data' => ArrayHelper::map(\common\models\SupportTicketPriority::findAllSupportTicketPriorities(), 'uuid', 'formattedName'),
						'pluginLoading' => false,
						'pluginOptions' => [
							'allowClear' => false,
							'escapeMarkup' => new JsExpression('function (markup) { return markup; }'),
							'placeholder' => Yii::t('common', 'Choose'),
						],
					]) ?>
				</div>
			</div>
			<?= $form->field($model, 'subject')->textInput() ?>
			<?php if ($model->isNewRecord): ?>
				<?= $form->field($model, 'content')->widget(TinyMCE::class, [
					'clientOptions' => [
						'mode' => 'none',
						'menubar' => false,
						'forced_root_block' => '',
						'plugins' => 'paste autoresize link autolink lists wordcount contextmenu codesample',
						'toolbar1' => 'undo redo | bold italic | link | alignleft aligncenter alignright alignjustify | numlist bullist | codesample | removeformat',
						'paste_as_text' => true,
						'autoresize_on_init' => true,
						'autoresize_min_height' => 100,
						'autoresize_bottom_margin' => 5,
					],
				]) ?>
			<?php endif; ?>

			<?php $attachments = $model->getAttachments(true, true); ?>
			<?= $form->field($model, 'attachmentFiles[]')->widget(FileInput::class, [
				'options' => [
					'multiple' => true,
					'data' => [
						'operation-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
					],
				],
				'resizeImages' => false,
				'sortThumbs' => false,
				'purifyHtml' => false,
				'pluginOptions' => [
					'maxFileSize' => Yii::$app->settings->get('maxFileSize'),
					'maxFileCount' => 5,
					'validateInitialCount' => true,
					'layoutTemplates' => [
						'modalMain' => '',
						'modal' => '',
					],
					'frameClass' => 'krajee-default file-preview-frame-nofigure',
					'dropZoneEnabled' => false,
					'showClose' => false,
					'showUpload' => false,
					'showCaption' => true,
					'showRemove' => false,
					'showPreview' => true,
					'hideThumbnailContent' => false,
					'fileActionSettings' => [
						'showDownload' => true,
						'downloadTitle' => Yii::t('common', 'Download'),
						'showRemove' => true,
						'removeTitle' => Yii::t('common', 'Delete'),
						'showUpload' => false,
						'showZoom' => false,
						'showDrag' => false,
					],
					'initialPreview' => $attachments ? array_column($attachments, 'url') : false,
					'initialPreviewConfig' => array_map(function ($attachment) {
						return [
							'key' => $attachment['name'],
							'caption' => $attachment['name'],
							'size' => $attachment['size'],
							'downloadUrl' => $attachment['url'],
						];
					}, $attachments),
					'initialPreviewAsData' => false,
					'initialPreviewShowDelete' => true,
					'overwriteInitial' => false,
					'preferIconicPreview' => true,
					'deleteUrl' => Url::to(['delete-file', 'id' => $model->id]),
					'deleteExtraData' => [
						'attribute' => 'attachment',
					],
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
