<?php

/* @var $this yii\web\View */
/* @var $dataTable string the DataTable selector */
/* @var $model string the DataTableAction class that builds the rows */
/* @var $title string */

use backend\modules\export\widgets\export\Export;
use tws\helpers\Url;

// XLSX export of a list through export-manager, with the grid's current filters.
?>
<?= Export::widget([
	'buttonOptions' => [
		'class' => 'btn btn-sm btn-default',
		'style' => 'margin: 0 5px;',
		'title' => Yii::t('common', 'Export'),
		'label' => '<span class="fa fa-sign-out"></span>',
	],
	'dropdownOptions' => [
		'class' => 'pull-right',
	],
	'items' => [
		Export::FORMAT_XLSX => [
			'visible' => true,
			'label' => 'XLSX',
			'url' => '#',
			'clientOptions' => [
				'shouldCreateNewSheetsAutomatically' => true,
				'shouldUseInlineStrings' => true,
			],
		],
	],
	'clientOptions' => [
		'url' => Url::to(['/export-manager/export']),
		'dataTable' => $dataTable,
		'model' => Yii::$app->security->maskToken($model),
		'title' => $title,
	],
]) ?>
