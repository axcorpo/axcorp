<?php
/* @var $this yii\web\View */

use common\models\CarouselItem;
use tws\widgets\sortable\Sortable;
use yii\helpers\Html;
use tws\helpers\Url;

$carousel = \common\models\Carousel::findOne(Yii::$app->request->get('carousel_id'));

if ($this->context->id != 'carousel') {
	$this->title = Yii::t('common', 'Carousel Items');
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
			'label' => $carousel->translation->name,
			'url' => ['carousel/update', 'id' => Yii::$app->request->get('carousel_id')],
		],
		[
			'label' => Yii::t('common', 'Carousel Items'),
			'url' => ['index', 'carousel_id' => Yii::$app->request->get('carousel_id')],
		],
	];

	if ($showTrash = Yii::$app->settings->get('enableSoftDelete')) {
		$showTrash = Yii::$app->request->get('deleted') == CarouselItem::YES;
		$this->params['breadcrumbs'][] = [
			'template' => '<li class="page-links">{link}</li>',
			'label' => implode('', [
				Html::a(Yii::t('common', 'Current'), ['index', 'carousel_id' => Yii::$app->request->get('carousel_id')], ['class' => $showTrash ? '' : 'active']),
				Html::a(Yii::t('common', 'Trash'), ['index', 'deleted' => CarouselItem::YES, 'carousel_id' => Yii::$app->request->get('carousel_id')], ['class' => $showTrash ? 'active' : '']),
			]),
		];
	}

	$this->params['actions'] = [
		[
			'visible' => Yii::$app->user->can('restoreCarousel') && $showTrash,
			'tag' => 'button',
			'icon' => 'fa fa-undo',
			'options' => [
				'class' => 'btn btn-sm btn-success hidden',
				'title' => Yii::t('common', 'Restore'),
				'data' => [
					'toggle' => 'tooltip',
					'dt-bulk-operation' => 'restore',
					'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
					'dt-url' => Url::to(['restore', 'carousel_id' => Yii::$app->request->get('carousel_id')]),
					'dt-table' => '#dt-carousel-items',
				],
			],
		],
		[
			'visible' => Yii::$app->user->can('deleteCarousel'),
			'tag' => 'button',
			'icon' => 'fa fa-trash',
			'options' => [
				'class' => 'btn btn-sm btn-danger hidden',
				'title' => $showTrash ? Yii::t('common', 'Delete Permanently') : Yii::t('common', 'Delete'),
				'data' => [
					'toggle' => 'tooltip',
					'dt-bulk-operation' => $showTrash ? 'delete-permanently' : 'delete',
					'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
					'dt-url' => Url::to(['delete', 'carousel_id' => Yii::$app->request->get('carousel_id')]),
					'dt-table' => '#dt-carousel-items',
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
} else {
	if ($showTrash = Yii::$app->settings->get('enableSoftDelete')) {
		$showTrash = Yii::$app->request->get('deleted') == CarouselItem::YES;
	}
}
?>

<?= Sortable::widget([
	'options' => [
		'id' => 'sortable-carousel-items',
	],
	'itemOptions' => [
		'item' => function (&$item, $content) use ($carousel, $showTrash) {
			if ($showTrash) {
				$actions = [
					Html::a('<span class="fa fa-undo"></span>', ['carousel-item/restore', 'carousel_id' => $carousel->id, 'id' => $item['id']], [
						'class' => 'btn btn-sm btn-success',
						'title' => Yii::t('common', 'Restore'),
						'data' => [
							'toggle' => 'tooltip',
							'popup-action' => '',
							'popup-method' => 'POST',
							'popup-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
							'popup-done' => ['reload' => '#sortable-carousel-items'],
						],
					]),
					Html::a('<span class="fa fa-trash"></span>', ['carousel-item/delete', 'carousel_id' => $carousel->id, 'id' => $item['id']], [
						'class' => 'btn btn-sm btn-danger',
						'title' => Yii::t('common', 'Delete'),
						'data' => [
							'toggle' => 'tooltip',
							'popup-action' => '',
							'popup-method' => 'POST',
							'popup-params' => [
								'dt_operation' => 'delete-permanently',
							],
							'popup-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
							'popup-done' => ['reload' => '#sortable-carousel-items'],
						],
					]),
				];
			} else {
				$actions = [
					Html::a('<span class="fa fa-eye"></span>', ['carousel-item/view', 'carousel_id' => $carousel->id, 'id' => $item['id']], [
						'class' => 'btn btn-sm btn-info',
						'title' => Yii::t('common', 'View'),
						'data' => [
							'toggle' => 'tooltip',
							'popup-action' => '',
						],
					]),
					Html::a('<span class="fa fa-edit"></span>', ['carousel-item/update', 'carousel_id' => $carousel->id, 'id' => $item['id']], [
						'class' => 'btn btn-sm btn-primary',
						'title' => Yii::t('common', 'Update'),
						'data' => [
							'toggle' => 'tooltip',
							'popup-action' => '',
							'popup-done' => ['reload' => '#sortable-carousel-items'],
						],
					]),
					Html::a('<span class="fa fa-trash"></span>', ['carousel-item/delete', 'carousel_id' => $carousel->id, 'id' => $item['id']], [
						'class' => 'btn btn-sm btn-danger',
						'title' => Yii::t('common', 'Delete'),
						'data' => [
							'toggle' => 'tooltip',
							'popup-action' => '',
							'popup-method' => 'POST',
							'popup-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
							'popup-done' => ['reload' => '#sortable-carousel-items'],
						],
					]),
				];
			}
			if ($item['model']->status == CarouselItem::STATUS_INACTIVE) {
				$item['options'] = [
					'class' => 'danger',
				];
			}
			if (!empty($item['model']->image)) {
				$icon = Html::tag('span', null, [
					'class' => 'gallery-thumbnail__figure',
					'style' => 'background-image: url("' . $item['model']->getImageUrl() . '");',
				]);
				$content = Html::tag('span', $content, [
					'class' => 'gallery-thumbnail__caption',
				]);
				$content = Html::a($icon . $content, $item['model']->getImageUrl(), [
					'class' => 'gallery-thumbnail',
					'title' => Yii::t('common', 'Open Gallery'),
					'data' => [
						'toggle' => 'tooltip',
						'fancybox' => 'menu-items',
						'caption' => $item['model']->translation->title,
					],
				]);
			}
			return $content . Html::tag('div', implode("\n", $actions), [
					'class' => 'sortable-item__actions',
				]);
		},
	],
	'emptyOptions' => [
		'message' => Yii::t('common', 'No records found.'),
		'class' => 'text-center',
	],
	'items' => $carousel->getSortableCarouselItems($showTrash ? CarouselItem::YES : null),
	'itemHandle' => true,
	'clientOptions' => [
		'opacity' => 0.6,
		'tolerance' => 'pointer',
		'toleranceElement' => '> div',
		'tabSize' => 25,
		'maxLevels' => 1,
		'saveOrder' => [
			'url' => Url::to(['carousel-item/reorder', 'carousel_id' => $carousel->id]),
			'method' => 'POST',
		],
	],
]) ?>
