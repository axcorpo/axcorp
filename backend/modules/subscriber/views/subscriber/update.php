<?php

/* @var $this yii\web\View */
/* @var $model common\models\Subscriber */

use common\models\Package;
use common\models\Subscription;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Update {item}', ['item' => Yii::t('common', 'Subscriber')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Subscribers'),
		'url' => ['default/index'],
	],
	Yii::t('common', 'Update'),
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewSubscriber'),
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
		'visible' => Yii::$app->user->can('createSubscriber'),
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

<?= $this->render('_form', [
	'model' => $model,
]) ?>

<div class="panel blue-hoki">
	<div class="panel-title">
		<div class="panel-heading">
			<?= Yii::t('common', 'Subscriptions') ?>
			<?php if ($showTrash = Yii::$app->settings->get('enableSoftDelete')): ?>
				<?php $showTrash = Yii::$app->request->get('deleted') == Subscription::YES; ?>
				<span class="page-links">
					<?= implode('', [
						Html::a(Yii::t('common', 'Current'), ['update', 'id' => $model->id], ['class' => $showTrash ? '' : 'active']),
						Html::a(Yii::t('common', 'Trash'), ['update', 'id' => $model->id, 'deleted' => Subscription::YES], ['class' => $showTrash ? 'active' : '']),
					]); ?>
				</span>
			<?php endif; ?>
		</div>
		<div class="panel-actions">
			<?php if (Yii::$app->user->can('restoreSubscriber') && $showTrash): ?>
				<?= Html::button('<span class="fa fa-undo"></span>', [
					'type' => 'button',
					'class' => 'btn btn-sm btn-success hidden',
					'title' => Yii::t('common', 'Restore'),
					'data' => [
						'toggle' => 'tooltip',
						'dt-bulk-operation' => 'restore',
						'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
						'dt-url' => Url::to(['subscription/restore', 'subscriber_id' => $model->id]),
						'dt-table' => '#dt-subscriptions',
					],
				]) ?>
			<?php endif; ?>
			<?php if (Yii::$app->user->can('deleteSubscriber')): ?>
				<?= Html::button('<span class="fa fa-trash"></span>', [
					'type' => 'button',
					'class' => 'btn btn-sm btn-danger hidden',
					'title' => $showTrash ? Yii::t('common', 'Delete Permanently') : Yii::t('common', 'Delete'),
					'data' => [
						'toggle' => 'tooltip',
						'dt-bulk-operation' => $showTrash ? 'delete-permanently' : 'delete',
						'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
						'dt-url' => Url::to(['subscription/delete', 'subscriber_id' => $model->id]),
						'dt-table' => '#dt-subscriptions',
					],
				]) ?>
			<?php endif; ?>
			<?php if (Yii::$app->user->can('createSubscriber')): ?>
				<?= Html::a('<span class="fa fa-plus"></span>', ['subscription/create', 'subscriber_id' => $model->id], [
					'type' => 'button',
					'class' => 'btn btn-sm btn-success',
					'title' => Yii::t('common', 'Create'),
					'data' => [
						'toggle' => 'tooltip',
						'popup-action' => '',
						'popup-done' => ['redrawDataTable' => '#dt-subscriptions'],
					],
				]) ?>
			<?php endif; ?>
		</div>
	</div>
	<div class="panel-body">
		<?= DataTable::widget([
			'id' => 'dt-subscriptions',
			'options' => [
				'class' => 'table table-bordered table-hover',
			],
			'showColumnFilters' => true,
			'clientOptions' => [
				'deferRender' => true,
				'processing' => true,
				'serverSide' => true,
				'ajax' => [
					'url' => Url::to(['subscription/dt-subscriptions']),
					'method' => 'POST',
					'data' => new JsExpression('function (data) {
					 	data.subscriber_id = ' . json_encode($model->uuid) . ';
						data.deleted = ' . json_encode($showTrash ? Subscription::YES : null) . ';
					}'),
					'reloadInterval' => 1 * 60000,
				],
				'order' => [
					[5, 'asc'],
					[4, 'desc'],
				],
				'pageLength' => Yii::$app->settings->get('itemsPerPage'),
				'lengthMenu' => [
					'autoCreate' => true,
					'displayAll' => Yii::t('common', 'All'),
				],
				'autoWidth' => false,
				'responsive' => true,
				'rowGroup' => [
					'dataSrc' => 'group.label',
				],
				'columns' => [
					[
						'class' => 'common\widgets\datatable\ActionColumn',
						'data' => 'action',
						'title' => Yii::t('common', 'Action'),
					],
					[
						'data' => 'code',
						'title' => Yii::t('label', 'Code'),
						'filter' => ['text'],
						'className' => 'col-autowidth',
					],
					[
						'data' => 'package',
						'title' => Yii::t('label', 'Package'),
						'filter' => ['text'],
					],
					[
						'data' => 'price',
						'title' => Yii::t('label', 'Price'),
						'filter' => ['text'],
					],
					[
						'data' => 'billing_cycle',
						'title' => Yii::t('label', 'Billing Cycle'),
						'filter' => ['select', Package::getCycleLabels()],
					],
					[
						'data' => 'end_at',
						'title' => Yii::t('label', 'Next Due At'),
						'filter' => ['date', 'icu:' . Yii::$app->settings->get('dateFormat')],
					],
					[
						'data' => 'status',
						'title' => Yii::t('label', 'Status'),
						'className' => 'col-autowidth',
						'filter' => ['select', ArrayHelper::getColumn(Subscription::getStatusLabels(), 'label')],
					],
				],
			],
		]) ?>
	</div>
</div>
