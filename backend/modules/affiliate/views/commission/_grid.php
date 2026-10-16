<?php

/* @var $this yii\web\View */
/* @var $id string DataTable id */
/* @var $params array fixed request params (e.g. ['affiliate_id' => uuid]) */
/* @var $selectable bool whether rows get a checkbox for the bulk operations */

use backend\modules\affiliate\models\CommissionSearch;
use common\models\AffiliateCommission;
use common\widgets\datatable\DataTable;
use tws\helpers\Url;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\JsExpression;

$params = isset($params) ? $params : [];
// The dashboard's column order (subscriber/invoice/index.php): identifier, main fields,
// relations, dates, status last. No action column: a commission has no page of its own.
$columns = [];
if (!empty($selectable)) {
	$columns[] = [
		'class' => 'common\widgets\datatable\CheckboxColumn',
	];
}
$columns = array_merge($columns, [
	[
		'data' => 'invoice',
		'title' => Yii::t('label', 'Invoice'),
		'className' => 'col-autowidth',
		'filter' => ['text'],
	],
	[
		'data' => 'base_amount',
		'title' => Yii::t('label', 'Base Amount'),
		'className' => 'text-right',
		'filter' => ['text'],
	],
	[
		'data' => 'percentage',
		'title' => Yii::t('label', 'Percentage'),
		'className' => 'col-autowidth text-right',
		'filter' => ['text'],
	],
	[
		'data' => 'amount',
		'title' => Yii::t('label', 'Commission'),
		'className' => 'text-right',
		'filter' => ['text'],
	],
	[
		'data' => 'currency',
		'title' => Yii::t('label', 'Currency'),
		'className' => 'col-autowidth',
		'filter' => ['text'],
	],
]);
if (!isset($params['affiliate_id'])) {
	$columns[] = [
		'data' => 'affiliate',
		'title' => Yii::t('label', 'Affiliate'),
		'filter' => ['select', CommissionSearch::affiliateFilterOptions()],
	];
}
$columns = array_merge($columns, [
	[
		'data' => 'subscriber',
		'title' => Yii::t('label', 'Subscriber'),
		'filter' => ['text'],
	],
	[
		'data' => 'payout',
		'title' => Yii::t('label', 'Payout'),
		'orderable' => false,
		'filter' => false,
	],
	[
		'data' => 'earned_at',
		'title' => Yii::t('label', 'Earned At'),
		'filter' => ['date', 'icu:' . Yii::$app->settings->get('dateFormat')],
	],
	[
		'data' => 'payable_at',
		'title' => Yii::t('label', 'Payable At'),
		'filter' => ['date', 'icu:' . Yii::$app->settings->get('dateFormat')],
	],
	[
		'data' => 'status',
		'title' => Yii::t('label', 'Status'),
		'className' => 'col-autowidth',
		'filter' => ['select', ArrayHelper::getColumn(AffiliateCommission::getStatusLabels(), 'label')],
	],
]);
// Newest earned first, wherever the optional columns put the date.
// (Not array_column(): it skips the checkbox column, which has no data key.)
$orderColumn = 0;
foreach ($columns as $index => $column) {
	if (($column['data'] ?? null) === 'earned_at') {
		$orderColumn = $index;
	}
}
?>

<div class="dt-scroll-x">
<?= DataTable::widget([
	'id' => $id,
	'options' => [
		'class' => 'table table-bordered table-hover',
	],
	'showColumnFilters' => true,
	'clientOptions' => [
		'deferRender' => true,
		'processing' => true,
		'serverSide' => true,
		'ajax' => [
			'url' => Url::to(['/affiliate-manager/commission/dt-commissions']),
			'method' => 'POST',
			'data' => new JsExpression('function (data) {
				$.extend(data, ' . Json::htmlEncode((object) $params) . ');
			}'),
		],
		'order' => [
			[$orderColumn, 'desc'],
		],
		'pageLength' => Yii::$app->settings->get('itemsPerPage'),
		'lengthMenu' => [
			'autoCreate' => true,
			'displayAll' => Yii::t('common', 'All'),
		],
		'autoWidth' => true,
		'responsive' => false,
		'scrollX' => false,
		'dom' => 'lfrtip',
		'columns' => $columns,
	],
]) ?>
</div>

<?= $this->render('/_double-scroll', ['id' => $id]) ?>
