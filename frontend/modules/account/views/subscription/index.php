<?php
/* @var $this yii\web\View */

use common\models\Subscription;
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

		<div class="text-end mb-3">
			<?= Html::a(Yii::t('common', 'Buy Packages'), ['/account/payment/package'], ['class' => 'btn btn-primary btn-slide-right']) ?>
		</div>

		<?= DataTable::widget([
			'id' => 'dt-package-subscriptions',
			'options' => [
				'class' => 'table table-bordered table-hover',
			],
			'showColumnFilters' => false,
			'clientOptions' => [
				'deferRender' => true,
				'processing' => true,
				'serverSide' => true,
				'ajax' => [
					'url' => Url::to(['dt-package-subscriptions']),
					'method' => 'POST',
					'reloadInterval' => 5 * 60000,
				],
				'order' => [
					[4, 'asc'],
					[5, 'desc'],
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
