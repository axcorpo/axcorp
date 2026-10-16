<?php

/* @var $this yii\web\View */
/* @var $model common\models\Workspace */

use common\models\Subscription;
use common\models\Workspace;
use yii\helpers\Html;
use yii\widgets\DetailView;

$this->title = Yii::t('common', 'View {item}', ['item' => Yii::t('common', 'Workspace')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Subscribers'),
		'url' => ['default/index'],
	],
	[
		'label' => Yii::t('common', 'Workspaces'),
		'url' => ['workspace/index'],
	],
	$this->title,
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewWorkspace'),
		'tag' => 'a',
		'url' => ['index'],
		'icon' => 'fa fa-list',
		'options' => [
			'class' => 'btn btn-sm btn-default',
			'title' => Yii::t('common', 'Workspaces'),
			'data' => [
				'toggle' => 'tooltip',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('updateWorkspace'),
		'tag' => 'a',
		'url' => ['update', 'id' => $model->id],
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
		'visible' => Yii::$app->user->can('deleteWorkspace'),
		'tag' => 'a',
		'url' => ['delete', 'id' => $model->id],
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
	[
		'visible' => Yii::$app->user->can('updateWorkspace'),
		'tag' => 'a',
		'url' => ['reinstall', 'id' => $model->id],
		'icon' => 'fa fa-undo',
		'options' => [
			'class' => 'btn btn-sm btn-warning',
			'title' => Yii::t('common', 'Reinstall'),
			'data' => [
				'toggle' => 'tooltip',
				'confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
				'method' => 'POST',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('createWorkspace'),
		'tag' => 'a',
		'url' => ['create'],
		'icon' => 'fa fa-plus',
		'options' => [
			'class' => 'btn btn-sm btn-success',
			'title' => Yii::t('common', 'Create'),
			'data' => [
				'toggle' => 'tooltip',
			],
		],
	],
];
?>

<div class="table-responsive mb-10">
	<?= DetailView::widget([
		'options' => [
			'class' => 'table table-striped table-bordered detail-view detail-view-fixed',
		],
		'model' => $model,
		'attributes' => [
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Code'),
				'value' => function (Workspace $model) {
					return $model->code ? Html::tag('code', Html::encode($model->code)) : '&mdash;';
				},
			],
			[
				'format' => 'raw',
				'label' => Yii::t('label', 'URL'),
				'value' => function (Workspace $model) {
					if ($model->url) {
						return Html::a($model->getAbsoluteUrl(), $model->getAbsoluteUrl(), ['target' => '_blank']);
					}
					return '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Subscriber'),
				'value' => function (Workspace $model) {
					if ($model->subscription && ($subscriber = $model->subscription->subscriber)) {
						return Html::a($subscriber->user->fullName, ['/subscriber-manager/subscriber/view', 'id' => $subscriber->id]);
					}
					return '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Subscription'),
				'value' => function (Workspace $model) {
					if ($subscription = $model->subscription) {
						return Html::a($subscription->formattedName, ['/subscriber-manager/subscription/view', 'id' => $subscription->id]);
					}
					return '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Type'),
				'value' => function (Workspace $model) {
					$type = Workspace::getTypeLabels()[$model->type];
					return $type;
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Created By'),
				'value' => function (Workspace $model) {
					return $model->creator ? Html::a($model->creator->fullName, ['/user-manager/user/view', 'id' => $model->creator->id]) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Created At'),
				'value' => function (Workspace $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Updated By'),
				'value' => function (Workspace $model) {
					return $model->updater ? Html::a($model->updater->fullName, ['/user-manager/user/view', 'id' => $model->updater->id]) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Updated At'),
				'value' => function (Workspace $model) {
					return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Status'),
				'value' => function (Workspace $model) {
					$status = Workspace::getStatusLabels()[$model->status];
					return Html::tag('span', $status['label'], ['class' => 'label label-' . $status['color']]);
				},
			],
			[
				// The workspace's own subscriptions per status, read live from its
				// tenant database — the dashboard's pie, scoped to this one workspace
				// (INCOMPLETE checkout shells left out).
				'format' => 'raw',
				'label' => Yii::t('common', 'Subscriptions'),
				'value' => function (Workspace $model) {
					$subscriptionStatuses = $model->getSubscriptionStatusStats();
					if (array_sum(\yii\helpers\ArrayHelper::getColumn($subscriptionStatuses, 'data')) <= 0) {
						return '&mdash;';
					}

					return \tws\widgets\chart\Chart::widget([
						'type' => \tws\widgets\chart\Chart::TYPE_FLOT,
						'options' => [
							'style' => [
								'height' => '300px',
								'max-width' => '560px',
							],
						],
						'clientOptions' => [
							'type' => 'pie',
							'data' => $subscriptionStatuses,
							'options' => [
								'series' => [
									'pie' => [
										'show' => true,
										'radius' => 1,
										'label' => [
											'show' => true,
											'radius' => 3/4,
											'formatter' => new \yii\web\JsExpression('function (label, series) {
												return $("<div/>", {
													"class": "chart-label",
													"html": Math.round(series.percent) + "%"
												}).prop("outerHTML");
											}'),
										],
									],
								],
								'legend' => [
									'show' => true,
									'position' => 'nw',
									'labelFormatter' => new \yii\web\JsExpression('function (label, series) {
										return $("<div/>", {
											"html": label + " (" + series.data[0][1] + ")"
										}).prop("outerHTML");
									}'),
								],
								'grid' => [
									'hoverable' => true,
									'clickable' => true,
								],
							],
						],
					]);
				},
			],
		],
	]) ?>
</div>
