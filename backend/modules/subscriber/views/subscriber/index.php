<?php

/* @var $this yii\web\View */

use backend\modules\export\widgets\export\Export;
use backend\modules\subscriber\models\SubscriberSearch;
use common\models\Subscriber;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Subscribers');
$this->params['breadcrumbs'] = [
	$this->title,
];

if ($showTrash = Yii::$app->settings->get('enableSoftDelete')) {
	$showTrash = Yii::$app->request->get('deleted') == Subscriber::YES;
	$this->params['breadcrumbs'][] = [
		'template' => '<li class="page-links">{link}</li>',
		'label' => implode('', [
			Html::a(Yii::t('common', 'Current'), ['index'], ['class' => $showTrash ? '' : 'active']),
			Html::a(Yii::t('common', 'Trash'), ['index', 'deleted' => Subscriber::YES], ['class' => $showTrash ? 'active' : '']),
		]),
	];
}

$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('restoreSubscriber') && $showTrash,
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
				'dt-table' => '#dt-subscribers',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('deleteSubscriber'),
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
				'dt-table' => '#dt-subscribers',
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
	[
		'visible' => true,
		'content' => Export::widget([
			'buttonOptions' => [
				'class' => 'btn btn-sm btn-default',
				'style' => 'margin: 0 5px;',
				'title' => Yii::t('common', 'Export'),
				'label' => '<span class="fa fa-sign-out"></span>',
			],
			'dropdownOptions' => [
				'class' => 'pull-right',
			],
			'items' => [
				Export::FORMAT_CSV => [
					'visible' => false,
					'label' => 'CSV',
					'url' => '#',
					'clientOptions' => [
						'fieldDelimiter' => ',',
						'fieldEnclosure' => '"',
						'shouldAddBom' => true,
					],
				],
				Export::FORMAT_VCF => [
					'visible' => true,
					'label' => 'VCF',
					'url' => '#',
					'clientOptions' => [
						'map' => [
							'name' => 'name',
							'phone' => 'phone',
							'email' => 'email',
						],
					],
				],
				Export::FORMAT_XLSX => [
					'visible' => false,
					'label' => 'XLSX',
					'url' => '#',
					'clientOptions' => [
						'shouldCreateNewSheetsAutomatically' => true,
						'shouldUseInlineStrings' => true,
					],
				],
				Export::FORMAT_PDF => [
					'visible' => false,
					'label' => 'PDF',
					'url' => '#',
					'clientOptions' => [
						'allowHtml' => true,
					],
				],
			],
			'clientOptions' => [
				'url' => Url::to(['/export-manager/export']),
				'dataTable' => '#dt-subscribers',
				'model' => Yii::$app->security->maskToken(SubscriberSearch::class),
				'title' => Yii::t('common', 'Subscribers'),
			],
		]),
	],
];
?>

<?= DataTable::widget([
	'id' => 'dt-subscribers',
	'options' => [
		'class' => 'table table-bordered table-hover',
	],
	'showColumnFilters' => true,
	'clientOptions' => [
		'deferRender' => true,
		'processing' => true,
		'serverSide' => true,
		'ajax' => [
			'url' => Url::to(['dt-subscribers']),
			'method' => 'POST',
			'data' => new JsExpression('function (data) {
				data.deleted = ' . json_encode($showTrash ? Subscriber::YES : null) . ';
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
				'data' => 'notes',
				'title' => Yii::t('label', 'Notes'),
				'filter' => ['text'],
			],
			[
				'data' => 'code',
				'title' => Yii::t('label', 'Code'),
				'filter' => ['text'],
				'className' => 'col-autowidth',
			],
			[
				'data' => 'image',
				'title' => Yii::t('label', 'Image'),
				'className' => 'figure-column col-autowidth text-center',
				'orderable' => false,
				'filter' => false,
			],
			[
				'data' => 'name',
				'title' => Yii::t('label', 'Name'),
				'filter' => ['text'],
			],
			[
				'data' => 'email',
				'title' => Yii::t('label', 'Email'),
				'filter' => ['text'],
			],
			[
				'data' => 'phone',
				'title' => Yii::t('label', 'Phone'),
				'filter' => ['text'],
			],
			[
				'data' => 'subscriptions',
				'title' => Yii::t('label', 'Subscriptions'),
				'filter' => ['text'],
			],
			[
				'data' => 'created_at',
				'title' => Yii::t('label', 'Created At'),
				'filter' => ['date', 'icu:' . Yii::$app->settings->get('dateFormat')],
			],
			[
				'data' => 'last_activity',
				'title' => Yii::t('label', 'Last Activity'),
				'filter' => ['date', 'icu:' . Yii::$app->settings->get('dateFormat')],
			],
			[
				'data' => 'status',
				'title' => Yii::t('label', 'Status'),
				'className' => 'col-autowidth',
				'filter' => ['select', ArrayHelper::getColumn(Subscriber::getStatusLabels(), 'label')],
			],
		],
	],
]) ?>
