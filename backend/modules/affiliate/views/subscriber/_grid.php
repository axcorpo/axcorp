<?php

/* @var $this yii\web\View */
/* @var $id string DataTable id */
/* @var $params array fixed request params (e.g. ['affiliate_id' => uuid]) */

use backend\modules\affiliate\models\SubscriberSearch;
use common\models\Subscription;
use common\widgets\datatable\DataTable;
use tws\helpers\Url;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\JsExpression;

$params = isset($params) ? $params : [];
// The dashboard's column order (subscriber/index.php): main fields, relations, dates. The
// rows are read-only here (managed in the subscriber module): no action column and no
// status of their own — the subscriptions column filters by a subscription status instead.
$columns = [
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
];
if (!isset($params['affiliate_id'])) {
	$columns[] = [
		'data' => 'affiliate',
		'title' => Yii::t('label', 'Affiliate'),
		'filter' => ['select', SubscriberSearch::affiliateFilterOptions()],
	];
}
$columns = array_merge($columns, [
	[
		'data' => 'subscriptions',
		'title' => Yii::t('label', 'Subscriptions'),
		'orderable' => false,
		'filter' => ['select', ArrayHelper::getColumn(Subscription::getStatusLabels(), 'label')],
	],
	[
		'data' => 'created_at',
		'title' => Yii::t('label', 'Signed Up At'),
		'filter' => ['date', 'icu:' . Yii::$app->settings->get('dateFormat')],
	],
]);
// Newest signups first, wherever the optional affiliate column puts the date.
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
			'url' => Url::to(['/affiliate-manager/subscriber/dt-subscribers']),
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
