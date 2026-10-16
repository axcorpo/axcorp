<?php

/* @var $this yii\web\View */

use common\models\Integration;
use common\widgets\datatable\DataTable;
use kartik\switchinput\SwitchInput;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Integrations');
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Settings'),
		'url' => ['index'],
	],
	$this->title,
];

$swSandbox = SwitchInput::widget([
	'name' => '',
	'options' => [
		'class' => 'form-control',
		'data' => [
			'key' => 'id',
			'attribute' => 'sandbox',
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
$swStatus = SwitchInput::widget([
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
	'id' => 'dt-integrations',
	'options' => [
		'class' => 'table table-bordered table-hover',
	],
	'showColumnFilters' => true,
	'clientOptions' => [
		'deferRender' => true,
		'processing' => true,
		'serverSide' => true,
		'ajax' => [
			'url' => Url::to(['dt-integrations']),
			'method' => 'POST',
		],
		'order' => [
			[3, 'desc'],
			[1, 'asc'],
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
				'data' => 'action',
				'title' => '',
			],
			[
				'data' => 'type',
				'title' => Yii::t('label', 'Type'),
				'className' => 'col-autowidth',
				'filter' => ['text'],
			],
			[
				'data' => 'name',
				'title' => Yii::t('label', 'Name'),
				'filter' => ['text'],
			],
			[
				'data' => 'expire_at',
				'title' => Yii::t('label', 'Expire At'),
				'filter' => ['date', 'icu:' . Yii::$app->settings->get('dateFormat')],
			],
			[
				'class' => 'common\widgets\datatable\FieldColumn',
				'className' => 'field-column col-autowidth',
				'data' => 'sandbox',
				'title' => Yii::t('label', 'Sandbox'),
				'filter' => ['select', ArrayHelper::getColumn(Integration::getStatusLabels(), 'label')],
				'render' => new JsExpression('function (data, type, row, meta) {
					var canUpdateIntegration = ' . Json::encode(Yii::$app->user->can('updateIntegration')) . ';
					var inputControl = ' . Json::encode($swSandbox) . ';
					var statuses = ' . Json::encode(Integration::getStatusLabels()) . ';

					if (row.iso_code == "' . Yii::$app->settings->get('integrationCode', 'general') . '") {
						return statuses[data].label;
					}
					if (canUpdateIntegration) {
						return inputControl;
					}
					return $("<label/>", {
						"class": "label label-block label-" + statuses[data].color,
						"text": statuses[data].label
					}).prop("outerHTML");
				}'),
			],
			[
				'class' => 'common\widgets\datatable\FieldColumn',
				'className' => 'field-column col-autowidth',
				'data' => 'status',
				'title' => Yii::t('label', 'Status'),
				'filter' => ['select', ArrayHelper::getColumn(Integration::getStatusLabels(), 'label')],
				'render' => new JsExpression('function (data, type, row, meta) {
					var canUpdateIntegration = ' . Json::encode(Yii::$app->user->can('updateIntegration')) . ';
					var inputControl = ' . Json::encode($swStatus) . ';
					var statuses = ' . Json::encode(Integration::getStatusLabels()) . ';

					if (row.iso_code == "' . Yii::$app->settings->get('integrationCode', 'general') . '") {
						return statuses[data].label;
					}
					if (canUpdateIntegration) {
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
			if (data.iso_code == "' . Yii::$app->settings->get('integrationCode', 'general') . '") {
				$(row).addClass("success");
			}
		}'),
	],
]) ?>
