<?php
/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

use frontend\modules\account\components\AffiliateAreaController;
use frontend\modules\account\models\AffiliateSubscriberSearch;
use yii\helpers\Html;
use yii\widgets\LinkPager;

$this->title = $this->title ?: Yii::t('frontend', 'Referred Subscribers');
$this->params['breadcrumbs'][] = Html::encode($this->title);
$formatter = Yii::$app->formatter;
?>

<div class="tab-pane fade shadow show active rounded p-4" id="affiliate-subscribers" role="tabpanel">
	<?php if ($this->context->currentPage && ($content = $this->context->currentPage->content)): ?>
		<header class="section-header mb-4">
			<?= $content ?>
		</header>
	<?php endif; ?>

	<p class="text-muted small"><?= Yii::t('frontend', 'The personal data of the subscribers is partially hidden.') ?></p>

	<?php if ($dataProvider->getTotalCount()): ?>
		<div class="table-responsive">
			<table class="table table-bordered table-hover mb-3">
				<thead>
					<tr>
						<th><?= Yii::t('label', 'Name') ?></th>
						<th><?= Yii::t('label', 'Email') ?></th>
						<th><?= Yii::t('frontend', 'Subscriptions') ?></th>
						<th><?= Yii::t('frontend', 'Signup date') ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($dataProvider->getModels() as $model): ?>
						<?php // Only the masked values of formatRow() are rendered. ?>
						<?php $row = AffiliateSubscriberSearch::formatRow($model); ?>
						<tr>
							<td><?= Html::encode($row['name']) ?: '&mdash;' ?></td>
							<td><?= Html::encode($row['email']) ?: '&mdash;' ?></td>
							<td>
								<?php if ($row['subscriptions']): ?>
									<ul class="list-unstyled mb-0">
										<?php foreach ($row['subscriptions'] as $subscription): ?>
											<li>
												<?= Html::encode($subscription['package'] ?: Yii::t('label', 'Package')) ?>
												&middot; <?= Html::encode($subscription['price']) ?>
												<span class="badge bg-<?= Html::encode($subscription['statusColor']) ?>"><?= Html::encode($subscription['status']) ?></span>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php else: ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td><?= $row['signupAt'] ? $formatter->asDate($row['signupAt']) : '&mdash;' ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?= LinkPager::widget(AffiliateAreaController::pagerConfig($dataProvider->getPagination())) ?>
	<?php else: ?>
		<div class="alert alert-info mb-0" role="alert"><?= Yii::t('frontend', 'No subscriber has signed up through your links yet.') ?></div>
	<?php endif; ?>
</div>
