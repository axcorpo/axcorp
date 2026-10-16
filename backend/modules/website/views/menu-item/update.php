<?php

/* @var $this yii\web\View */
/* @var $model common\models\Menu */

use tws\widgets\sortable\Sortable;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Update {item}', ['item' => Yii::t('common', 'Menu Item')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Website'),
		'url' => ['default/index'],
	],
	[
		'label' => Yii::t('common', 'Menus'),
		'url' => ['menu/index'],
	],
	[
		'label' => Yii::t('common', 'Menu'),
		'url' => ['menu/update', 'id' => Yii::$app->request->get('menu_id')],
	],
	[
		'label' => Yii::t('common', 'Menu Items'),
		'url' => ['index', 'menu_id' => Yii::$app->request->get('menu_id')],
	],
	$this->title,
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewMenu'),
		'tag' => 'a',
		'url' => ['index', 'menu_id' => Yii::$app->request->get('menu_id')],
		'icon' => 'fa fa-list',
		'options' => [
			'class' => 'btn btn-sm btn-default',
			'title' => Yii::t('common', 'List'),
			'data' => [
				'toggle' => 'tooltip',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('createMenu'),
		'tag' => 'a',
		'url' => ['create', 'menu_id' => Yii::$app->request->get('menu_id')],
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
?>

<?= $this->render('_form', [
	'model' => $model,
]) ?>
