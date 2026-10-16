<?php

/* @var $this yii\web\View */

use common\models\Currency;
use common\widgets\datatable\DataTable;
use kartik\switchinput\SwitchInput;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Currencies');
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
			'key' => 'id',
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
	'id' => 'dt-currencies',
	'options' => [
		'class' => 'table table-bordered table-hover',
	],
	'showColumnFilters' => true,
	'clientOptions' => [
		'deferRender' => true,
		'processing' => true,
		'serverSide' => true,
		'ajax' => [
			'url' => Url::to(['dt-currencies']),
			'method' => 'POST',
		],
		'order' => [
			[3, 'desc'],
			[0, 'asc'],
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
				'data' => 'iso_code',
				'title' => Yii::t('label', 'Iso Code'),
				'className' => 'col-autowidth',
				'filter' => ['text'],
			],
			[
				'data' => 'symbol',
				'title' => Yii::t('label', 'Symbol'),
				'className' => 'col-autowidth',
				'filter' => ['text'],
			],
			[
				'data' => 'name',
				'title' => Yii::t('label', 'Name'),
				'filter' => ['text'],
			],
			[
				'class' => 'common\widgets\datatable\FieldColumn',
				'className' => 'field-column col-autowidth',
				'data' => 'status',
				'title' => Yii::t('label', 'Status'),
				'filter' => ['select', ArrayHelper::getColumn(Currency::getStatusLabels(), 'label')],
				'render' => new JsExpression('function (data, type, row, meta) {
					var canUpdateCurrency = ' . Json::encode(Yii::$app->user->can('updateCurrency')) . ';
					var inputControl = ' . Json::encode($swInp) . ';
					var statuses = ' . Json::encode(Currency::getStatusLabels()) . ';

					if (row.iso_code == "' . Yii::$app->settings->get('currencyCode', 'general') . '") {
						return statuses[data].label;
					}
					if (canUpdateCurrency) {
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
			if (data.iso_code == "' . Yii::$app->settings->get('currencyCode', 'general') . '") {
				$(row).addClass("success");
			}
		}'),
	],
]) ?>
