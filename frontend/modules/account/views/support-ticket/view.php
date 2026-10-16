<?php
/* @var $this yii\web\View */
/* @var $model common\models\SupportTicket */

use yii\helpers\Html;

$this->title = Yii::t('common', 'View {item}', ['item' => Yii::t('common', 'Support Ticket') . " (#{$model->getDocumentSeriesNumber()}) - {$model->subject}"]);
?>

<?php if (Yii::$app->request->isAjax): ?>
<div class="modal-dialog modal-lg">
	<div class="modal-content support-ticket-view">
		<div class="modal-header support-ticket-view__header">
			<div class="modal-title support-ticket-view__title"><?= Html::encode(Yii::t('common', 'Support Ticket')) ?> #<?= Html::encode($model->getDocumentSeriesNumber()) ?> &mdash; <?= Html::encode($model->subject) ?></div>
			<button type="button" class="btn-close support-ticket-view__close" data-bs-dismiss="modal" aria-label="<?= Html::encode(Yii::t('common', 'Close')) ?>"></button>
		</div>
		<div class="modal-body support-ticket-view__body">
			<div class="support-ticket-view__layout">
				<?= $this->render('_thread', ['model' => $model]) ?>
			</div>
		</div>
		<div class="modal-footer support-ticket-view__footer">
			<button type="button" class="btn btn-light btn-slide-right" data-bs-dismiss="modal"><?= Yii::t('common', 'Close') ?></button>
		</div>
	</div>
</div>
<?php else: ?>
	<div class="support-ticket-view">
		<?= $this->render('_thread', ['model' => $model]) ?>
	</div>
<?php endif; ?>
