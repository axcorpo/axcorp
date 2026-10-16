<?php

/* @var $this yii\web\View */
/* @var $model common\models\CarouselItem */

use yii\helpers\Html;

$this->title = Yii::t('common', 'Update {item}', ['item' => Yii::t('common', 'Carousel Item')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Website'),
		'url' => ['default/index'],
	],
	[
		'label' => Yii::t('common', 'Carousels'),
		'url' => ['carousel/index'],
	],
	[
		'label' => Yii::t('common', 'Carousel'),
		'url' => ['carousel/update', 'id' => Yii::$app->request->get('carousel_id')],
	],
	[
		'label' => Yii::t('common', 'Carousel Items'),
		'url' => ['index', 'carousel_id' => Yii::$app->request->get('carousel_id')],
	],
	$this->title,
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewCarousel'),
		'tag' => 'a',
		'url' => ['index', 'carousel_id' => Yii::$app->request->get('carousel_id')],
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
		'visible' => Yii::$app->user->can('createCarousel'),
		'tag' => 'a',
		'url' => ['create', 'carousel_id' => Yii::$app->request->get('carousel_id')],
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
