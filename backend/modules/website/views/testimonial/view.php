<?php

/* @var $this yii\web\View */
/* @var $model common\models\Testimonial */

use common\helpers\HtmlSanitizer;
use common\models\EventLog;
use common\models\Testimonial;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;
use yii\widgets\DetailView;

$this->title = Yii::t('common', 'View {item}', ['item' => Yii::t('common', 'Testimonial')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Website'),
		'url' => ['default/index'],
	],
	[
		'label' => Yii::t('common', 'Testimonials'),
		'url' => ['index'],
	],
	$this->title,
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewTestimonial'),
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
		'visible' => Yii::$app->user->can('updateTestimonial'),
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
		'visible' => Yii::$app->user->can('deleteTestimonial'),
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
		'visible' => Yii::$app->user->can('createTestimonial'),
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

<?= DetailView::widget([
	'model' => $model,
	'options' => [
		'class' => 'table table-striped table-bordered detail-view detail-view-fixed',
	],
	'attributes' => [
		[
			'format' => 'raw',
			'label' => Yii::t('label', 'Image'),
			'value' => function (Testimonial $model) {
				if ($model->image && is_file(Yii::getAlias("@uploads/testimonial/{$model->uuid}/{$model->image}"))) {
					$imgTag = Html::img($model->getImageUrl(), [
						'class' => 'img-responsive',
						'alt' => $model->name,
					]);
					return Html::a($imgTag, $model->getImageUrl(), [
						'class' => 'gallery-thumbnail',
						'title' => Yii::t('common', 'Open Gallery'),
						'data' => [
							'toggle' => 'tooltip',
							'fancybox' => 'testimonials',
							'caption' => $model->name,
						],
					]);
				}
				return '&mdash;';
			},
		],
		[
			'format' => 'html',
			'label' => Yii::t('label', 'Name'),
			'value' => function (Testimonial $model) {
				return $model->name ? Html::encode($model->name) : '&mdash;';
			},
		],
		[
			'format' => 'html',
			'label' => Yii::t('label', 'Role'),
			'value' => function (Testimonial $model) {
				return $model->translation->role ? Html::encode($model->translation->role) : '&mdash;';
			},
		],
		[
			'format' => 'html',
			'label' => Yii::t('label', 'Organization'),
			'value' => function (Testimonial $model) {
				return $model->organization ? Html::encode($model->organization) : '&mdash;';
			},
		],
		[
			'format' => 'raw',
			'label' => Yii::t('label', 'Rating'),
			'value' => function (Testimonial $model) {
				return \kartik\rating\StarRating::widget([
					'id' => 'rating-' . rand(),
					'name' => '',
					'value' => $model->rating ?: 0,
					'pluginOptions' => [
						'displayOnly' => true,
						'showCaption' => false,
						'showClear' => false,
						'size' => 'xxs',
						'min' => 0,
						'max' => 5,
						'stars' => 5,
						'step' => 1,
					],
				]);
			},
		],
		[
			'format' => 'raw',
			'label' => Yii::t('label', 'Message'),
			'value' => function (Testimonial $model) {
				return $model->translation->message ? Html::tag('div', HtmlSanitizer::richText($model->translation->message), ['class' => 'small-preview-container']) : '&mdash;';
			},
		],
		[
			'format' => 'html',
			'label' => Yii::t('label', 'IP Address'),
			'value' => function (Testimonial $model) {
				return $model->ip_address ? Html::encode($model->ip_address) : '&mdash;';
			},
		],
		[
			'format' => 'html',
			'label' => Yii::t('label', 'Created By'),
			'value' => function (Testimonial $model) {
				return $model->creator ? Html::a($model->creator->fullName, ['/user-manager/user/view', 'id' => $model->creator->id]) : '&mdash;';
			},
		],
		[
			'format' => 'html',
			'label' => Yii::t('label', 'Created At'),
			'value' => function (Testimonial $model) {
				return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
			},
		],
		[
			'format' => 'html',
			'label' => Yii::t('label', 'Updated By'),
			'value' => function (Testimonial $model) {
				return $model->updater ? Html::a($model->updater->fullName, ['/user-manager/user/view', 'id' => $model->updater->id]) : '&mdash;';
			},
		],
		[
			'format' => 'html',
			'label' => Yii::t('label', 'Updated At'),
			'value' => function (Testimonial $model) {
				return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
			},
		],
		[
			'format' => 'html',
			'label' => Yii::t('label', 'Status'),
			'value' => function (Testimonial $model) {
				$status = Testimonial::getStatusLabels()[$model->status];
				return Html::tag('span', $status['label'], ['class' => 'label label-' . $status['color']]);
			},
		],
	],
]) ?>

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
							data.model = ' . json_encode(Testimonial::class) . '; 
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
