<?php

/* @var $this yii\web\View */

use common\models\Package;
use common\models\Subscription;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Subscriptions');
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Subscribers'),
		'url' => ['default/index'],
	],
	[
		'label' => Yii::t('common', 'Subscriber'),
		'url' => ['subscriber/update', 'id' => $model->subscriber_id],
	],
	[
		'label' => Yii::t('common', 'Subscriptions'),
		'url' => ['subscriber/update', 'id' => $model->subscriber_id],
	],
	$this->title,
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('deleteSubscriber'),
		'tag' => 'button',
		'icon' => 'fa fa-trash',
		'options' => [
			'class' => 'btn btn-sm btn-danger hidden',
			'title' => Yii::t('common', 'Delete'),
			'data' => [
				'toggle' => 'tooltip',
				'dt-bulk-operation' => 'delete',
				'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
				'dt-url' => Url::to(['bulk-delete']),
				'dt-table' => '#dt-subscriptions',
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
				data.subscriber_id = "' . Yii::$app->request->get('subscriber_id') . '"; 
			}'),
			'reloadInterval' => 1 * 60000,
		],
		'order' => [
			[8, 'asc'],
			[7, 'desc'],
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
				'data' => 'users',
				'title' => Yii::t('label', 'Users'),
				'filter' => ['text'],
			],
			[
				'data' => 'quantity',
				'title' => Yii::t('label', 'Quantity'),
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

