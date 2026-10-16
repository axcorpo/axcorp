<?php
/* @var $this yii\web\View */

use common\widgets\datatable\DataTable;
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
			<?= Html::a(Yii::t('frontend', 'Create company'), ['create'], [
				'class' => 'btn btn-primary btn-slide-right',
				'data' => [
					'popup-action' => '',
					'popup-done' => ['redrawDataTable' => '#dt-companies'],
				],
			]) ?>
		</div>

		<?= DataTable::widget([
			'id' => 'dt-companies',
			'options' => [
				'class' => 'table table-bordered table-hover',
			],
			'showColumnFilters' => false,
			'clientOptions' => [
				'deferRender' => true,
				'processing' => true,
				'serverSide' => true,
				'ajax' => [
					'url' => Url::to(['company/dt-companies']),
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
						'data' => 'created_at',
						'title' => Yii::t('label', 'Created At'),
						'className' => 'col-autowidth',
						'filter' => ['date', 'icu:' . Yii::$app->settings->get('dateFormat')],
					],
				],
			],
		]) ?>
	</div>
</div>
