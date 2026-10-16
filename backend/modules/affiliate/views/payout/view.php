<?php

/* @var $this yii\web\View */
/* @var $model common\models\AffiliatePayout */
/* @var $rejectForm backend\modules\affiliate\models\PayoutRejectForm */
/* @var $proofForm backend\modules\affiliate\models\PayoutProofForm */
/* @var $paidForm backend\modules\affiliate\models\PayoutPaidForm */

use backend\modules\affiliate\models\PayoutProofForm;
use backend\widgets\ActiveForm;
use common\helpers\DateHelper;
use common\helpers\PrivateFileHelper;
use common\models\AffiliatePayout;
use kartik\file\FileInput;
use tws\widgets\datetimepicker\DateTimePicker;
use yii\helpers\Html;
use yii\widgets\DetailView;

$affiliate = $model->affiliate;
$this->title = Yii::t('common', 'View {item}', ['item' => Yii::t('common', 'Affiliate Payout')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Affiliate Payouts'),
		'url' => ['index'],
	],
	Yii::$app->formatter->asDecimal($model->amount, 2) . ' ' . $model->currency,
];
$this->params['actions'] = [
	[
		'visible' => true,
		'tag' => 'a',
		'url' => ['index'],
		'icon' => 'fa fa-list',
		'options' => [
			'class' => 'btn btn-sm btn-default',
			'title' => Yii::t('common', 'List'),
			'data' => [
				'toggle' => 'tooltip',
			],
		],
	],
];

$statusLabel = AffiliatePayout::getStatusLabels()[$model->status] ?? null;
$invoiceStatusLabel = AffiliatePayout::getInvoiceStatusLabels()[$model->invoice_status] ?? null;
$isOpen = $model->getIsOpen();
$canAddProofs = (int) $model->status !== AffiliatePayout::STATUS_CANCELLED;
$fileLink = function ($name, $label) use ($model) {
	return Html::a('<span class="fa fa-download"></span> ' . Html::encode($label), ['download', 'id' => $model->uuid, 'file' => $name], [
		'target' => '_blank',
		'data-pjax' => 0,
	]);
};
?>

