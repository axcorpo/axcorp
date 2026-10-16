<?php

/* @var $this yii\web\View */

use common\models\Workspace;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Workspaces');
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Subscribers'),
		'url' => ['default/index'],
	],
	$this->title,
];

if ($showTrash = Yii::$app->settings->get('enableSoftDelete')) {
	$showTrash = Yii::$app->request->get('deleted') == Workspace::YES;
	$this->params['breadcrumbs'][] = [
		'template' => '<li class="page-links">{link}</li>',
		'label' => implode('', [
			Html::a(Yii::t('common', 'Current'), ['index'], ['class' => $showTrash ? '' : 'active']),
			Html::a(Yii::t('common', 'Trash'), ['index', 'deleted' => Workspace::YES], ['class' => $showTrash ? 'active' : '']),
		]),
	];
}

$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('restoreWorkspace') && $showTrash,
		'tag' => 'button',
		'icon' => 'fa fa-undo',
		'options' => [
			'class' => 'btn btn-sm btn-success hidden',
			'title' => Yii::t('common', 'Restore'),
			'data' => [
				'toggle' => 'tooltip',
				'dt-bulk-operation' => 'restore',
				'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
				'dt-url' => Url::to(['restore']),
				'dt-table' => '#dt-workspaces',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('deleteWorkspace'),
		'tag' => 'button',
		'icon' => 'fa fa-trash',
		'options' => [
			'class' => 'btn btn-sm btn-danger hidden',
			'title' => $showTrash ? Yii::t('common', 'Delete Permanently') : Yii::t('common', 'Delete'),
			'data' => [
				'toggle' => 'tooltip',
				'dt-bulk-operation' => $showTrash ? 'delete-permanently' : 'delete',
				'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
				'dt-url' => Url::to(['delete']),
				'dt-table' => '#dt-workspaces',
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
	[
		'visible' => Yii::$app->user->can('updateWorkspace'),
		'tag' => 'a',
		'url' => ['symlink-update'],
		'icon' => 'fa fa-link',
		'options' => [
			'class' => 'btn btn-sm btn-warning',
			'title' => Yii::t('common', 'Symbolic Links Update'),
			'data' => [
				'toggle' => 'tooltip',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('updateWorkspace'),
		'tag' => 'a',
		'url' => ['cache-flush'],
		'icon' => 'fa fa-eraser',
		'options' => [
			'class' => 'btn btn-sm btn-info',
			'title' => Yii::t('common', 'Flush Workspaces Cache'),
			'data' => [
				'toggle' => 'tooltip',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('updateWorkspace') && Yii::$app->user->identity->authAssignment->item_name == 'superAdmin',
		'tag' => 'a',
		'url' => ['database-update'],
		'icon' => 'fa fa-database',
		'options' => [
			'class' => 'btn btn-sm btn-primary',
			'title' => Yii::t('common', 'Database Update'),
			'data' => [
				'toggle' => 'tooltip',
			],
		],
	],
];
?>

<?= DataTable::widget([
	'id' => 'dt-workspaces',
	'options' => [
		'class' => 'table table-bordered table-hover',
	],
	'showColumnFilters' => true,
	'clientOptions' => [
		'deferRender' => true,
		'processing' => true,
		'serverSide' => true,
		'ajax' => [
			'url' => Url::to(['dt-workspaces']),
			'method' => 'POST',
			'data' => new JsExpression('function (data) {
				data.deleted = ' . json_encode($showTrash ? Workspace::YES : null) . ';
			}'),
		],
		'order' => [
			[4, 'asc'],
		],
		'pageLength' => Yii::$app->settings->get('itemsPerPage'),
		'lengthMenu' => [
			'autoCreate' => true,
			'displayAll' => Yii::t('common', 'All'),
		],
		'autoWidth' => false,
		'responsive' => true,
		'columns' => [
			[
				'class' => 'common\widgets\datatable\CheckboxColumn',
			],
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
				'data' => 'url',
				'title' => Yii::t('label', 'URL'),
				'filter' => ['text'],
			],
			[
				'data' => 'subscriber',
				'title' => Yii::t('label', 'Subscriber'),
				'filter' => ['text'],
			],
			[
				'data' => 'subscription',
				'title' => Yii::t('label', 'Subscription'),
				'filter' => ['text'],
			],
			[
				'data' => 'type',
				'title' => Yii::t('label', 'Type'),
				'className' => 'col-autowidth',
				'filter' => ['select', Workspace::getTypeLabels()],
			],
			[
				'data' => 'created_at',
				'title' => Yii::t('label', 'Created At'),
				'filter' => ['date', 'icu:' . Yii::$app->settings->get('dateFormat')],
			],
			[
				'data' => 'status',
				'title' => Yii::t('label', 'Status'),
				'className' => 'col-autowidth',
				'filter' => ['select', ArrayHelper::getColumn(Workspace::getStatusLabels(), 'label')],
			],
		],
	],
]) ?>
