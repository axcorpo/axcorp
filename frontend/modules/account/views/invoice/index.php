<?php
/* @var $this yii\web\View */

use common\models\Invoice;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->params['breadcrumbs'][] = Html::encode($this->title);
?>

<div class="section section-md pt-0">
	<div class="container-fluid">
		<?php if ($content = $this->context->currentPage->content): ?>
			<header class="section-header">
				<?= $content ?>
			</header>
		<?php endif; ?>

		<?= DataTable::widget([
			'id' => 'dt-invoices',
			'options' => [
				'class' => 'table table-bordered table-hover',
			],
			'showColumnFilters' => false,
			'clientOptions' => [
				'deferRender' => true,
				'processing' => true,
				'serverSide' => true,
				'ajax' => [
					'url' => Url::to(['dt-invoices']),
					'method' => 'POST',
					'reloadInterval' => 5 * 60000,
				],
				'order' => [
					[5, 'asc'],
					[3, 'desc'],
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
					],
					[
						'data' => 'document_number',
						'title' => '#',
						'className' => 'col-autowidth',
						'filter' => ['text'],
					],
					[
						'data' => 'amount',
						'title' => Yii::t('label', 'Amount'),
						'filter' => ['text'],
					],
					[
						'data' => 'issued_at',
						'title' => Yii::t('label', 'Issued At'),
						'filter' => ['date', 'icu:' . Yii::$app->settings->get('dateFormat')],
					],
					[
						'data' => 'paid_at',
						'title' => Yii::t('label', 'Paid At'),
						'filter' => ['date', 'icu:' . Yii::$app->settings->get('dateFormat')],
					],
					[
						'data' => 'status',
						'title' => Yii::t('label', 'Status'),
						'className' => 'col-autowidth',
						'filter' => ['select', ArrayHelper::getColumn(Invoice::getStatusLabels(), 'label')],
					],
				],
			],
		]) ?>
	</div>
</div>
