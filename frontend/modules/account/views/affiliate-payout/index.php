<?php
/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

use common\models\AffiliatePayout;
use frontend\modules\account\components\AffiliateAreaController;
use tws\helpers\Url;
use yii\helpers\Html;
use yii\widgets\LinkPager;

$this->title = $this->title ?: Yii::t('frontend', 'Payouts');
$this->params['breadcrumbs'][] = Html::encode($this->title);
$formatter = Yii::$app->formatter;
?>

<div class="tab-pane fade shadow show active rounded p-4" id="affiliate-payouts" role="tabpanel">
	<?php if ($this->context->currentPage && ($content = $this->context->currentPage->content)): ?>
		<header class="section-header mb-4">
			<?= $content ?>
		</header>
	<?php endif; ?>

	<?php if ($dataProvider->getTotalCount()): ?>
		<div class="table-responsive">
			<table class="table table-bordered table-hover mb-3">
				<thead>
					<tr>
						<?php // The dashboard's column order: action, amount, invoice, dates, status last. ?>
						<th><?= Yii::t('common', 'Action') ?></th>
						<th class="text-end"><?= Yii::t('label', 'Amount') ?></th>
						<th><?= Yii::t('label', 'Invoice') ?></th>
						<th><?= Yii::t('label', 'Created At') ?></th>
						<th><?= Yii::t('label', 'Paid At') ?></th>
						<th><?= Yii::t('label', 'Status') ?></th>
					</tr>
				</thead>
				<tbody>
					<?php /** @var AffiliatePayout $model */ ?>
					<?php foreach ($dataProvider->getModels() as $model): ?>
						<tr>
							<td class="text-nowrap">
								<?php $label = (int) $model->status === AffiliatePayout::STATUS_OPEN ? Yii::t('frontend', 'Upload invoice') : Yii::t('common', 'View'); ?>
								<?= Html::a(Html::encode($label), Url::to(['view', 'id' => $model->uuid]), ['class' => 'btn btn-sm btn-outline-primary']) ?>
							</td>
							<td class="text-end"><?= $formatter->asCurrency($model->amount, $model->currency) ?></td>
							<td>
								<?php if ($model->invoice_number): ?>
									<?= Html::encode($model->invoice_number) ?>
									<?= AffiliateAreaController::statusBadge(AffiliatePayout::getInvoiceStatusLabels(), $model->invoice_status) ?>
								<?php else: ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td><?= $model->created_at ? $formatter->asDate($model->created_at) : '&mdash;' ?></td>
							<td><?= $model->paid_at ? $formatter->asDate($model->paid_at) : '&mdash;' ?></td>
							<td><?= AffiliateAreaController::statusBadge(AffiliatePayout::getStatusLabels(), $model->status) ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?= LinkPager::widget(AffiliateAreaController::pagerConfig($dataProvider->getPagination())) ?>
	<?php else: ?>
		<div class="alert alert-info mb-0" role="alert"><?= Yii::t('frontend', 'There are no payouts yet. A payout is created when your payable commissions reach the minimum amount.') ?></div>
	<?php endif; ?>
</div>
