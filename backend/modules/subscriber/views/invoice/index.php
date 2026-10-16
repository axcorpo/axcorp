<?php
/* @var $this yii\web\View */

use common\models\Invoice;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Invoices');
$this->params['breadcrumbs'] = [
	$this->title,
];

if ($showTrash = Yii::$app->settings->get('enableSoftDelete')) {
	$showTrash = Yii::$app->request->get('deleted') == Invoice::YES;
	$this->params['breadcrumbs'][] = [
		'template' => '<li class="page-links">{link}</li>',
		'label' => implode('', [
			Html::a(Yii::t('common', 'Current'), ['index'], ['class' => $showTrash ? '' : 'active']),
			Html::a(Yii::t('common', 'Trash'), ['index', 'deleted' => Invoice::YES], ['class' => $showTrash ? 'active' : '']),
		]),
	];
}

$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('restoreInvoice') && $showTrash,
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
				'dt-table' => '#dt-invoices',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('deleteInvoice'),
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
				'dt-table' => '#dt-invoices',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('updateInvoice'),
		'url' => empty($integration) ? '/spv' : ['/setting-manager/integration-manager/integration/index'],
		'content' => Html::a(
			'<small><i class="fa fa-internet-explorer"></i></small> - ' . Yii::t('common', 'Invoice'),
			(empty($integration) ? '/spv' : ['/setting-manager/integration-manager/integration/index']),
			[
				'class' => 'btn btn-sm btn-' . (empty($integration) ? 'danger' : 'success'),
				'title' => (empty($integration) ? Yii::t('common', 'Enable') : Yii::t('common', 'Configure')),
				'target' => (empty($integration) ? '_blank' : '_self'),
				'data' => [
					'toggle' => 'tooltip',
				],
			]
		),
	],
];

?>

<div class="dt-scroll-x">
<?= DataTable::widget([
	'id' => 'dt-invoices',
	'options' => [
		'class' => 'table table-bordered table-hover',
	],
	'showColumnFilters' => true,
	'clientOptions' => [
		'deferRender' => true,
		'processing' => true,
		'serverSide' => true,
		'ajax' => [
			'url' => Url::to(['dt-invoices']),
			'method' => 'POST',
			'data' => new JsExpression('function (data) {
				data.deleted = ' . json_encode($showTrash ? Invoice::YES : null) . ';
			}'),
			'reloadInterval' => 5 * 60000,
		],
		'order' => [
			[6, 'desc'],
		],
		'pageLength' => Yii::$app->settings->get('itemsPerPage'),
		'lengthMenu' => [
			'autoCreate' => true,
			'displayAll' => Yii::t('common', 'All'),
		],
		'autoWidth' => true,
		'responsive' => false,
		'scrollX' => false,
		'colReorder' => true,
		'dom' => 'lfrtip',
		'columns' => [
			[
				'class' => 'common\widgets\datatable\CheckboxColumn',
			],
			[
				'data' => 'action',
				'class' => 'common\widgets\datatable\ActionColumn',
				'title' => Yii::t('common', 'Action'),
				'className' => 'action-column col-autowidth text-center',
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
				'data' => 'subscription',
				'title' => Yii::t('label', 'Subscription'),
				'filter' => ['text'],
			],
			[
				'data' => 'subscriber',
				'title' => Yii::t('label', 'Subscriber'),
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
				'data' => 'e_invoice_upload_id',
				'title' => Yii::t('label', 'e-Invoice Upload ID'),
				'filter' => ['text'],
			],
			[
				'data' => 'e_invoice_download_id',
				'title' => Yii::t('label', 'e-Invoice Download ID'),
				'filter' => ['text'],
			],
			[
				'data' => 'e_invoice_status',
				'title' => Yii::t('label', 'e-Invoice Status'),
				'className' => 'col-autowidth',
				'filter' => ['select', ArrayHelper::getColumn(Invoice::getEStatusLabels(), 'label')],
			],
			[
				'data' => 'e_invoice_error',
				'title' => Yii::t('label', 'e-Invoice Error'),
				'filter' => ['text'],
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

<?php
$this->registerJs('
	$("#dt-invoices").on("draw.dt", function () {
        $("#dt-invoices").parent().doubleScroll();
		$(".doubleScroll-scroll").width($("#dt-invoices").outerWidth());
	});
	$("#dt-invoices").on("column-visibility.dt", function () {
        $("#dt-invoices").parent().doubleScroll();
		$(".doubleScroll-scroll").width($("#dt-invoices").outerWidth());
	});
	$(window).resize(function () {
        $("#dt-invoices").parent().doubleScroll();
		$(".doubleScroll-scroll").width($("#dt-invoices").outerWidth());
	});
	
	$("#dt-invoices").on("click", ".e-invoice .dropdown-item", function() {
         setTimeout(function() {
            $("#dt-invoices").DataTable().draw();
        }, 100);
    });
');
?>
