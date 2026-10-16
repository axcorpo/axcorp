<?php

/* @var $this yii\web\View */

use backend\modules\affiliate\models\AffiliateSearch;
use common\models\Affiliate;
use common\widgets\datatable\DataTable;
use tws\helpers\Url;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Affiliates');
$this->params['breadcrumbs'] = [
	$this->title,
];

// Three lists: the affiliates, the applications from the public page (pending and
// rejected), and the trash.
$showTrash = Yii::$app->user->can('deleteAffiliate') && Yii::$app->request->get('deleted') == Affiliate::YES;
$showApplications = !$showTrash && Yii::$app->request->get('applications') == Affiliate::YES;
$this->params['breadcrumbs'][] = [
	'template' => '<li class="page-links">{link}</li>',
	'label' => implode('', array_filter([
		Html::a(Yii::t('common', 'Current'), ['index'], ['class' => $showTrash || $showApplications ? '' : 'active']),
		Html::a(Yii::t('common', 'Applications'), ['index', 'applications' => Affiliate::YES], ['class' => $showApplications ? 'active' : '']),
		Yii::$app->user->can('deleteAffiliate')
			? Html::a(Yii::t('common', 'Trash'), ['index', 'deleted' => Affiliate::YES], ['class' => $showTrash ? 'active' : ''])
			: null,
	])),
];

$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('deleteAffiliate') && $showTrash,
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
				'dt-table' => '#dt-affiliates',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('deleteAffiliate') && !$showTrash,
		'tag' => 'button',
		'icon' => 'fa fa-trash',
		'options' => [
			'class' => 'btn btn-sm btn-danger hidden',
			'title' => Yii::t('common', 'Delete'),
			'data' => [
				'toggle' => 'tooltip',
				'dt-bulk-operation' => 'delete',
				'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
				'dt-url' => Url::to(['delete']),
				'dt-table' => '#dt-affiliates',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('createAffiliate'),
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
	[
		'visible' => true,
		'content' => $this->render('/_export', [
			'dataTable' => '#dt-affiliates',
			'model' => AffiliateSearch::class,
			'title' => $this->title,
		]),
	],
];
?>

<?php // Columns in the dashboard's order (subscriber/index.php): action, identifier, main fields, dates, status last. ?>
<div class="dt-scroll-x">
<?= DataTable::widget([
	'id' => 'dt-affiliates',
	'options' => [
		'class' => 'table table-bordered table-hover',
	],
	'showColumnFilters' => true,
	'clientOptions' => [
		'deferRender' => true,
		'processing' => true,
		'serverSide' => true,
		'ajax' => [
			'url' => Url::to(['dt-affiliates']),
			'method' => 'POST',
			'data' => new JsExpression('function (data) {
				data.deleted = ' . json_encode($showTrash ? Affiliate::YES : null) . ';
				data.applications = ' . json_encode($showApplications ? Affiliate::YES : null) . ';
			}'),
		],
		'order' => [
			[11, 'desc'],
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
		'columns' => [
			[
				'class' => 'common\widgets\datatable\CheckboxColumn',
			],
			[
				'class' => 'common\widgets\datatable\ActionColumn',
				'data' => 'action',
				'title' => Yii::t('common', 'Action'),
			],
			[
				'data' => 'code',
				'title' => Yii::t('label', 'Affiliate Code'),
				'className' => 'col-autowidth',
				'filter' => ['text'],
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
				'data' => 'commission_percentage',
				'title' => Yii::t('label', 'Commission Percentage'),
				'className' => 'col-autowidth',
				'filter' => ['text'],
			],
			[
				'data' => 'commission_months',
				'title' => Yii::t('label', 'Commission Months'),
				'className' => 'col-autowidth',
				'filter' => ['text'],
			],
			[
				'data' => 'active_subscribers',
				'title' => Yii::t('label', 'Active Subscribers'),
				'className' => 'col-autowidth',
				'orderable' => false,
				'filter' => false,
			],
			[
				'data' => 'pending',
				'title' => Yii::t('label', 'Pending'),
				'orderable' => false,
				'filter' => false,
			],
			[
				'data' => 'payable',
				'title' => Yii::t('label', 'Payable'),
				'orderable' => false,
				'filter' => false,
			],
			[
				'data' => 'paid',
				'title' => Yii::t('label', 'Paid'),
				'orderable' => false,
				'filter' => false,
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
				'filter' => ['select', ArrayHelper::getColumn(Affiliate::getStatusLabels(), 'label')],
			],
		],
	],
]) ?>
</div>

<?= $this->render('/_double-scroll', ['id' => 'dt-affiliates']) ?>
