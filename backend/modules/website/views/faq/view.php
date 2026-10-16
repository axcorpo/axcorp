<?php
/* @var $this yii\web\View */
/* @var $model common\models\SurveyQuestion */

use common\models\Carousel;
use common\models\EventLog;
use common\models\SurveyQuestion;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\widgets\DetailView;

$this->title = Yii::t('common', 'View {item}', ['item' => Yii::t('common', 'FAQ')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Website'),
		'url' => ['default/index'],
	],
	[
		'label' => Yii::t('common', 'FAQs'),
		'url' => ['index'],
	],
	$this->title,
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewFaq'),
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
		'visible' => Yii::$app->user->can('updateFaq'),
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
		'visible' => Yii::$app->user->can('deleteFaq'),
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
		'visible' => Yii::$app->user->can('createFaq'),
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
			'format' => 'html',
			'label' => Yii::t('label', 'Question'),
			'value' => function (SurveyQuestion $model) {
				return $model->translation->content ?: '&mdash;';
			},
		],
		[
			'format' => 'html',
			'label' => Yii::t('label', 'Answer'),
			'value' => function (SurveyQuestion $model) {
				/** @var \common\models\SurveyAnswer[] $surveyAnswers */
				if ($surveyAnswers = $model->getSurveyAnswers()->active()->all()) {
					$content = $surveyAnswers[0]->translation->content;
					return $content ? Html::tag('div', $content, ['class' => 'small-preview-container']) : '&mdash;';
				}
				return '&mdash;';
			},
		],
		[
			'format' => 'html',
			'label' => Yii::t('label', 'Featured'),
			'value' => function (SurveyQuestion $model) {
				return Yii::$app->formatter->asBoolean($model->featured);
			},
		],
		[
			'format' => 'html',
			'label' => Yii::t('label', 'Sort Order'),
			'value' => function (SurveyQuestion $model) {
				return $model->sort_order ?: '&mdash;';
			},
		],
		[
			'format' => 'html',
			'label' => Yii::t('label', 'Created By'),
			'value' => function (SurveyQuestion $model) {
				return $model->creator ? Html::a($model->creator->fullName, ['/user-manager/user/view', 'id' => $model->creator->id]) : '&mdash;';
			},
		],
		[
			'format' => 'html',
			'label' => Yii::t('label', 'Created At'),
			'value' => function (SurveyQuestion $model) {
				return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
			},
		],
		[
			'format' => 'html',
			'label' => Yii::t('label', 'Updated By'),
			'value' => function (SurveyQuestion $model) {
				return $model->updater ? Html::a($model->updater->fullName, ['/user-manager/user/view', 'id' => $model->updater->id]) : '&mdash;';
			},
		],
		[
			'format' => 'html',
			'label' => Yii::t('label', 'Updated At'),
			'value' => function (SurveyQuestion $model) {
				return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
			},
		],
		[
			'format' => 'html',
			'label' => Yii::t('label', 'Status'),
			'value' => function (SurveyQuestion $model) {
				$status = Carousel::getStatusLabels()[$model->status];
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
							data.model = ' . json_encode(SurveyQuestion::class) . '; 
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
