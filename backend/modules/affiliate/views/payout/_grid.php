<?php

/* @var $this yii\web\View */
/* @var $id string DataTable id */
/* @var $params array fixed request params (e.g. ['affiliate_id' => uuid]) */

use backend\modules\affiliate\models\PayoutSearch;
use common\models\AffiliatePayout;
use common\widgets\datatable\DataTable;
use tws\helpers\Url;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\JsExpression;

$params = isset($params) ? $params : [];
// The dashboard's column order (subscriber/invoice/index.php): action, main fields,
// relations, dates, status last.
$columns = [
	[
		'class' => 'common\widgets\datatable\ActionColumn',
		'data' => 'action',
		'title' => Yii::t('common', 'Action'),
	],
	[
		'data' => 'amount',
		'title' => Yii::t('label', 'Amount'),
		'className' => 'text-right',
		'filter' => ['text'],
	],
	[
		'data' => 'currency',
		'title' => Yii::t('label', 'Currency'),
		'className' => 'col-autowidth',
		'filter' => ['text'],
	],
];
if (!isset($params['affiliate_id'])) {
	$columns[] = [
		'data' => 'affiliate',
		'title' => Yii::t('label', 'Affiliate'),
		'filter' => ['select', PayoutSearch::affiliateFilterOptions()],
	];
}
$columns = array_merge($columns, [
	[
		'data' => 'invoice',
		'title' => Yii::t('label', 'Invoice'),
		'filter' => ['text'],
	],
	[
		'data' => 'created_at',
		'title' => Yii::t('label', 'Created At'),
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
		'filter' => ['select', ArrayHelper::getColumn(AffiliatePayout::getStatusLabels(), 'label')],
	],
]);
// Newest first, wherever the optional affiliate column puts the date.
$orderColumn = 0;
foreach ($columns as $index => $column) {
	if (($column['data'] ?? null) === 'created_at') {
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
			'url' => Url::to(['/affiliate-manager/payout/dt-payouts']),
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
