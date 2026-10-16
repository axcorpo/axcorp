<?php
/* @var $this yii\web\View */
/* @var $model common\models\AffiliatePayout */
/* @var $invoiceForm frontend\modules\account\models\AffiliateInvoiceForm */
/* @var $affiliate common\models\Affiliate */

use common\helpers\DateHelper;
use common\helpers\PrivateFileHelper;
use common\models\AffiliatePayout;
use common\widgets\ActiveForm;
use frontend\modules\account\components\AffiliateAreaController;
use kartik\file\FileInput;
use tws\helpers\Url;
use tws\widgets\datetimepicker\DateTimePicker;
use yii\helpers\Html;

$formatter = Yii::$app->formatter;
$this->title = Yii::t('frontend', 'Payout of {date}', ['date' => $formatter->asDate($model->created_at)]);
if ($indexPage = \common\models\Page::findPageByRoute(['/account/affiliate-payout/index'])) {
	$this->params['breadcrumbs'][] = ['label' => Html::encode($indexPage->translation->title), 'url' => ['index']];
}
$this->params['breadcrumbs'][] = Html::encode($this->title);
$proofs = $model->getProofFileList();
?>

<div class="tab-pane fade shadow show active rounded p-4" id="affiliate-payout" role="tabpanel">
	<div class="table-responsive">
		<table class="table table-striped table-bordered detail-view mb-4">
			<tbody>
				<tr>
					<th><?= Yii::t('label', 'Amount') ?></th>
					<td><?= $formatter->asCurrency($model->amount, $model->currency) ?></td>
				</tr>
				<tr>
					<th><?= Yii::t('label', 'Status') ?></th>
					<td><?= AffiliateAreaController::statusBadge(AffiliatePayout::getStatusLabels(), $model->status) ?></td>
				</tr>
				<tr>
					<th><?= Yii::t('label', 'Created At') ?></th>
					<td><?= $model->created_at ? $formatter->asDate($model->created_at) : '&mdash;' ?></td>
				</tr>
				<tr>
					<th><?= Yii::t('label', 'Paid At') ?></th>
					<td><?= $model->paid_at ? $formatter->asDate($model->paid_at) : '&mdash;' ?></td>
				</tr>
				<tr>
					<th><?= Yii::t('frontend', 'Commissions included') ?></th>
					<td><?= $formatter->asInteger($model->getCommissions()->count()) ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<h5 class="mb-3"><?= Yii::t('frontend', 'Your invoice') ?></h5>
	<?php if ($model->invoice_file): ?>
		<p class="mb-2">
			<?= Html::encode($model->invoice_number) ?>
			<?php if ($model->invoice_date): ?>
				/ <?= $formatter->asDate($model->invoice_date) ?>
			<?php endif; ?>
			<?= AffiliateAreaController::statusBadge(AffiliatePayout::getInvoiceStatusLabels(), $model->invoice_status) ?>
			&middot;
			<?= Html::a(Yii::t('frontend', 'Download'), Url::to(['download', 'id' => $model->uuid, 'file' => $model->invoice_file])) ?>
		</p>
	<?php else: ?>
		<p class="text-muted mb-2"><?= Yii::t('frontend', 'You have not uploaded the invoice yet.') ?></p>
	<?php endif; ?>
	<?php if ((int) $model->invoice_status === AffiliatePayout::INVOICE_STATUS_REJECTED && $model->invoice_rejection_note): ?>
		<div class="alert alert-danger" role="alert">
			<strong><?= Yii::t('frontend', 'Your invoice was rejected') ?>:</strong>
			<?= nl2br(Html::encode($model->invoice_rejection_note)) ?>
		</div>
	<?php endif; ?>

	<?php if ($invoiceForm->getCanUpload()): ?>
		<?php $company = $affiliate->company; ?>
		<div class="alert alert-info" role="alert">
			<?= Yii::t('frontend', 'Issue an invoice of {amount} to us and upload it here (PDF, JPG or PNG).', ['amount' => $formatter->asCurrency($model->amount, $model->currency)]) ?>
			<?php if (!$company || !$company->bank_account): ?>
				<?= Yii::t('frontend', 'Add your company, with its IBAN, so that we can pay your commissions.') ?>
				<?= Html::a(Yii::t('frontend', 'Add your company'), Url::to(['/account/company/index']), ['class' => 'alert-link']) ?>
			<?php endif; ?>
		</div>
		<?php $form = ActiveForm::begin([
			'id' => 'affiliate-invoice-form',
			'action' => Url::to(['upload-invoice', 'id' => $model->uuid]),
			'method' => 'post',
			'options' => [
				'enctype' => 'multipart/form-data',
				'novalidate' => true,
			],
			'fieldConfig' => [
				'options' => ['class' => 'mb-3'],
				'labelOptions' => ['class' => 'form-label'],
			],
		]); ?>
			<div class="row">
				<div class="col-md-4">
					<?= $form->field($invoiceForm, 'invoice_number')->textInput(['class' => 'form-control', 'maxlength' => 255]) ?>
				</div>
				<div class="col-md-4">
					<?php // The picker as in profile/index.php, in the datetime format (every date is a DATETIME). ?>
					<?php $invoiceDate = DateHelper::parseDateTime($invoiceForm->invoice_date); ?>
					<?= $form->field($invoiceForm, 'invoice_date')->widget(DateTimePicker::class, [
						'options' => [
							'class' => 'form-control',
							'value' => $invoiceDate ? $formatter->asDatetime($invoiceDate) : $invoiceForm->invoice_date,
							'placeholder' => Yii::$app->settings->get('datetimeFormat'),
						],
						'clientOptions' => [
							'format' => 'icu:' . Yii::$app->settings->get('datetimeFormat'),
							'maxDate' => (new DateTime)->setTime(23, 59, 59)->format(DATE_ATOM),
							'ignoreReadonly' => true,
							'showTodayButton' => true,
							'showClear' => true,
							'showClose' => true,
							'allowInputToggle' => true,
							'useCurrent' => false,
						],
					]) ?>
				</div>
				<div class="col-md-4">
					<?php // The upload widget as in support-ticket/_form.php, one document. ?>
					<?= $form->field($invoiceForm, 'invoiceFile')->widget(FileInput::class, [
						'options' => [
							'accept' => '.pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png',
						],
						'resizeImages' => false,
						'sortThumbs' => false,
						'purifyHtml' => false,
						'pluginOptions' => [
							'allowedFileExtensions' => PrivateFileHelper::EXTENSIONS,
							// KB, the plugin's unit; the server checks the same limit.
							'maxFileSize' => (int) (PrivateFileHelper::getMaxSize() / 1024),
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
							'showCancel' => false,
							'showPreview' => false,
							'preferIconicPreview' => true,
						],
					]) ?>
				</div>
			</div>
			<?= Html::submitButton(Yii::t('frontend', 'Upload invoice'), ['class' => 'btn btn-primary']) ?>
		<?php ActiveForm::end(); ?>
	<?php endif; ?>

	<h5 class="mt-4 mb-3"><?= Yii::t('frontend', 'Payment proofs') ?></h5>
	<?php if ($proofs): ?>
		<ul class="mb-0">
			<?php foreach ($proofs as $i => $name): ?>
				<li><?= Html::a(Yii::t('frontend', 'Payment proof') . ' ' . ($i + 1), Url::to(['download', 'id' => $model->uuid, 'file' => $name])) ?></li>
			<?php endforeach; ?>
		</ul>
	<?php else: ?>
		<p class="text-muted mb-0"><?= Yii::t('frontend', 'The payment proofs will appear here once the payout is made.') ?></p>
	<?php endif; ?>
</div>