<div class="row">
	<div class="col-md-6">
		<div class="portlet box blue-hoki">
			<div class="portlet-title">
				<div class="caption"><?= Yii::t('common', 'Affiliate Payout') ?></div>
			</div>
			<div class="portlet-body">
				<div class="table-responsive">
					<?= DetailView::widget([
						'model' => $model,
						'options' => [
							'class' => 'table table-striped table-bordered detail-view detail-view-fixed',
						],
						'attributes' => [
							[
								'format' => 'raw',
								'label' => Yii::t('label', 'Affiliate'),
								'value' => $affiliate
									? Html::a(Html::encode(trim(($affiliate->user ? $affiliate->user->getFullName() : '') . " ({$affiliate->code})")), ['affiliate/view', 'id' => $affiliate->uuid])
									: '&mdash;',
							],
							[
								'attribute' => 'amount',
								'value' => Yii::$app->formatter->asDecimal($model->amount, 2) . ' ' . $model->currency,
							],
							[
								'format' => 'raw',
								'attribute' => 'status',
								'value' => $statusLabel ? Html::tag('span', $statusLabel['label'], ['class' => 'label label-' . $statusLabel['color']]) : '&mdash;',
							],
							'paid_at:datetime',
							[
								'format' => 'ntext',
								'attribute' => 'notes',
							],
							'created_at:datetime',
							[
								'label' => Yii::t('label', 'Created By'),
								'value' => $model->creator ? $model->creator->getFullName() : null,
							],
						],
					]) ?>
				</div>
				<?php if ($affiliate && $affiliate->company): ?>
					<p>
						<strong><?= Yii::t('common', 'Billing Company') ?>:</strong>
						<?= Html::encode($affiliate->company->name) ?>,
						<?= Html::encode($affiliate->company->tin) ?>,
						<?= Yii::t('label', 'IBAN') ?> <?= Html::encode($affiliate->company->bank_account ?: '—') ?>
						<?= $affiliate->company->bank ? '(' . Html::encode($affiliate->company->bank) . ')' : '' ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="col-md-6">
		<div class="portlet box blue-hoki">
			<div class="portlet-title">
				<div class="caption"><?= Yii::t('common', 'Affiliate Invoice') ?></div>
			</div>
			<div class="portlet-body">
				<?php if ($model->invoice_file): ?>
					<p>
						<?= Html::encode($model->invoice_number) ?>
						<?= $model->invoice_date ? ' / ' . Yii::$app->formatter->asDate($model->invoice_date) : '' ?>
						<?= $invoiceStatusLabel ? Html::tag('span', $invoiceStatusLabel['label'], ['class' => 'label label-' . $invoiceStatusLabel['color']]) : '' ?>
					</p>
					<p><?= $fileLink($model->invoice_file, Yii::t('common', 'Download invoice')) ?></p>
					<?php if ($model->invoice_rejection_note): ?>
						<div class="alert alert-warning"><strong><?= Yii::t('label', 'Rejection Reason') ?>:</strong> <?= nl2br(Html::encode($model->invoice_rejection_note)) ?></div>
					<?php endif; ?>
					<?php if ($isOpen && (int) $model->invoice_status === AffiliatePayout::INVOICE_STATUS_PENDING): ?>
						<?= Html::beginForm(['accept-invoice', 'id' => $model->uuid], 'post', ['class' => 'inline-block mb-10']) ?>
							<?= Html::submitButton('<span class="fa fa-check"></span> ' . Yii::t('common', 'Accept invoice'), ['class' => 'btn btn-sm btn-success']) ?>
						<?= Html::endForm() ?>
					<?php endif; ?>
					<?php if ($isOpen && in_array((int) $model->invoice_status, [AffiliatePayout::INVOICE_STATUS_PENDING, AffiliatePayout::INVOICE_STATUS_ACCEPTED], true)): ?>
						<?php $form = ActiveForm::begin([
							'id' => 'payout-reject-form',
							'action' => ['reject-invoice', 'id' => $model->uuid],
							'options' => [
								'novalidate' => true,
							],
						]); ?>
							<?= $form->field($rejectForm, 'reason')->textarea(['rows' => 3]) ?>
							<?= Html::submitButton('<span class="fa fa-times"></span> ' . Yii::t('common', 'Reject invoice'), [
								'class' => 'btn btn-sm btn-danger',
								'data' => [
									'confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							]) ?>
						<?php ActiveForm::end(); ?>
					<?php endif; ?>
				<?php else: ?>
					<div class="text-info-icon fa-info-circle"><?= Yii::t('common', 'The affiliate has not uploaded the invoice for this payout yet.') ?></div>
				<?php endif; ?>
			</div>
		</div>

		<div class="portlet box blue-hoki">
			<div class="portlet-title">
				<div class="caption"><?= Yii::t('label', 'Payment Proofs') ?></div>
			</div>
			<div class="portlet-body">
				<?php $proofs = $model->getProofFileList(); ?>
				<?php if ($proofs): ?>
					<ul class="list-unstyled">
						<?php foreach ($proofs as $index => $proof): ?>
							<li class="mb-10">
								<?= $fileLink($proof, Yii::t('common', 'Payment proof {number}', ['number' => $index + 1])) ?>
								<?= Html::beginForm(['remove-proof', 'id' => $model->uuid], 'post', ['class' => 'inline-block']) ?>
									<?= Html::hiddenInput('file', $proof) ?>
									<?= Html::submitButton('<span class="fa fa-trash"></span>', [
										'class' => 'btn btn-xs btn-danger',
										'title' => Yii::t('common', 'Delete'),
										'data' => [
											'confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
										],
									]) ?>
								<?= Html::endForm() ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else: ?>
					<div class="text-info-icon fa-info-circle mb-10"><?= Yii::t('common', 'No records found.') ?></div>
				<?php endif; ?>
				<?php if ($canAddProofs): ?>
					<?php $form = ActiveForm::begin([
						'id' => 'payout-proof-form',
						'action' => ['upload-proof', 'id' => $model->uuid],
						'options' => [
							'enctype' => 'multipart/form-data',
							'novalidate' => true,
						],
					]); ?>
						<?php // The multiple upload widget as in helpdesk/support-ticket/_form.php. ?>
						<?= $form->field($proofForm, 'proofFiles[]')->widget(FileInput::class, [
							'options' => [
								'multiple' => true,
								'accept' => '.' . implode(',.', PrivateFileHelper::EXTENSIONS),
							],
							'resizeImages' => false,
							'sortThumbs' => false,
							'purifyHtml' => false,
							'pluginOptions' => [
								'allowedFileExtensions' => PrivateFileHelper::EXTENSIONS,
								// KB, the plugin's unit; the server checks the same limit.
								'maxFileSize' => (int) (PrivateFileHelper::getMaxSize() / 1024),
								'maxFileCount' => PayoutProofForm::MAX_FILES,
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
									'showRemove' => false,
									'showUpload' => false,
									'showZoom' => false,
									'showDrag' => false,
								],
								'preferIconicPreview' => true,
							],
						])->label(false)->hint(Yii::t('common', 'PDF, JPG or PNG; several files at once.')) ?>
						<?= Html::submitButton('<span class="fa fa-upload"></span> ' . Yii::t('common', 'Upload'), ['class' => 'btn btn-sm btn-primary']) ?>
					<?php ActiveForm::end(); ?>
				<?php endif; ?>
			</div>
		</div>

		<?php if ($isOpen): ?>
			<div class="portlet box blue-hoki">
				<div class="portlet-title">
					<div class="caption"><?= Yii::t('common', 'Settlement') ?></div>
				</div>
				<div class="portlet-body">
					<?php $form = ActiveForm::begin([
						'id' => 'payout-paid-form',
						'action' => ['mark-paid', 'id' => $model->uuid],
						'options' => [
							'class' => 'mb-10',
							'novalidate' => true,
						],
					]); ?>
						<?php $paidAt = DateHelper::parseDateTime($paidForm->paid_at); ?>
						<div class="row">
							<div class="col-sm-6">
								<?php // The picker as in subscriber/report/subscription.php. ?>
								<?= $form->field($paidForm, 'paid_at')->widget(DateTimePicker::class, [
									'options' => [
										'value' => $paidAt ? Yii::$app->formatter->asDatetime($paidAt) : $paidForm->paid_at,
										'placeholder' => Yii::$app->settings->get('datetimeFormat'),
									],
									'clientOptions' => [
										'format' => 'icu:' . Yii::$app->settings->get('datetimeFormat'),
										'maxDate' => (new \DateTime)->setTime(23, 59, 59)->format(DATE_ATOM),
										'ignoreReadonly' => true,
										'showTodayButton' => true,
										'showClear' => true,
										'showClose' => true,
										'allowInputToggle' => true,
										'useCurrent' => false,
									],
								]) ?>
							</div>
						</div>
						<?php if ((int) $model->invoice_status !== AffiliatePayout::INVOICE_STATUS_ACCEPTED): ?>
							<?= $form->field($paidForm, 'withoutInvoice')->checkbox() ?>
						<?php endif; ?>
						<?= Html::submitButton('<span class="fa fa-money"></span> ' . Yii::t('common', 'Mark as paid'), [
							'class' => 'btn btn-success',
							'data' => [
								'confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
							],
						]) ?>
					<?php ActiveForm::end(); ?>

					<?= Html::beginForm(['cancel', 'id' => $model->uuid], 'post') ?>
						<div class="form-group">
							<?= Html::label(Yii::t('common', 'Cancellation reason'), 'cancel-reason', ['class' => 'control-label']) ?>
							<?= Html::textarea('reason', '', ['id' => 'cancel-reason', 'class' => 'form-control', 'rows' => 3]) ?>
						</div>
						<?= Html::submitButton('<span class="fa fa-ban"></span> ' . Yii::t('common', 'Cancel payout'), [
							'class' => 'btn btn-danger',
							'data' => [
								'confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
							],
						]) ?>
					<?= Html::endForm() ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<div class="portlet box blue-hoki mt-15">
	<div class="portlet-title">
		<div class="caption"><?= Yii::t('common', 'Commissions') ?></div>
	</div>
	<div class="portlet-body">
		<?= $this->render('/commission/_grid', [
			'id' => 'dt-payout-commissions',
			'params' => [
				'affiliate_id' => $affiliate ? $affiliate->uuid : null,
				'payout_id' => $model->uuid,
			],
		]) ?>
	</div>
</div>
