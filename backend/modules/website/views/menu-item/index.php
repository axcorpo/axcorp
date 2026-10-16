<?php
/* @var $this yii\web\View */

use common\models\MenuItem;
use tws\widgets\sortable\Sortable;
use yii\helpers\Html;
use tws\helpers\Url;

$menu = \common\models\Menu::findOne(Yii::$app->request->get('menu_id'));

if ($this->context->id != 'menu') {
	$this->title = Yii::t('common', 'Menu Items');
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
			'label' => $menu->translation->name,
			'url' => ['menu/update', 'id' => Yii::$app->request->get('menu_id')],
		],
		[
			'label' => Yii::t('common', 'Menu Items'),
			'url' => ['index', 'menu_id' => Yii::$app->request->get('menu_id')],
		],
	];

	if ($showTrash = Yii::$app->settings->get('enableSoftDelete')) {
		$showTrash = Yii::$app->request->get('deleted') == MenuItem::YES;
		$this->params['breadcrumbs'][] = [
			'template' => '<li class="page-links">{link}</li>',
			'label' => implode('', [
				Html::a(Yii::t('common', 'Current'), ['index', 'menu_id' => Yii::$app->request->get('menu_id')], ['class' => $showTrash ? '' : 'active']),
				Html::a(Yii::t('common', 'Trash'), ['index', 'deleted' => MenuItem::YES, 'menu_id' => Yii::$app->request->get('menu_id')], ['class' => $showTrash ? 'active' : '']),
			]),
		];
	}

	$this->params['actions'] = [
		[
			'visible' => Yii::$app->user->can('restoreMenu') && $showTrash,
			'tag' => 'button',
			'icon' => 'fa fa-undo',
			'options' => [
				'class' => 'btn btn-sm btn-success hidden',
				'title' => Yii::t('common', 'Restore'),
				'data' => [
					'toggle' => 'tooltip',
					'dt-bulk-operation' => 'restore',
					'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
					'dt-url' => Url::to(['restore', 'menu_id' => Yii::$app->request->get('menu_id')]),
					'dt-table' => '#dt-menu-items',
				],
			],
		],
		[
			'visible' => Yii::$app->user->can('deleteMenu'),
			'tag' => 'button',
			'icon' => 'fa fa-trash',
			'options' => [
				'class' => 'btn btn-sm btn-danger hidden',
				'title' => $showTrash ? Yii::t('common', 'Delete Permanently') : Yii::t('common', 'Delete'),
				'data' => [
					'toggle' => 'tooltip',
					'dt-bulk-operation' => $showTrash ? 'delete-permanently' : 'delete',
					'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
					'dt-url' => Url::to(['delete', 'menu_id' => Yii::$app->request->get('menu_id')]),
					'dt-table' => '#dt-menu-items',
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
} else {
	if ($showTrash = Yii::$app->settings->get('enableSoftDelete')) {
		$showTrash = Yii::$app->request->get('deleted') == MenuItem::YES;
	}
}
?>

<?= Sortable::widget([
	'options' => [
		'id' => 'sortable-menu-items',
	],
	'itemOptions' => [
		'item' => function (&$item, $content) use ($menu, $showTrash) {
			/** @var MenuItem $itemModel */
			$itemModel = $item['model'];
			$itemModelTranslation = $itemModel->getTranslation();
			if ($showTrash) {
				$actions = [
					Html::a('<span class="fa fa-undo"></span>', ['menu-item/restore', 'menu_id' => $menu->id, 'id' => $item['id']], [
						'class' => 'btn btn-sm btn-success',
						'title' => Yii::t('common', 'Restore'),
						'data' => [
							'toggle' => 'tooltip',
							'popup-action' => '',
							'popup-method' => 'POST',
							'popup-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
							'popup-done' => ['reload' => '#sortable-menu-items'],
						],
					]),
					Html::a('<span class="fa fa-trash"></span>', ['menu-item/delete', 'menu_id' => $menu->id, 'id' => $item['id']], [
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
							'popup-done' => ['reload' => '#sortable-menu-items'],
						],
					]),
				];
			} else {
				$actions = [
					Html::a('<span class="fa fa-eye"></span>', ['menu-item/view', 'menu_id' => $menu->id, 'id' => $item['id']], [
						'class' => 'btn btn-sm btn-info',
						'title' => Yii::t('common', 'View'),
						'data' => [
							'toggle' => 'tooltip',
							'popup-action' => '',
						],
					]),
					Html::a('<span class="fa fa-edit"></span>', ['menu-item/update', 'menu_id' => $menu->id, 'id' => $item['id']], [
						'class' => 'btn btn-sm btn-primary',
						'title' => Yii::t('common', 'Update'),
						'data' => [
							'toggle' => 'tooltip',
							'popup-action' => '',
							'popup-done' => ['reload' => '#sortable-menu-items'],
						],
					]),
					Html::a('<span class="fa fa-trash"></span>', ['menu-item/delete', 'menu_id' => $menu->id, 'id' => $item['id']], [
						'class' => 'btn btn-sm btn-danger',
						'title' => Yii::t('common', 'Delete'),
						'data' => [
							'toggle' => 'tooltip',
							'popup-action' => '',
							'popup-method' => 'POST',
							'popup-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
							'popup-done' => ['reload' => '#sortable-menu-items'],
						],
					]),
				];
			}
			if ($itemModel->status == MenuItem::STATUS_INACTIVE) {
				$item['options'] = [
					'class' => 'danger',
				];
			}
			$icon = '';
			if (!empty($itemModel->icon)) {
				$icon = (\common\helpers\FontIcon::render($itemModel->icon) . ' ');
			}
			if (isset($itemModel->page_id)) {
				if (strlen($itemModelTranslation->url) > 1 && $itemModelTranslation->url[0] === '#') {
					$content .= (' # ' . $itemModelTranslation->title ?: $itemModelTranslation->url);
				} elseif (!empty($itemModelTranslation->title)) {
					$content .= (" ({$itemModelTranslation->title})");
				}
				$content = Html::a($icon . $content, ['/website-manager/page/view', 'id' => $itemModel->page_id], [
					'target' => '_blank',
				]);
			} else {
				$content = Html::a($icon . $content, $itemModelTranslation->url, [
					'target' => '_blank',
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
	'items' => $menu->getNestedItems(null, null, $showTrash ? MenuItem::YES : null),
	'itemHandle' => true,
	'clientOptions' => [
		'opacity' => 0.6,
		'tolerance' => 'pointer',
		'toleranceElement' => '> div',
		'tabSize' => 25,
		'maxLevels' => 1,
//		'saveOrder' => '#input-test',
		'saveOrder' => [
			'url' => Url::to(['menu-item/reorder', 'menu_id' => $menu->id]),
			'method' => 'POST',
		],
	],
]) ?>
