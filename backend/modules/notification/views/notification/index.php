<?php
/* @var $this yii\web\View */

use common\models\Notification;
use common\widgets\datatable\DataTable;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Notifications');
$this->params['breadcrumbs'][] = $this->title;

if ($showTrash = Yii::$app->settings->get('enableSoftDelete')) {
	$showTrash = Yii::$app->request->get('deleted') == Notification::YES;
	$this->params['breadcrumbs'][] = [
		'template' => '<li class="page-links">{link}</li>',
		'label' => implode('', [
			Html::a(Yii::t('common', 'Current'), ['index'], ['class' => $showTrash ? '' : 'active']),
			Html::a(Yii::t('common', 'Trash'), ['index', 'deleted' => Notification::YES], ['class' => $showTrash ? 'active' : '']),
		]),
	];
}

$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('restoreNotification') && $showTrash,
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
				'dt-table' => '#dt-notifications',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('deleteNotification'),
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
				'dt-table' => '#dt-notifications',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('viewNotification'),
		'tag' => 'button',
		'icon' => 'fa fa-eye',
		'options' => [
			'class' => 'btn btn-sm btn-info hidden',
			'title' => Yii::t('common', 'Mark as Seen'),
			'data' => [
				'toggle' => 'tooltip',
				'dt-bulk-operation' => 'mark-as-seen',
				'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
				'dt-url' => Url::to(['mark-as-seen']),
				'dt-table' => '#dt-notifications',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('createNotification'),
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
	'id' => 'dt-notifications',
	'options' => [
		'class' => 'table table-bordered table-hover',
	],
	'showColumnFilters' => true,
	'clientOptions' => [
		'deferRender' => true,
		'processing' => true,
		'serverSide' => true,
		'ajax' => [
			'url' => Url::to(['dt-notifications']),
			'method' => 'POST',
			'data' => new JsExpression('function (data) {
				data.deleted = ' . json_encode($showTrash ? Notification::YES : null) . ';
			}'),
			'reloadInterval' => 25 * 1000,
		],
		'order' => [
			[6, 'desc'],
			[7, 'asc'],
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
				'visible' => Yii::$app->user->can('deleteNotification'),
				'class' => 'common\widgets\datatable\CheckboxColumn',
			],
			[
				'class' => 'common\widgets\datatable\ActionColumn',
				'data' => 'action',
				'title' => Yii::t('common', 'Action'),
			],
			[
				'data' => 'type',
				'title' => Yii::t('label', 'Type'),
				'filter' => ['select', Notification::getTypeLabels()],
			],
			[
				'data' => 'title',
				'title' => Yii::t('label', 'Title'),
				'filter' => ['text'],
			],
			[
				'data' => 'message',
				'title' => Yii::t('label', 'Message'),
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
				'data' => 'seen',
				'title' => Yii::t('label', 'Seen'),
				'className' => 'col-autowidth',
				'filter' => ['select', [Yii::t('yii', 'No'), Yii::t('yii', 'Yes')]],
			],
		],
		'rowCallback' => new JsExpression('function (row, data, index) {
			if (data.seen == "' . Yii::$app->formatter->asBoolean(false) . '") {
				$(row).addClass("text-muted bold");
			}
		}'),
	],
]) ?>
