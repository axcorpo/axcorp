<?php

/* @var $this yii\web\View */

use common\models\EventLog;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Event Logs');
$this->params['breadcrumbs'][] = $this->title;
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('deleteEventLog'),
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
				'dt-table' => '#dt-event-logs',
			],
		],
	],
];
?>

<?= DataTable::widget([
	'id' => 'dt-event-logs',
	'options' => [
		'class' => 'table table-bordered table-hover',
	],
	'showColumnFilters' => true,
	'clientOptions' => [
		'deferRender' => true,
		'processing' => true,
		'serverSide' => true,
		'ajax' => [
			'url' => Url::to(['dt-event-logs']),
			'method' => 'POST',
			'reloadInterval' => 5 * 60000,
		],
		'order' => [
			[5, 'desc'],
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
				'class' => 'common\widgets\datatable\ActionColumn',
				'title' => Yii::t('common', 'Action'),
				'buttons' => [
					'view' => [
						'visible' => Yii::$app->user->can('viewEventLog'),
						'url' => ['view', 'id' => new JsExpression('id')],
						'content' => '<span class="fa fa-eye"></span>',
						'options' => [
							'class' => 'action-view btn btn-xs btn-info',
							'title' => Yii::t('common', 'View'),
							'data' => [
								'toggle' => 'tooltip',
								'popup-action' => '',
							],
						],
					],
					'delete' => Yii::$app->user->can('deleteEventLog'),
				],
			],
			[
				'data' => 'user',
				'title' => Yii::t('label', 'User'),
				'filter' => ['text'],
			],
			[
				'data' => 'operation',
				'title' => Yii::t('label', 'Operation'),
				'filter' => ['select', ArrayHelper::getColumn(EventLog::getActions(), 'label')],
			],
			[
				'data' => 'resource',
				'title' => Yii::t('label', 'Resource'),
				'filter' => ['select', ArrayHelper::map(EventLog::findDistinctResources(), 'resource', 'translatedResource')],
			],
			[
				'data' => 'model_key',
				'title' => Yii::t('label', 'Resource ID'),
				'filter' => ['text'],
			],
			[
				'data' => 'date',
				'title' => Yii::t('label', 'Date'),
				'filter' => ['date', 'icu:' . Yii::$app->settings->get('dateFormat')],
			],
			[
				'data' => 'ip_address',
				'title' => Yii::t('label', 'IP Address'),
				'filter' => ['text'],
			],
		],
	],
]) ?>
