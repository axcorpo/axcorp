<?php
/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $totals array per currency and status, see Affiliate::getCommissionTotals() */

use common\models\AffiliateCommission;
use frontend\modules\account\components\AffiliateAreaController;
use yii\helpers\Html;
use yii\widgets\LinkPager;

$this->title = $this->title ?: Yii::t('frontend', 'Commissions');
$this->params['breadcrumbs'][] = Html::encode($this->title);
$formatter = Yii::$app->formatter;
$statusLabels = AffiliateCommission::getStatusLabels();
?>

<div class="tab-pane fade shadow show active rounded p-4" id="affiliate-commissions" role="tabpanel">
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
						<?php // The dashboard's column order: amounts, relations, date, status last. ?>
						<th class="text-end"><?= Yii::t('frontend', 'Base (net)') ?></th>
						<th class="text-end">%</th>
						<th class="text-end"><?= Yii::t('label', 'Commission') ?></th>
						<th><?= Yii::t('label', 'Subscriber') ?></th>
						<th><?= Yii::t('frontend', 'Invoice date') ?></th>
						<th><?= Yii::t('label', 'Status') ?></th>
					</tr>
				</thead>
				<tbody>
					<?php /** @var AffiliateCommission $model */ ?>
					<?php foreach ($dataProvider->getModels() as $model): ?>
						<?php $invoiceDate = $model->invoice ? ($model->invoice->issued_at ?: $model->invoice->paid_at) : $model->earned_at; ?>
						<tr>
							<td class="text-end"><?= $formatter->asCurrency($model->base_amount, $model->currency) ?></td>
							<td class="text-end"><?= $formatter->asDecimal($model->percentage, 2) ?></td>
							<td class="text-end">
								<?= $formatter->asCurrency($model->amount, $model->currency) ?>
								<?php if ($model->getIsRectify()): ?>
									<br><small class="text-muted"><?= Yii::t('frontend', 'Refund') ?></small>
								<?php endif; ?>
							</td>
							<td><?= $model->subscriber ? Html::encode($model->subscriber->getMaskedName()) : '&mdash;' ?></td>
							<td><?= $invoiceDate ? $formatter->asDate($invoiceDate) : '&mdash;' ?></td>
							<td>
								<?= AffiliateAreaController::statusBadge($statusLabels, $model->status) ?>
								<?php if ((int) $model->status === AffiliateCommission::STATUS_PENDING && $model->payable_at): ?>
									<br><small class="text-muted"><?= Yii::t('frontend', 'Payable from {date}', ['date' => $formatter->asDate($model->payable_at)]) ?></small>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?= LinkPager::widget(AffiliateAreaController::pagerConfig($dataProvider->getPagination())) ?>

		<?php if ($totals): ?>
			<h5 class="mt-4 mb-3"><?= Yii::t('frontend', 'Totals') ?></h5>
			<div class="table-responsive">
				<table class="table table-bordered mb-0">
					<thead>
						<tr>
							<th><?= Yii::t('label', 'Currency') ?></th>
							<?php foreach ([AffiliateCommission::STATUS_PENDING, AffiliateCommission::STATUS_PAYABLE, AffiliateCommission::STATUS_PAID] as $status): ?>
								<th class="text-end"><?= Html::encode($statusLabels[$status]['label']) ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($totals as $currency => $byStatus): ?>
							<tr>
								<td><?= Html::encode($currency) ?></td>
								<?php foreach ([AffiliateCommission::STATUS_PENDING, AffiliateCommission::STATUS_PAYABLE, AffiliateCommission::STATUS_PAID] as $status): ?>
									<td class="text-end"><?= $formatter->asCurrency($byStatus[$status] ?? 0, $currency) ?></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	<?php else: ?>
		<div class="alert alert-info mb-0" role="alert"><?= Yii::t('frontend', 'There are no commissions yet.') ?></div>
	<?php endif; ?>
</div>
