<?php

/* @var $this yii\web\View */

use common\models\Language;
use common\widgets\datatable\DataTable;
use kartik\switchinput\SwitchInput;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Languages');
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Settings'),
		'url' => ['index'],
	],
	$this->title,
];

$swInp = SwitchInput::widget([
	'name' => '',
	'options' => [
		'class' => 'form-control',
		'data' => [
			'key' => 'language_id',
			'attribute' => 'status',
			'on-done' => 'redrawAndNotify',
		],
	],
	'pluginOptions' => [
		'size' => 'mini',
		'onColor' => 'success',
		'offColor' => 'danger',
		'onText' => Yii::t('label', 'Active'),
		'offText' => Yii::t('label', 'Inactive'),
	],
]);
?>

<?= DataTable::widget([
	'id' => 'dt-languages',
	'options' => [
		'class' => 'table table-bordered table-hover',
	],
	'showColumnFilters' => true,
	'clientOptions' => [
		'deferRender' => true,
		'processing' => true,
		'serverSide' => true,
		'ajax' => [
			'url' => Url::to(['dt-languages']),
			'method' => 'POST',
		],
		'order' => [
			[6, 'desc'],
			[4, 'asc'],
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
				'class' => 'common\widgets\datatable\ActionColumn',
				'title' => Yii::t('common', 'Action'),
				'className' => 'action-column col-autowidth text-center',
				'filter' => Html::tag('button', '<span class="fa fa-times"></span>', [
					'type' => 'button',
					'class' => 'btn btn-xs btn-default',
					'title' => Yii::t('common', 'Clear column filters'),
					'data' => [
						'toggle' => 'tooltip',
						'dt-clear-filters' => true,
					],
				]),
				'buttons' => [
					'translate' => [
						'visible' => Yii::$app->user->can('translateIntoLanguage'),
						'url' => ['translate', 'id' => new JsExpression('language_id')],
						'content' => '<span class="fa fa-language"></span>',
						'options' => [
							'class' => 'btn btn-xs btn-primary',
							'title' => Yii::t('common', 'Translate'),
							'data' => [
								'toggle' => 'tooltip',
							],
						],
					],
				],
			],
			[
				'data' => 'language_id',
				'title' => Yii::t('label', 'ID'),
				'className' => 'col-autowidth',
				'filter' => ['text'],
			],
			[
				'data' => 'language',
				'title' => Yii::t('label', 'Language'),
				'className' => 'col-autowidth',
				'filter' => ['text'],
			],
			[
				'data' => 'name',
				'title' => Yii::t('label', 'Name'),
				'filter' => ['text'],
			],
			[
				'data' => 'name_ascii',
				'title' => Yii::t('label', 'Name Ascii'),
				'filter' => ['text'],
			],
			[
				'data' => 'progress',
				'title' => Yii::t('label', 'Progress'),
				'className' => 'col-autowidth',
				'orderable' => false,
				'render' => new JsExpression('function (data, type, row, meta) {
					var $progressBar = $("<div/>", {
						"class": "progress-bar progress-bar-info progress-bar-striped",
						"style": "width: " + data + "%;",
						"html": data + "%"
					});
					return $("<div/>", {
						"class": "progress",
						"html": $progressBar.prop("outerHTML")
					}).prop("outerHTML");
				}'),
			],
			[
				'class' => 'common\widgets\datatable\FieldColumn',
				'className' => 'field-column col-autowidth',
				'data' => 'status',
				'title' => Yii::t('label', 'Status'),
				'filter' => ['select', ArrayHelper::getColumn(Language::getStatusLabels(), 'label')],
				'render' => new JsExpression('function (data, type, row, meta) {
					var canUpdateLanguage = ' . Json::encode(Yii::$app->user->can('updateLanguage')) . ';
					var inputControl = ' . Json::encode($swInp) . ';
					var statuses = ' . Json::encode(Language::getStatusLabels()) . ';

					if (row.language_id == "' . Yii::$app->language . '") {
						return statuses[data].label;
					}
					if (canUpdateLanguage) {
						return inputControl;
					}
					return $("<label/>", {
						"class": "label label-block label-" + statuses[data].color,
						"text": statuses[data].label
					}).prop("outerHTML");
				}'),
			],
		],
		'rowCallback' => new JsExpression('function (row, data, index) {
			if (data.language_id == "' . Yii::$app->language . '") {
				$(row).addClass("success");
			}
		}'),
	],
]) ?>
