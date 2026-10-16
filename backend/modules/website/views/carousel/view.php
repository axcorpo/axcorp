<?php

/* @var $this yii\web\View */
/* @var $model common\models\Carousel */

use common\models\Carousel;
use common\models\CarouselItem;
use common\models\EventLog;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;
use yii\widgets\DetailView;

$this->title = Yii::t('common', 'View {item}', ['item' => Yii::t('common', 'Carousel')]);
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
		'visible' => Yii::$app->user->can('updateCarousel'),
		'tag' => 'a',
		'url' => ['update', 'id' => $model->id],
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
		'visible' => Yii::$app->user->can('deleteCarousel') && !$model->default,
		'tag' => 'a',
		'url' => ['delete', 'id' => $model->id],
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

$showEventLogs = isset($showEventLogs) ? $showEventLogs : Yii::$app->eventLog->enabled;
?>

<div class="table-responsive">
	<?= DetailView::widget([
		'model' => $model,
		'options' => [
			'class' => 'table table-striped table-bordered detail-view detail-view-fixed',
		],
		'attributes' => [
			[
				'format' => 'html',
				'label' => Yii::t('label', 'ID'),
				'value' => function (Carousel $model) {
					return $model->id ?: '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Name'),
				'value' => function (Carousel $model) {
					return $model->translation->name ? Html::encode($model->translation->name) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Type'),
				'value' => function (Carousel $model) {
					return Carousel::getTypeLabels()[$model->type] ?: '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Default'),
				'value' => function (Carousel $model) {
					return Yii::$app->formatter->asBoolean($model->default);
				},
			],
			[
				'format' => 'raw',
				'label' => Yii::t('common', 'Slides'),
				'contentOptions' => [
					'class' => 'p0',
				],
				'value' => function (Carousel $model) {
					if ($carouselItems = $model->carouselItems) {
						$content[] = Html::tag('div', implode('', [
							Html::tag('div', '#', ['class' => 'th col-autowidth']),
							Html::tag('div', Yii::t('label', 'Image'), ['class' => 'th col-autowidth']),
							Html::tag('div', Yii::t('label', 'Title'), ['class' => 'th']),
							Html::tag('div', Yii::t('label', 'Created At'), ['class' => 'th']),
							Html::tag('div', Yii::t('label', 'Created By'), ['class' => 'th']),
							Html::tag('div', Yii::t('label', 'Status'), ['class' => 'th col-autowidth']),
						]), ['class' => 'tr']);

						usort($carouselItems, function ($a, $b) {
							return strcmp($a->sort_order, $b->sort_order);
						});
						foreach ($carouselItems as $carouselItem) {
							$carouselItemTranslation = $carouselItem->getTranslation();
							$imgTag = null;
							if ($carouselItem->image) {
								$imgTag = Html::img($carouselItem->getImageUrl(), [
									'class' => 'img-responsive',
									'alt' => $carouselItemTranslation->title,
								]);
								$imgTag = Html::a($imgTag, $carouselItem->getImageUrl(), [
									'class' => 'gallery-thumbnail',
									'title' => Yii::t('common', 'Open Gallery'),
									'data' => [
										'toggle' => 'tooltip',
										'fancybox' => 'carousel-items',
										'caption' => $carouselItemTranslation->title,
									],
								]);
							}
							$status = CarouselItem::getStatusLabels()[$model->status];
							$status = Html::tag('span', $status['label'], ['class' => 'label label-block label-' . $status['color']]);

							$content[] = Html::tag('div', implode('', [
								Html::tag('div', $carouselItem->sort_order ?: '&mdash;', ['class' => 'td col-autowidth']),
								Html::tag('div', $imgTag ?: '&mdash;', ['class' => 'td col-autowidth']),
								Html::tag('div', $carouselItemTranslation->title ?: '&mdash;', ['class' => 'td']),
								Html::tag('div', $carouselItem->created_at ? Yii::$app->formatter->asDatetime($carouselItem->created_at) : '&mdash;', ['class' => 'td']),
								Html::tag('div', $carouselItem->creator ? Html::a($carouselItem->creator->getFullName(), ['/user-manager/user/view', 'id' => $carouselItem->created_by]) : '&mdash;', ['class' => 'td']),
								Html::tag('div', $status, ['class' => 'td col-autowidth']),
							]), ['class' => 'tr']);
						}
						return Html::tag('div', implode('', $content), ['class' => 'table table-bordered table-nested']);
					}
					return Html::tag('div', '&mdash;', ['class' => 'p8']);
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Created By'),
				'value' => function (Carousel $model) {
					return $model->creator ? Html::a($model->creator->fullName, ['/user-manager/user/view', 'id' => $model->creator->id]) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Created At'),
				'value' => function (Carousel $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Updated By'),
				'value' => function (Carousel $model) {
					return $model->updater ? Html::a($model->updater->fullName, ['/user-manager/user/view', 'id' => $model->updater->id]) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Updated At'),
				'value' => function (Carousel $model) {
					return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Status'),
				'value' => function (Carousel $model) {
					$status = Carousel::getStatusLabels()[$model->status];
					return Html::tag('span', $status['label'], ['class' => 'label label-' . $status['color']]);
				},
			],
		],
	]) ?>
</div>

<?php if ((!isset($showEventLogs) || $showEventLogs === true) && Yii::$app->user->can('viewEventLog')) : ?>
	<div class="portlet box blue-hoki mt-15">
		<div class="portlet-title">
			<div class="caption"><?= Yii::t('common', 'Event Logs') ?></div>
		</div>
		<div class="portlet-body">
			<?= DataTable::widget([
				'id' => 'dt-logs',
				'options' => [
					'class' => 'table table-bordered table-hover',
				],
				'showColumnFilters' => true,
				'clientOptions' => [
					'deferRender' => true,
					'processing' => true,
					'serverSide' => true,
					'ajax' => [
						'url' => Url::to(['/eventlog-manager/event-log/dt-event-logs']),
						'method' => 'POST',
						'data' => new JsExpression('function (data) {
							data.model = ' . json_encode(Carousel::class) . '; 
							data.model_key = "' . $model->uuid . '";
						}'),
						'reloadInterval' => 5 * 60000,
					],
					'order' => [
						[3, 'desc'],
					],
					'pageLength' => Yii::$app->settings->get('itemsPerPage'),
					'lengthMenu' => [
						'autoCreate' => true,
						'displayAll' => Yii::t('common', 'All'),
					],
					'autoWidth' => false,
					'responsive' => true,
					'columns' => [
						[
							'class' => 'common\widgets\datatable\ActionColumn',
							'title' => Yii::t('common', 'Action'),
							'buttons' => [
								'event-log' => [
									'visible' => Yii::$app->user->can('viewEventLog'),
									'url' => ['/eventlog-manager/event-log/view', 'id' => new JsExpression('id')],
									'content' => '<span class="fa fa-eye"></span>',
									'options' => [
										'class' => 'action-view btn btn-xs btn-info',
										'title' => Yii::t('common', 'View'),
										'data' => [
											'toggle' => 'tooltip',
											'popup-action' => '',
										],
									],
								],
							],
						],
						[
							'data' => 'user',
							'title' => Yii::t('label', 'User'),
							'filter' => ['text'],
						],
						[
							'data' => 'operation',
							'title' => Yii::t('label', 'Operation'),
							'filter' => ['select', ArrayHelper::getColumn(EventLog::getActions(), 'label')],
						],
						[
							'data' => 'date',
							'title' => Yii::t('label', 'Date'),
							'filter' => ['date', 'icu:' . Yii::$app->settings->get('dateFormat')],
						],
						[
							'data' => 'ip_address',
							'title' => Yii::t('label', 'IP Address'),
							'filter' => ['text'],
						],
					],
				],
			]) ?>
		</div>
	</div>
<?php endif; ?>
