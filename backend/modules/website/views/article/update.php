<?php
/* @var $this yii\web\View */
/* @var $model common\models\Article */

use yii\helpers\Html;

$this->title = Yii::t('common', 'Update {item}', ['item' => Yii::t('common', 'Article')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Website'),
		'url' => ['default/index'],
	],
	[
		'label' => Yii::t('common', 'Articles'),
		'url' => ['index'],
	],
	$this->title,
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewArticle'),
		'tag' => 'a',
		'url' => ['index'],
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
		'visible' => Yii::$app->user->can('createArticle'),
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
?>

<?= $this->render('_form', [
	'model' => $model,
]) ?>
