<?php

/* @var $this yii\web\View */
/* @var $model common\models\Menu */

use yii\helpers\Html;

$this->title = Yii::t('common', 'Create {item}', ['item' => Yii::t('common', 'Menu Item')]);
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
];
?>

<?= $this->render('_form', [
	'model' => $model,
]) ?>
