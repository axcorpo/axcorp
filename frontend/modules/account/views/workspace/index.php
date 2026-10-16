<?php
/* @var $this yii\web\View */

use common\models\Workspace;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->params['breadcrumbs'][] = Html::encode($this->title);
?>

<div class="section section-md pt-0">
	<div class="container-fluid">
		<?php if ($content = $this->context->currentPage->content): ?>
			<header class="section-header">
				<?= $content ?>
			</header>
		<?php endif; ?>

		<div class="d-flex justify-content-between align-items-center mb-3">
			<?= Html::a(Yii::t('frontend', 'Request workspace creation'), '#request-workspace-steps', [
				'class' => 'btn btn-primary btn-slide-right',
				'data' => ['bs-toggle' => 'modal', 'bs-target' => '#request-workspace-steps'],
			]) ?>
			<?= Html::a(Yii::t('common', 'Buy Packages'), ['/account/payment/package'], ['class' => 'btn btn-primary btn-slide-right']) ?>
		</div>

		<?= DataTable::widget([
			'id' => 'dt-workspaces',
			'options' => [
				'class' => 'table table-bordered table-hover',
			],
			'showColumnFilters' => false,
			'clientOptions' => [
				'deferRender' => true,
				'processing' => true,
				'serverSide' => true,
				'ajax' => [
					'url' => Url::to(['workspace/dt-workspaces']),
					'method' => 'POST',
				],
				'order' => [
					[2, 'asc'],
				],
				'pageLength' => Yii::$app->settings->get('itemsPerPage'),
				'lengthMenu' => [
					'autoCreate' => true,
					'displayAll' => Yii::t('common', 'All'),
				],
				'autoWidth' => false,
				'responsive' => true,
				'drawCallback' => new JsExpression('function(settings) {
					var $firstTd = $(".dataTables_wrapper tbody td");
					var $pagination = $(".dataTables_wrapper .dataTables_paginate");
					var api = this.api();
					var pageLength = api.page.len();
					var recordsFiltered = api.page.info().recordsTotal;

					if ($firstTd.hasClass("dataTables_empty")) {
						$pagination.addClass("hide-dt-pagination");
					}
					else if (recordsFiltered > pageLength && pageLength !== -1) {
						$pagination.removeClass("hide-dt-pagination");
					}
					else {
						$pagination.addClass("hide-dt-pagination");
					}
				}'),
				'columns' => [
					[
						'data' => 'action',
						'class' => 'common\widgets\datatable\ActionColumn',
						'title' => Yii::t('common', 'Action'),
					],
					[
						'data' => 'code',
						'title' => Yii::t('label', 'Code'),
						'filter' => ['text'],
						'className' => 'col-autowidth',
					],
					[
						'data' => 'subscription',
						'title' => Yii::t('label', 'Subscription'),
						'filter' => ['text'],
					],
					[
						'data' => 'url',
						'title' => Yii::t('label', 'URL'),
						'filter' => ['text'],
					],
					[
						'data' => 'created_at',
						'title' => Yii::t('label', 'Created At'),
						'className' => 'col-autowidth',
						'filter' => ['date', 'icu:' . Yii::$app->settings->get('dateFormat')],
					],
				],
			],
		]) ?>
	</div>
</div>

<?php
// The steps a customer goes through before a workspace can be opened for them. The
// first one is checked against the account so the customer sees whether it is already
// done; the request itself goes through the helpdesk for now.
$user = Yii::$app->user->identity;
$hasActiveSubscription = $user->getSubscriber()->active()->deleted(false)->exists()
	&& \common\models\Subscription::find()
		->andWhere(['subscriber_id' => $user->subscriber->id, 'status' => \common\models\Subscription::STATUS_ACTIVE, 'deleted' => \common\models\Subscription::NO])
		->exists();
