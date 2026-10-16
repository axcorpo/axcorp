<?php

/* @var $this yii\web\View */

use common\models\Testimonial;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Testimonials');
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Website'),
		'url' => ['default/index'],
	],
	$this->title,
];

if ($showTrash = Yii::$app->settings->get('enableSoftDelete')) {
	$showTrash = Yii::$app->request->get('deleted') == Testimonial::YES;
	$this->params['breadcrumbs'][] = [
		'template' => '<li class="page-links">{link}</li>',
		'label' => implode('', [
			Html::a(Yii::t('common', 'Current'), ['index'], ['class' => $showTrash ? '' : 'active']),
			Html::a(Yii::t('common', 'Trash'), ['index', 'deleted' => Testimonial::YES], ['class' => $showTrash ? 'active' : '']),
		]),
	];
}

$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('restoreTestimonial') && $showTrash,
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
				'dt-table' => '#dt-testimonials',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('deleteTestimonial'),
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
				'dt-table' => '#dt-testimonials',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('createTestimonial'),
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
];

\kartik\rating\StarRatingAsset::register($this);
?>

<?= DataTable::widget([
	'id' => 'dt-testimonials',
	'options' => [
		'class' => 'table table-bordered table-hover',
	],
	'showColumnFilters' => true,
	'clientOptions' => [
		'deferRender' => true,
		'processing' => true,
		'serverSide' => true,
		'ajax' => [
			'url' => Url::to(['dt-testimonials']),
			'method' => 'POST',
			'data' => new JsExpression('function (data) {
				data.deleted = ' . json_encode($showTrash ? Testimonial::YES : null) . ';
			}'),
		],
		'order' => [
			[8, 'desc'],
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
				'class' => 'common\widgets\datatable\CheckboxColumn',
			],
			[
				'class' => 'common\widgets\datatable\ActionColumn',
				'data' => 'action',
				'title' => Yii::t('common', 'Action'),
			],
			[
				'data' => 'image',
				'title' => Yii::t('label', 'Image'),
				'className' => 'figure-column col-autowidth text-center',
				'orderable' => false,
				'filter' => false,
			],
			[
				'data' => 'name',
				'title' => Yii::t('label', 'Name'),
				'filter' => ['text'],
			],
			[
				'data' => 'role',
				'title' => Yii::t('label', 'Role'),
				'filter' => ['text'],
			],
			[
				'data' => 'organization',
				'title' => Yii::t('label', 'Organization'),
				'filter' => ['text'],
			],
			[
				'data' => 'rating',
				'title' => Yii::t('label', 'Rating'),
				'filter' => ['select', [
					0 => '0 ' . mb_strtolower(Yii::t('common', 'Stars')),
					1 => '1 ' . mb_strtolower(Yii::t('common', 'Star')),
					2 => '2 ' . mb_strtolower(Yii::t('common', 'Stars')),
					3 => '3 ' . mb_strtolower(Yii::t('common', 'Stars')),
					4 => '4 ' . mb_strtolower(Yii::t('common', 'Stars')),
					5 => '5 ' . mb_strtolower(Yii::t('common', 'Stars')),
				]],
				'render' => new JsExpression('function (data, type, row, meta) {
					return $input = $("<input/>", {
						id: "rating-" + row.id,
						class: "hidden",
						value: data
					}).prop("outerHTML");
				}'),
			],
			[
				'data' => 'updated_by',
				'title' => Yii::t('label', 'Updated By'),
				'filter' => ['text'],
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
				'filter' => ['select', ArrayHelper::getColumn(Testimonial::getStatusLabels(), 'label')],
			],
		],
		'rowCallback' => new JsExpression('function (row, data) {
			var $row = $(row);
			
			$row.find("#rating-" + data.id).rating({
				displayOnly: true,
				showCaption: false,
				showClear: false,
				size: "xs",
				min: 0,
				max: 5,
				stars: 5,
				step: 1,
				containerClass: "dt-rating-container"
			});
		}'),
	],
]) ?>
