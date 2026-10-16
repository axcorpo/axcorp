<?php
/* @var $this yii\web\View */

use common\widgets\datatable\DataTable;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->params['breadcrumbs'][] = Html::encode($this->title);
?>

<div class="text-end mb-3">
	<?= Html::a(Yii::t('common', 'Open a Ticket'), ['support-ticket/create'], [
		'class' => 'btn btn-primary btn-slide-right',
		'data' => [
			'popup-action' => '',
			'popup-done' => ['redrawDataTable' => '#dt-support-tickets'],
		],
	]) ?>
</div>

<?= DataTable::widget([
	'id' => 'dt-support-tickets',
	'options' => [
		'class' => 'table table-bordered table-hover',
	],
	'showColumnFilters' => false,
	'clientOptions' => [
		'deferRender' => true,
		'processing' => true,
		'serverSide' => true,
		'ajax' => [
			'url' => Url::to(['support-ticket/dt-support-tickets']),
			'method' => 'POST',
		],
		'order' => [
			[1, 'desc'],
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
				'className' => 'action-column col-autowidth text-center',
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
				'data' => 'created_at',
				'title' => Yii::t('label', 'Created At'),
				'className' => 'col-autowidth',
				'filter' => ['date', 'icu:' . Yii::$app->settings->get('dateFormat')],
			],
			[
				'data' => 'updated_at',
				'title' => Yii::t('label', 'Updated At'),
				'className' => 'col-autowidth',
				'filter' => ['date', 'icu:' . Yii::$app->settings->get('dateFormat')],
			],
			[
				'data' => 'status',
				'title' => Yii::t('label', 'Status'),
				'className' => 'col-autowidth',
				'filter' => ['text'],
			],
		],
	],
]) ?>
