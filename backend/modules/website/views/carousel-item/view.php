<?php

/* @var $this yii\web\View */
/* @var $model common\models\CarouselItem */

use common\helpers\HtmlSanitizer;
use common\models\Carousel;
use common\models\CarouselItem;
use yii\helpers\Html;
use yii\widgets\DetailView;

$this->title = Yii::t('common', 'View {item}', ['item' => Yii::t('common', 'Carousel Item')]);
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
		'visible' => Yii::$app->user->can('updateCarousel'),
		'tag' => 'a',
		'url' => ['update', 'carousel_id' => Yii::$app->request->get('carousel_id'), 'id' => $model->id],
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
		'visible' => Yii::$app->user->can('deleteCarousel'),
		'tag' => 'a',
		'url' => ['delete', 'carousel_id' => Yii::$app->request->get('carousel_id'), 'id' => $model->id],
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
		'visible' => Yii::$app->user->can('deleteCarousel'),
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
							'format' => 'raw',
							'label' => Yii::t('label', 'Image'),
							'value' => function (CarouselItem $model) {
								if ($model->image) {
									$imgTag = Html::img($model->getImageUrl(), [
										'class' => 'img-responsive',
										'alt' => $model->translation->title,
									]);
									return Html::a($imgTag, $model->getImageUrl(), [
										'class' => 'gallery-thumbnail',
										'title' => Yii::t('common', 'Open Gallery'),
										'data' => [
											'toggle' => 'tooltip',
											'fancybox' => 'articles',
											'caption' => $model->translation->title,
										],
									]);
								}
								return '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Title'),
							'value' => function (CarouselItem $model) {
								return $model->translation->title ? Html::encode($model->translation->title) : '&mdash;';
							},
						],
//						[
//							'format' => 'html',
//							'label' => Yii::t('label', 'URL'),
//							'value' => function (CarouselItem $model) {
//								return $model->translation->url ?: '&mdash;';
//							},
//						],
//						[
//							'format' => 'html',
//							'label' => Yii::t('label', 'Target'),
//							'value' => function (CarouselItem $model) {
//								return $model->target ?: '&mdash;';
//							},
//						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Content'),
							'value' => function (CarouselItem $model) {
								return $model->translation->content ? Html::tag('div', HtmlSanitizer::richText($model->translation->content), ['class' => 'small-preview-container']) : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Sort Order'),
							'value' => function (CarouselItem $model) {
								return $model->sort_order ?: '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Created By'),
							'value' => function (CarouselItem $model) {
								return $model->creator ? Html::a($model->creator->fullName, ['/user-manager/user/view', 'id' => $model->creator->id]) : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Created At'),
							'value' => function (CarouselItem $model) {
								return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Updated By'),
							'value' => function (CarouselItem $model) {
								return $model->updater ? Html::a($model->updater->fullName, ['/user-manager/user/view', 'id' => $model->updater->id]) : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Updated At'),
							'value' => function (CarouselItem $model) {
								return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Status'),
							'value' => function (CarouselItem $model) {
								$status = Carousel::getStatusLabels()[$model->status];
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