// Usage per subscription: active workspaces and the distinct users across them, next to
// the limits carried by the subscription's features (package features plus add-ons).
$usageRows = [];
if ($hasActiveSubscription) {
	$subscriptions = \common\models\Subscription::find()
		->andWhere([
			'subscriber_id' => $user->subscriber->id,
			'parent_id' => null,
			'status' => \common\models\Subscription::STATUS_ACTIVE,
			'deleted' => \common\models\Subscription::NO,
		])
		->all();
	foreach ($subscriptions as $subscription) {
		$workspaceIds = $subscription->getWorkspaces()
			->select(['id'])
			->andWhere(['status' => \common\models\Workspace::STATUS_ACTIVE, 'deleted' => \common\models\Workspace::NO])
			->column();
		$workspacesLimit = $subscription->getSubscriptionFeatureByName(\common\models\Feature::WORKSPACES);
		$usersLimit = $subscription->getSubscriptionFeatureByName(\common\models\Feature::USERS);
		$usageRows[] = [
			'name' => $subscription->getFormattedName(),
			'workspaces' => count($workspaceIds),
			'workspacesLimit' => $workspacesLimit ? (int) $workspacesLimit->value : 0,
			'users' => $workspaceIds
				? (int) \common\models\WorkspaceHasUser::find()
					->andWhere(['workspace_id' => $workspaceIds, 'deleted' => \common\models\WorkspaceHasUser::NO])
					->count('DISTINCT user_id')
				: 0,
			'usersLimit' => $usersLimit ? (int) $usersLimit->value : 0,
		];
	}
}
$steps = [
	[
		'done' => $hasActiveSubscription,
		'title' => Yii::t('frontend', 'Have an active subscription'),
		'text' => Yii::t('frontend', 'Every workspace runs on a paid package. Buy one, or make sure the subscription you already have is active.'),
		'url' => ['/account/payment/package'],
		'link' => Yii::t('common', 'Buy Packages'),
	],
	[
		'done' => false,
		'title' => Yii::t('frontend', 'Send the request'),
		'text' => Yii::t('frontend', 'Open a support ticket and let us know the domain on which you would like the workspace to be set up. Should you require assistance with the server configuration, please mention it in the same ticket and we will guide you through the process.'),
		'url' => ['/account/helpdesk/index'],
		'link' => Yii::t('common', 'Open a Ticket'),
	],
	[
		'done' => false,
		'title' => Yii::t('frontend', 'We set it up'),
		'text' => Yii::t('frontend', 'Our team creates the workspace and lets you know by email. It then appears in the list on this page.'),
		'url' => null,
		'link' => null,
	],
];
?>
<div class="modal fade" id="request-workspace-steps" tabindex="-1" aria-labelledby="request-workspace-steps-title" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<div class="modal-title" id="request-workspace-steps-title"><?= Yii::t('frontend', 'Request workspace creation') ?></div>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<ol class="list-unstyled mb-0">
					<?php foreach ($steps as $index => $step): ?>
						<li class="d-flex mb-3">
							<span class="flex-shrink-0 me-3 rounded-circle d-inline-flex align-items-center justify-content-center <?= $step['done'] ? 'bg-success text-white' : 'bg-light text-muted' ?>" style="width: 32px; height: 32px;">
								<?= $step['done'] ? '<i class="uil uil-check"></i>' : ($index + 1) ?>
							</span>
							<div>
								<div class="fw-bold"><?= Html::encode($step['title']) ?></div>
								<div class="text-muted"><?= Html::encode($step['text']) ?></div>
								<?php if ($step['url'] && !$step['done']): ?>
									<?= Html::a($step['link'], $step['url'], ['class' => 'd-inline-block mt-1']) ?>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ol>

				<?php if ($usageRows): ?>
					<table class="table table-bordered table-sm mt-2 mb-0 text-center">
						<thead>
							<tr>
								<th rowspan="2" class="text-start align-middle"><?= Yii::t('label', 'Subscription') ?></th>
								<th colspan="2"><?= Yii::t('label', 'Workspaces') ?></th>
								<th colspan="2"><?= Yii::t('label', 'Users') ?></th>
							</tr>
							<tr>
								<th class="fw-normal text-muted"><?= Yii::t('frontend', 'Included in subscription') ?></th>
								<th class="fw-normal text-muted"><?= Yii::t('label', 'Active') ?></th>
								<th class="fw-normal text-muted"><?= Yii::t('frontend', 'Included in subscription') ?></th>
								<th class="fw-normal text-muted"><?= Yii::t('label', 'Active') ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($usageRows as $row): ?>
								<tr>
									<td class="text-start"><?= Html::encode($row['name']) ?></td>
									<td><?= $row['workspacesLimit'] ?></td>
									<td><?= $row['workspaces'] ?></td>
									<td><?= $row['usersLimit'] ?></td>
									<td><?= $row['users'] ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light btn-slide-right" data-bs-dismiss="modal"><?= Yii::t('common', 'Close') ?></button>
			</div>
		</div>
	</div>
</div>
