<?php

/* @var $this yii\web\View */

use common\models\Package;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Packages');
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Nomenclature'),
		'url' => ['default/index'],
	],
	$this->title,
];

if ($showTrash = Yii::$app->settings->get('enableSoftDelete')) {
	$showTrash = Yii::$app->request->get('deleted') == Package::YES;
	$this->params['breadcrumbs'][] = [
		'template' => '<li class="page-links">{link}</li>',
		'label' => implode('', [
			Html::a(Yii::t('common', 'Current'), ['index'], ['class' => $showTrash ? '' : 'active']),
			Html::a(Yii::t('common', 'Trash'), ['index', 'deleted' => Package::YES], ['class' => $showTrash ? 'active' : '']),
		]),
	];
}

$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('restorePackage') && $showTrash,
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
				'dt-table' => '#dt-packages',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('deletePackage'),
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
				'dt-table' => '#dt-packages',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('createPackage'),
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
	'id' => 'dt-packages',
	'options' => [
		'class' => 'table table-bordered table-hover',
	],
	'showColumnFilters' => true,
	'clientOptions' => [
		'deferRender' => true,
		'processing' => true,
		'serverSide' => true,
		'ajax' => [
			'url' => Url::to(['dt-packages']),
			'method' => 'POST',
			'data' => new JsExpression('function (data) {
				data.deleted = ' . json_encode($showTrash ? Package::YES : null) . ';
			}'),
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
		'rowGroup' => [
			'dataSrc' => 'type',
		],
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
				'data' => 'sort_order',
				'title' => '#',
				'className' => 'col-autowidth',
				'filter' => ['text'],
			],
			[
				'data' => 'name',
				'title' => Yii::t('label', 'Name'),
				'filter' => ['text'],
			],
			[
				'data' => 'default',
				'title' => Yii::t('label', 'Default'),
				'filter' => ['select', Package::getBooleanLabels()],
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
				'data' => 'trial_period',
				'title' => Yii::t('label', 'Trial Period'),
				'filter' => ['select', Package::getCycleLabels()],
			],
			[
				'data' => 'status',
				'title' => Yii::t('label', 'Status'),
				'className' => 'col-autowidth',
				'filter' => ['select', ArrayHelper::getColumn(Package::getStatusLabels(), 'label')],
			],
		],
		'rowCallback' => new JsExpression('function (row, data, index) {
			if (data.defaultValue == "' . Package::YES . '") {
				$(row).addClass("success");
			}
		}'),
	],
]) ?>
