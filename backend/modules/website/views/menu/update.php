<?php

/* @var $this yii\web\View */
/* @var $model common\models\Menu */

use common\models\MenuItem;
use tws\widgets\sortable\Sortable;
use yii\helpers\Html;
use tws\helpers\Url;

$this->title = Yii::t('common', 'Update {item}', ['item' => Yii::t('common', 'Menu')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Website'),
		'url' => ['default/index'],
	],
	[
		'label' => Yii::t('common', 'Menus'),
		'url' => ['index'],
	],
	$this->title,
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewMenu'),
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
		'visible' => Yii::$app->user->can('createMenu'),
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
$requestQueryParams['menu_id'] = $model->id;
Yii::$app->request->setQueryParams($requestQueryParams);
?>

<?= $this->render('_form', [
	'model' => $model,
]) ?>

<div class="panel blue-hoki">
	<div class="panel-title">
		<div class="panel-heading">
			<?= Yii::t('common', 'Menu Items') ?>
			<?php if ($showTrash = Yii::$app->settings->get('enableSoftDelete')): ?>
				<?php $showTrash = Yii::$app->request->get('deleted') == MenuItem::YES; ?>
				<span class="page-links">
					<?= implode('', [
						Html::a(Yii::t('common', 'Current'), ['update', 'id' => $model->id], ['class' => $showTrash ? '' : 'active']),
						Html::a(Yii::t('common', 'Trash'), ['update', 'id' => $model->id, 'deleted' => MenuItem::YES], ['class' => $showTrash ? 'active' : '']),
					]); ?>
				</span>
			<?php endif; ?>
		</div>
		<div class="panel-actions">
			<?php if (Yii::$app->user->can('createMenu')): ?>
				<?= Html::a('<span class="fa fa-plus"></span>', ['menu-item/create', 'menu_id' => $model->id], [
					'type' => 'button',
					'class' => 'btn btn-sm btn-success',
					'title' => Yii::t('common', 'Create'),
					'data' => [
						'toggle' => 'tooltip',
						'popup-action' => '',
						'popup-done' => ['reload' => '#sortable-menu-items'],
					],
				]) ?>
			<?php endif; ?>
		</div>
	</div>
	<div class="panel-body">
		<?= $this->render('@website/views/menu-item/index') ?>
	</div>
</div>
