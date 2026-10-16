<?php

/* @var $this yii\web\View */
/* @var $categories array */

use common\widgets\datatable\DataTable;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Translation into {language}', ['language' => Yii::$app->request->get('id')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Settings'),
		'url' => ['/setting-manager/setting/index'],
	],
	[
		'label' => Yii::t('common', 'Languages'),
		'url' => ['index'],
	],
	$this->title,
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewLanguage'),
		'tag' => 'a',
		'url' => ['index'],
		'icon' => 'fa fa-list',
		'options' => [
			'class' => 'btn btn-sm btn-default',
			'title' => Yii::t('common', 'Languages'),
			'data' => [
				'toggle' => 'tooltip',
			],
		],
	],
    [
        'visible' => Yii::$app->user->can('viewLanguage') && Yii::$app->request->get('id') != 'en-US',
        'tag' => 'a',
        'url' => ['translate', 'id' => Yii::$app->request->get('id'), 'translator' => 1],
        'icon' => 'fa fa-language',
        'options' => [
            'class' => 'btn btn-sm btn-info',
            'title' => Yii::t('label', 'Translate'),
            'data' => [
                'toggle' => 'tooltip',
            ],
        ],
    ],
    [
        'visible' => Yii::$app->user->can('viewLanguage') && Yii::$app->request->get('id') != 'en-US',
        'tag' => 'a',
        'url' => ['translate', 'id' => Yii::$app->request->get('id'), 'translator' => 1, 'overwrite' => 1],
        'icon' => 'fa fa-language',
        'options' => [
            'class' => 'btn btn-sm btn-warning',
            'title' => Yii::t('label', 'Translate & Overwrite'),
            'data' => [
                'toggle' => 'tooltip',
            ],
        ],
    ],
];

\tws\widgets\tinymce\TinyMCEAsset::register($this);
?>

<div class="table-responsive borderless">
	<?= DataTable::widget([
		'id' => 'dt-language-translations',
		'options' => [
			'class' => 'table table-bordered table-hover',
		],
		'showColumnFilters' => true,
		'clientOptions' => [
			'deferRender' => true,
			'processing' => true,
			'serverSide' => true,
			'ajax' => [
				'url' => Url::to(['dt-language-translations']),
				'method' => 'POST',
				'data' => new JsExpression('function (data) {
			 	data.language_id = "' . Yii::$app->request->get('id') . '";
			}'),
			],
			'order' => [
				[1, 'asc'],
			],
			'pageLength' => Yii::$app->settings->get('itemsPerPage'),
			'lengthMenu' => [
				'autoCreate' => true,
				'displayAll' => Yii::t('common', 'All'),
			],
			'autoWidth' => false,
			'columns' => [
				[
					'class' => 'common\widgets\datatable\SerialColumn',
					'filter' => Html::tag('button', '<span class="fa fa-times"></span>', [
						'type' => 'button',
						'class' => 'btn btn-xs btn-default',
						'title' => Yii::t('common', 'Clear column filters'),
						'data' => [
							'toggle' => 'tooltip',
							'dt-clear-filters' => true,
						],
					]),
				],
				[
					'data' => 'category',
					'title' => Yii::t('common', 'Category'),
					'filter' => ['select', $categories],
				],
				[
					'data' => 'message',
					'title' => Yii::t('common', 'Source'),
					'className' => 'source-column',
					'width' => '40%',
					'filter' => ['text'],
					'render' => new JsExpression('function (data, type, row, meta) {
					return $("<div/>", {
						"class": "control-source",
						"html": data,
						"title": "' . Yii::t('common', 'Copy') . '",
						"data-toggle": "tooltip",
					}).prop("outerHTML");
				}'),
				],
				[
					'class' => 'common\widgets\datatable\FieldColumn',
					'data' => 'translation',
					'title' => Yii::t('common', 'Translation'),
					'className' => 'field-column translation-column',
					'width' => '40%',
					'filter' => ['text'],
					'render' => new JsExpression('function (data, type, row, meta) {
					return $("<textarea/>", {
						"id": "textarea-editor-" + meta.row,
						"rows": 2,
						"html": data,
						"data-key": "id",
						"data-attribute": "translation",
					}).prop("outerHTML") + $("<div/>", {
						"id": "editor-" + meta.row,
						"class": "control-translation",
						"html": data,
					}).prop("outerHTML");
				}'),
				],
			],
		],
	]) ?>
</div>
