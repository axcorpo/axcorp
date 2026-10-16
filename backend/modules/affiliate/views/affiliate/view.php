<?php

/* @var $this yii\web\View */
/* @var $model common\models\Affiliate */
/* @var $payableSummary array see AffiliatePayoutService::getPayableSummary() */
/* @var $stats common\services\AffiliateStatsService the last 12 months of this affiliate */
/* @var $rejectForm backend\modules\affiliate\models\AffiliateRejectForm */

use backend\widgets\ActiveForm;
use common\models\Affiliate;
use common\models\AffiliateCommission;
use yii\helpers\Html;
use yii\widgets\DetailView;

$user = $model->user;
$company = $model->company;
$this->title = Yii::t('common', 'View {item}', ['item' => Yii::t('common', 'Affiliate')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Affiliates'),
		'url' => ['index'],
	],
	$model->code,
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewAffiliate'),
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
	[
		'visible' => Yii::$app->user->can('updateAffiliate'),
		'tag' => 'a',
		'url' => ['update', 'id' => $model->uuid],
		'icon' => 'fa fa-edit',
		'options' => [
			'class' => 'btn btn-sm btn-primary',
			'title' => Yii::t('common', 'Update'),
			'data' => [
				'toggle' => 'tooltip',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('deleteAffiliate'),
		'tag' => 'a',
		'url' => ['delete', 'id' => $model->uuid],
		'icon' => 'fa fa-trash',
		'options' => [
			'class' => 'btn btn-sm btn-danger',
			'title' => Yii::t('common', 'Delete'),
			'data' => [
				'toggle' => 'tooltip',
				'confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
				'method' => 'POST',
			],
		],
	],
];

$totals = $model->getCommissionTotals();
$dash = '&mdash;';
// Applied from the public page: the application stays shown after the review too.
$isApplication = $model->application_message !== null || in_array((int) $model->status, [Affiliate::STATUS_PENDING, Affiliate::STATUS_REJECTED], true);
?>

<?php if ($isApplication): ?>
	<div class="portlet box blue-hoki">
		<div class="portlet-title">
			<div class="caption"><?= Yii::t('common', 'Affiliate Application') ?></div>
		</div>
		<div class="portlet-body">
			<div class="table-responsive">
				<?= DetailView::widget([
					'model' => $model,
					'options' => [
						'class' => 'table table-striped table-bordered detail-view detail-view-fixed',
					],
					'attributes' => array_filter([
						'created_at:datetime',
						[
							'format' => 'ntext',
							'attribute' => 'application_message',
						],
						$model->reviewed_at ? 'reviewed_at:datetime' : null,
						$model->rejection_reason !== null ? [
							'format' => 'ntext',
							'attribute' => 'rejection_reason',
						] : null,
					]),
				]) ?>
			</div>
			<?php if ($model->getIsPending() && Yii::$app->user->can('updateAffiliate')): ?>
				<p class="help-block"><?= Yii::t('backend', 'Check the code and the commission terms (Update) before approving; once approved, the affiliate is active and its links start attributing signups.') ?></p>
				<?= Html::beginForm(['approve', 'id' => $model->uuid], 'post', ['class' => 'inline-block mb-10']) ?>
					<?= Html::submitButton('<span class="fa fa-check"></span> ' . Yii::t('common', 'Approve application'), [
						'class' => 'btn btn-sm btn-success',
						'data' => [
							'confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
						],
					]) ?>
				<?= Html::endForm() ?>
				<?php $form = ActiveForm::begin([
					'id' => 'affiliate-reject-form',
					'action' => ['reject', 'id' => $model->uuid],
					'options' => [
						'novalidate' => true,
					],
				]); ?>
					<?= $form->field($rejectForm, 'reason')->textarea(['rows' => 3]) ?>
					<?= Html::submitButton('<span class="fa fa-times"></span> ' . Yii::t('common', 'Reject application'), [
						'class' => 'btn btn-sm btn-danger',
						'data' => [
							'confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
						],
					]) ?>
				<?php ActiveForm::end(); ?>
			<?php endif; ?>
		</div>
	</div>
<?php endif; ?>

<div class="row">
	<div class="col-md-6">
		<div class="portlet box blue-hoki">
			<div class="portlet-title">
				<div class="caption"><?= Yii::t('common', 'Affiliate') ?></div>
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
								'label' => Yii::t('label', 'Name'),
								'value' => $user ? $user->getFullName() : null,
							],
							[
								'format' => 'raw',
								'label' => Yii::t('label', 'Email'),
								'value' => $user && $user->email ? Html::a(Html::encode($user->email), 'mailto:' . $user->email) : $dash,
							],
							[
								'label' => Yii::t('label', 'Phone'),
								'value' => $user ? $user->phone : null,
							],
							'code',
							[
								'attribute' => 'commission_percentage',
								'value' => Yii::$app->formatter->asDecimal($model->commission_percentage, 2) . '%',
							],
							[
								'attribute' => 'commission_months',
								'value' => $model->commission_months ?: Yii::t('common', 'Lifetime'),
							],
							[
								'format' => 'raw',
								'attribute' => 'status',
								'value' => Html::tag('span', Affiliate::getStatusLabels()[$model->status]['label'] ?? '', ['class' => 'label label-' . (Affiliate::getStatusLabels()[$model->status]['color'] ?? 'default')]),
							],
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
			</div>
		</div>
	</div>
	<div class="col-md-6">
		<div class="portlet box blue-hoki">
			<div class="portlet-title">
				<div class="caption"><?= Yii::t('common', 'Billing Company') ?></div>
			</div>
			<div class="portlet-body">
				<?php if ($company): ?>
					<div class="table-responsive">
						<?= DetailView::widget([
							'model' => $company,
							'options' => [
								'class' => 'table table-striped table-bordered detail-view detail-view-fixed',
							],
							'attributes' => [
								'name',
								'tin',
								'registration_number',
								[
									'label' => Yii::t('label', 'Address'),
									'value' => $company->fullAddress,
								],
								'bank',
								[
									'label' => Yii::t('label', 'IBAN'),
									'value' => $company->bank_account,
								],
							],
						]) ?>
					</div>
				<?php else: ?>
					<div class="text-info-icon fa-info-circle"><?= Yii::t('common', 'The affiliate has not filled in a billing company yet.') ?></div>
				<?php endif; ?>
			</div>
		</div>

		<div class="portlet box blue-hoki">
			<div class="portlet-title">
				<div class="caption"><?= Yii::t('common', 'Commission Totals') ?></div>
			</div>
			<div class="portlet-body">
				<?php if ($totals): ?>
					<table class="table table-bordered table-condensed">
						<thead>
							<tr>
								<th><?= Yii::t('label', 'Currency') ?></th>
								<th class="text-right"><?= Yii::t('label', 'Pending') ?></th>
								<th class="text-right"><?= Yii::t('label', 'Payable') ?></th>
								<th class="text-right"><?= Yii::t('label', 'Paid') ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($totals as $currency => $byStatus): ?>
								<tr>
									<td><?= Html::encode($currency) ?></td>
									<?php foreach ([AffiliateCommission::STATUS_PENDING, AffiliateCommission::STATUS_PAYABLE, AffiliateCommission::STATUS_PAID] as $status): ?>
										<td class="text-right"><?= Yii::$app->formatter->asDecimal($byStatus[$status] ?? 0, 2) ?></td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else: ?>
					<div class="text-info-icon fa-info-circle"><?= Yii::t('common', 'No records found.') ?></div>
				<?php endif; ?>

				<?php if ($payableSummary && Yii::$app->user->can('manageAffiliatePayout')): ?>
					<h5><strong><?= Yii::t('common', 'Available for a new payout') ?></strong></h5>
					<?php foreach ($payableSummary as $currency => $summary): ?>
						<div class="mb-10">
							<?= Html::encode(Yii::$app->formatter->asDecimal($summary['amount'], 2) . ' ' . $currency) ?>
							<small class="text-muted">(<?= Yii::t('common', '{count, plural, one{# commission} other{# commissions}}', ['count' => $summary['count']]) ?>)</small>
							<?php if ($summary['canCreate']): ?>
								<?= Html::a('<span class="fa fa-money"></span> ' . Yii::t('common', 'Create payout'), ['/affiliate-manager/payout/create', 'affiliate_id' => $model->uuid, 'currency' => $currency], [
									'class' => 'btn btn-xs btn-success',
								]) ?>
							<?php else: ?>
								<small class="text-warning"><?= Yii::t('common', 'Below the minimum payout or not positive.') ?></small>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<?php /* The same charts the affiliate sees on its dashboard, scoped to it. */ ?>
<h4 class="affiliate-charts-heading"><?= Yii::t('common', 'Last 12 months') ?></h4>
<div class="row">
	<div class="col-lg-6">
		<?= $this->render('/report/_chart-signups', ['stats' => $stats, 'id' => 'chart-signups']) ?>
	</div>
	<div class="col-lg-6">
		<?= $this->render('/report/_chart-commissions', ['stats' => $stats, 'id' => 'chart-commissions', 'currency' => null]) ?>
	</div>
</div>

<div class="portlet box blue-hoki">
	<div class="portlet-title">
		<div class="caption"><?= Yii::t('common', 'Referral Link') ?></div>
	</div>
	<div class="portlet-body">
		<?= Html::textInput(null, $model->getReferralUrl(), ['class' => 'form-control input-sm', 'readonly' => true, 'onclick' => 'this.select();']) ?>
	</div>
</div>

<div class="portlet box blue-hoki mt-15">
	<div class="portlet-title">
		<div class="caption"><?= Yii::t('common', 'Referred Subscribers') ?></div>
		<div class="tools">
			<?= Html::a(null, 'javascript:void(0);', [
				'class' => 'collapse',
				'title' => Yii::t('common', 'Expand') . '/' . Yii::t('common', 'Collapse'),
			]) ?>
		</div>
	</div>
	<div class="portlet-body">
		<?= $this->render('/subscriber/_grid', [
			'id' => 'dt-affiliate-subscribers',
			'params' => ['affiliate_id' => $model->uuid],
		]) ?>
	</div>
</div>

<div class="portlet box blue-hoki mt-15">
	<div class="portlet-title">
		<div class="caption"><?= Yii::t('common', 'Commissions') ?></div>
		<div class="tools">
			<?= Html::a(null, 'javascript:void(0);', [
				'class' => 'collapse',
				'title' => Yii::t('common', 'Expand') . '/' . Yii::t('common', 'Collapse'),
			]) ?>
		</div>
	</div>
	<div class="portlet-body">
		<p><?= Html::a(Yii::t('common', 'Approve or cancel commissions'), ['/affiliate-manager/commission/index', 'affiliate_id' => $model->uuid]) ?></p>
		<?= $this->render('/commission/_grid', [
			'id' => 'dt-affiliate-commissions',
			'params' => ['affiliate_id' => $model->uuid],
		]) ?>
	</div>
</div>

<?php if (Yii::$app->user->can('manageAffiliatePayout')): ?>
	<div class="portlet box blue-hoki mt-15">
		<div class="portlet-title">
			<div class="caption"><?= Yii::t('common', 'Affiliate Payouts') ?></div>
			<div class="tools">
				<?= Html::a(null, 'javascript:void(0);', [
					'class' => 'collapse',
					'title' => Yii::t('common', 'Expand') . '/' . Yii::t('common', 'Collapse'),
				]) ?>
			</div>
		</div>
		<div class="portlet-body">
			<?= $this->render('/payout/_grid', [
				'id' => 'dt-affiliate-payouts',
				'params' => ['affiliate_id' => $model->uuid],
			]) ?>
		</div>
	</div>
<?php endif; ?>
