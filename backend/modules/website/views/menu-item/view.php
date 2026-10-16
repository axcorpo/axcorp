<?php

/* @var $this yii\web\View */
/* @var $model common\models\Menu */

use common\helpers\FontIcon;
use common\models\Menu;
use common\models\MenuItem;
use yii\helpers\Html;
use yii\widgets\DetailView;

$this->title = Yii::t('common', 'View {item}', ['item' => Yii::t('common', 'Menu Item')]);
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
		'visible' => Yii::$app->user->can('updateMenu'),
		'tag' => 'a',
		'url' => ['update', 'menu_id' => Yii::$app->request->get('menu_id'), 'id' => $model->id],
		'icon' => 'fa fa-edit',
		'options' => [
			'class' => 'btn btn-sm btn-primary',
			'title' => Yii::t('common', 'Update'),
			'data' => [
				'toggle' => 'tooltip',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('deleteMenu'),
		'tag' => 'a',
		'url' => ['delete', 'menu_id' => Yii::$app->request->get('menu_id'), 'id' => $model->id],
		'icon' => 'fa fa-trash',
		'options' => [
			'class' => 'btn btn-sm btn-danger',
			'title' => Yii::t('common', 'Delete'),
			'data' => [
				'toggle' => 'tooltip',
				'confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
				'method' => 'POST',
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

$showEventLogs = isset($showEventLogs) ? $showEventLogs : Yii::$app->eventLog->enabled;
?>

<?php if (Yii::$app->request->isAjax): ?>
<div class="modal-dialog modal-lg">
	<div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
			<div class="modal-title"><?= $this->title ?></div>
		</div>
		<div class="modal-body">
<?php endif; ?>

			<div class="table-responsive">
				<?= DetailView::widget([
					'model' => $model,
					'options' => [
						'class' => 'table table-striped table-bordered detail-view detail-view-fixed',
					],
					'attributes' => [
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Title'),
							'value' => function (MenuItem $model) {
								return $model->translation->title ?: '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Page'),
							'value' => function (MenuItem $model) {
								return $model->page ? Html::a($model->page->translation->title, ['/page/view', 'id' => $model->page->id]) : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Icon'),
							'value' => function (MenuItem $model) {
								return $model->icon ? FontIcon::render($model->icon, ['class' => 'fa-lg']) : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'URL'),
							'value' => function (MenuItem $model) {
								return $model->translation->url ?: '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Target'),
							'value' => function (MenuItem $model) {
								return $model->target ?: '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Excluded'),
							'value' => function (MenuItem $model) {
								return Yii::$app->formatter->asBoolean($model->excluded);
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Sort Order'),
							'value' => function (MenuItem $model) {
								return $model->sort_order ?: '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Created By'),
							'value' => function (MenuItem $model) {
								return $model->creator ? Html::a($model->creator->fullName, ['/user-manager/user/view', 'id' => $model->creator->id]) : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Created At'),
							'value' => function (MenuItem $model) {
								return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Updated By'),
							'value' => function (MenuItem $model) {
								return $model->updater ? Html::a($model->updater->fullName, ['/user-manager/user/view', 'id' => $model->updater->id]) : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Updated At'),
							'value' => function (MenuItem $model) {
								return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Status'),
							'value' => function (MenuItem $model) {
								$status = Menu::getStatusLabels()[$model->status];
								return Html::tag('span', $status['label'], ['class' => 'label label-' . $status['color']]);
							},
						],
					],
				]) ?>
			</div>

<?php if (Yii::$app->request->isAjax): ?>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><?= Yii::t('common', 'Close') ?></button>
		</div>
	</div>
</div>
<?php endif; ?>
