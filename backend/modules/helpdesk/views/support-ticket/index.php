<?php
/* @var $this yii\web\View */

use common\models\SupportTicket;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Support Tickets');
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Helpdesk'),
		'url' => ['default/index'],
	],
	$this->title,
];

if ($showTrash = Yii::$app->settings->get('enableSoftDelete')) {
	$showTrash = Yii::$app->request->get('deleted') == SupportTicket::YES;
	$this->params['breadcrumbs'][] = [
		'template' => '<li class="page-links">{link}</li>',
		'label' => implode('', [
			Html::a(Yii::t('common', 'Current'), ['index'], ['class' => $showTrash ? '' : 'active']),
			Html::a(Yii::t('common', 'Trash'), ['index', 'deleted' => SupportTicket::YES], ['class' => $showTrash ? 'active' : '']),
		]),
	];
}

$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('restoreHelpdeskSupportTicket') && $showTrash,
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
				'dt-table' => '#dt-support-tickets',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('deleteHelpdeskSupportTicket'),
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
				'dt-table' => '#dt-support-tickets',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('createHelpdeskSupportTicket'),
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
	'id' => 'dt-support-tickets',
	'options' => [
		'class' => 'table table-bordered table-hover',
	],
	'showColumnFilters' => true,
	'clientOptions' => [
		'deferRender' => true,
		'processing' => true,
		'serverSide' => true,
		'ajax' => [
			'url' => Url::to(['dt-support-tickets']),
			'method' => 'POST',
			'data' => new JsExpression('function (data) {
				data.deleted = ' . json_encode($showTrash ? SupportTicket::YES : null) . ';
			}'),
		],
		'order' => [
			[2, 'desc'],
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
				'data' => 'action',
				'class' => 'common\widgets\datatable\ActionColumn',
				'title' => Yii::t('common', 'Action'),
			],
			[
				'data' => 'number',
				'title' => '#',
				'className' => 'col-autowidth',
				'filter' => ['text'],
			],
			[
				'data' => 'subject',
				'title' => Yii::t('label', 'Subject'),
				'filter' => ['text'],
			],
			[
				'data' => 'department',
				'title' => Yii::t('label', 'Department'),
				'filter' => ['text'],
			],
			[
				'data' => 'priority',
				'title' => Yii::t('label', 'Priority'),
				'filter' => ['text'],
			],
			[
				'data' => 'subscription',
				'title' => Yii::t('label', 'Subscription'),
				'filter' => ['text'],
			],
			[
				'data' => 'created_by',
				'title' => Yii::t('label', 'Created By'),
				'filter' => ['text'],
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
				'filter' => ['text'],
			],
		],
		'rowCallback' => new JsExpression('function (row, data, index) {
			if (data.seen == "' . Yii::$app->formatter->asBoolean(false) . '") {
				$(row).addClass("text-muted bold");
			}
		}'),
	],
]) ?>
