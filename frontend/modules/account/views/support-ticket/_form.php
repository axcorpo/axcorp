<?php
/* @var $this yii\web\View */
/* @var $form common\widgets\ActiveForm */
/* @var $model common\models\SupportTicket */
/* @var $supportTicketCommentModel common\models\SupportTicket */

use common\models\Subscription;
use common\widgets\ActiveForm;
use common\models\SupportTicketComment;
use kartik\file\FileInput;
use tws\widgets\tinymce\TinyMCE;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;

$shouldRenderModal = Yii::$app->request->isAjax;
?>

<?php $form = ActiveForm::begin([
	'id' => mb_strtolower($model->formName()),
	'options' => [
		'novalidate' => true,
		'class' => $shouldRenderModal ? 'modal-dialog modal-lg' : 'support-ticket-form support-ticket-form--page',
	],
	'validateOnType' => true,
	'validateOnBlur' => false,
]); ?>
	<div class="form-body <?= $shouldRenderModal ? 'modal-content support-ticket-form' : '' ?>">
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

			<?php if ($model->isNewRecord): ?>
				<div class="row">
					<div class="col-sm-3">
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
					<div class="col-sm-3">
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
					<div class="col-sm-3">
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
					<div class="col-sm-3">
						<?= $form->field($model, 'subscription_id')->widget(Select2::class, [
							'data' => ArrayHelper::map(Yii::$app->user->identity->subscriber->subscriptions, 'uuid', 'formattedName', function (Subscription $subscription) {
								return $subscription->parent_id ? Yii::t('common', 'Features') : Yii::t('common', 'Packages');
							}),
							'pluginLoading' => false,
							'pluginOptions' => [
								'allowClear' => true,
								'escapeMarkup' => new JsExpression('function (markup) { return markup; }'),
								'placeholder' => Yii::t('common', 'Choose'),
							],
						]) ?>
					</div>
				</div>
				<?= $form->field($model, 'subject')->textInput() ?>
				<?= $form->field($model, 'content', ['options' => ['class' => 'form-group support-ticket-form__field support-ticket-form__field--editor']])->widget(TinyMCE::class, [
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
				<?= $form->field($model, 'attachmentFiles[]', ['options' => ['class' => 'form-group support-ticket-form__attachments support-ticket-form__file-wrap']])->widget(FileInput::class, [
					'options' => [
						'multiple' => true,
						'accept' => 'image/*',
					],
					'resizeImages' => false,
					'sortThumbs' => false,
					'purifyHtml' => false,
					'pluginOptions' => [
						'maxFileSize' => Yii::$app->settings->get('maxFileSize'),
						'maxFileCount' => 5,
						'allowedFileExtensions' => Yii::$app->params['image.extensions'],
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
						'showRemove' => true,
						'showPreview' => true,
						'hideThumbnailContent' => false,
						'fileActionSettings' => [
							'showDownload' => false,
							'downloadTitle' => Yii::t('common', 'Download'),
							'showRemove' => false,
							'removeTitle' => Yii::t('common', 'Delete'),
							'showUpload' => false,
							'showZoom' => false,
							'showDrag' => false,
						],
						'preferIconicPreview' => true,
					],
				]) ?>
			<?php else: ?>
				<?= $form->field($model, 'support_ticket_status_id')->widget(Select2::class, [
					'data' => ArrayHelper::map(\common\models\SupportTicketStatus::findAllSupportTicketStatuses(), 'uuid', 'formattedName'),
					'pluginLoading' => false,
					'pluginOptions' => [
						'allowClear' => false,
						'escapeMarkup' => new JsExpression('function (markup) { return markup; }'),
						'placeholder' => Yii::t('common', 'Choose'),
					],
				]) ?>
				<?= $form->field($supportTicketCommentModel, 'content', ['options' => ['class' => 'form-group support-ticket-form__field support-ticket-form__field--editor']])->widget(TinyMCE::class, [
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
				])->label(Yii::t('label', 'Reply Message')) ?>
				<?= $form->field($supportTicketCommentModel, 'attachmentFiles[]', ['options' => ['class' => 'form-group support-ticket-form__attachments support-ticket-form__file-wrap']])->widget(FileInput::class, [
					'options' => [
						'multiple' => true,
						'accept' => 'image/*',
					],
					'resizeImages' => false,
					'sortThumbs' => false,
					'purifyHtml' => false,
					'pluginOptions' => [
						'maxFileSize' => Yii::$app->settings->get('maxFileSize'),
						'maxFileCount' => 3,
						'allowedFileExtensions' => Yii::$app->params['image.extensions'],
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
						'showRemove' => true,
						'showPreview' => true,
						'hideThumbnailContent' => false,
						'fileActionSettings' => [
							'showDownload' => false,
							'showRemove' => true,
							'removeTitle' => Yii::t('common', 'Delete'),
							'showUpload' => false,
							'showZoom' => false,
							'showDrag' => false,
						],
						'preferIconicPreview' => true,
					],
				]) ?>
				<section class="support-ticket-form__messages" aria-label="<?= Html::encode(Yii::t('common', 'Messages')) ?>">
					<h5 class="support-ticket-form__section-title"><?= Html::encode(Yii::t('common', 'Messages')) ?></h5>
					<div class="support-ticket-form__messages-scroll">
						<?= $this->render('_thread', ['model' => $model]) ?>
					</div>
				</section>
			<?php endif; ?>
		</div>

		<?php if ($shouldRenderModal): ?>
			<div class="modal-footer">
				<button type="button" class="btn btn-light btn-slide-right" data-bs-dismiss="modal"><?= Yii::t('common', 'Cancel') ?></button>
				<button type="submit" class="btn btn-primary btn-slide-right"><?= Yii::t('common', 'Submit') ?></button>
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
