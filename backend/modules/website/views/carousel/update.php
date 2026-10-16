<?php

/* @var $this yii\web\View */
/* @var $model common\models\Carousel */

use common\models\CarouselItem;
use tws\widgets\sortable\Sortable;
use yii\helpers\Html;
use tws\helpers\Url;

$this->title = Yii::t('common', 'Update {item}', ['item' => Yii::t('common', 'Carousel')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Website'),
		'url' => ['default/index'],
	],
	[
		'label' => Yii::t('common', 'Carousels'),
		'url' => ['index'],
	],
	$this->title,
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewCarousel'),
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
		'visible' => Yii::$app->user->can('createCarousel'),
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

$requestQueryParams = Yii::$app->request->getQueryParams();
$requestQueryParams['carousel_id'] = $model->id;
Yii::$app->request->setQueryParams($requestQueryParams);
?>

<?= $this->render('_form', [
	'model' => $model,
]) ?>

<div class="panel box blue-hoki">
	<div class="panel-title">
		<div class="panel-heading">
			<?= Yii::t('common', 'Slides') ?>
			<?php if ($showTrash = Yii::$app->settings->get('enableSoftDelete')): ?>
				<?php $showTrash = Yii::$app->request->get('deleted') == CarouselItem::YES; ?>
				<span class="page-links">
					<?= implode('', [
						Html::a(Yii::t('common', 'Current'), ['update', 'id' => $model->id], ['class' => $showTrash ? '' : 'active']),
						Html::a(Yii::t('common', 'Trash'), ['update', 'id' => $model->id, 'deleted' => CarouselItem::YES], ['class' => $showTrash ? 'active' : '']),
					]); ?>
				</span>
			<?php endif; ?>
		</div>
		<div class="panel-actions">
			<?php if (Yii::$app->user->can('createMenu')): ?>
				<?= Html::a('<span class="fa fa-plus"></span>', ['carousel-item/create', 'carousel_id' => $model->id], [
					'type' => 'button',
					'class' => 'btn btn-sm btn-success',
					'title' => Yii::t('common', 'Create'),
					'data' => [
						'toggle' => 'tooltip',
						'popup-action' => '',
						'popup-done' => ['reload' => '#sortable-carousel-items'],
					],
				]) ?>
			<?php endif; ?>
		</div>
	</div>
	<div class="panel-body">
		<?= $this->render('@website/views/carousel-item/index') ?>
	</div>
</div>
