<?php

/* @var $this yii\web\View */
/* @var $model common\models\User */

use common\models\User;
use common\models\EventLog;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;
use yii\widgets\DetailView;

$this->title = Yii::t('common', 'View {item}', ['item' => Yii::t('common', 'Operator User')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Operator Users'),
		'url' => ['index'],
	],
	Yii::t('common', 'View'),
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewUser'),
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
		'visible' => Yii::$app->user->can('updateUser'),
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
		'visible' => Yii::$app->user->can('deleteUser'),
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
		'visible' => Yii::$app->user->can('createUser'),
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

<div class="table-responsive mb-10">
	<?= DetailView::widget([
		'model' => $model,
		'options' => [
			'class' => 'table table-striped table-bordered detail-view detail-view-fixed',
		],
		'attributes' => [
			[
				'format' => 'raw',
				'label' => Yii::t('label', 'Image'),
				'value' => function (User $model) {
					if ($model->image && is_file(Yii::getAlias("@uploads/user/{$model->uuid}/{$model->image}"))) {
						$imgTag = Html::img($model->getImageUrl(), [
							'class' => 'img-responsive',
							'alt' => $model->getFullName(),
						]);
						return Html::a($imgTag, $model->getImageUrl(), [
							'class' => 'gallery-thumbnail',
							'title' => Yii::t('common', 'Open Gallery'),
							'data' => [
								'toggle' => 'tooltip',
								'fancybox' => 'users',
								'caption' => $model->getFullName(),
							],
						]);
					}
					return '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Name'),
				'value' => function (User $model) {
					$content = [];
					$content[] = $model->getFullName() ?: '&mdash;';
					if ($token = \common\models\AssumeIdentityForm::generateToken($model->uuid)) {
						$content[] = '<span class="fa fa-user-secret"></span> <small>' . $token . '</small>';
					}
					return implode('<br>', $content);
				},
			],
			[
				'format' => 'raw',
				'label' => Yii::t('label', 'Email'),
				'value' => function (User $model) {
					return $model->email ? Html::a($model->email, "mailto:{$model->email}") : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Phone'),
				'value' => function (User $model) {
					return $model->phone ? Html::a($model->phone, "tel:{$model->phone}") : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Gender'),
				'value' => function (User $model) {
					return $model->gender ? User::getGenderLabels()[$model->gender] : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Role'),
				'value' => function (User $model) {
					return $model->authAssignment->item_name ? Html::a($model->authAssignment->itemName->description, ['/user-manager/role/view', 'id' => $model->authAssignment->item_name]) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Last Activity'),
				'value' => function (User $model) {
					return $model->last_activity ? Yii::$app->formatter->asDatetime($model->last_activity) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Created By'),
				'value' => function (User $model) {
					return $model->creator ? Html::a($model->creator->fullName, ['/user-manager/user/view', 'id' => $model->creator->id]) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Created At'),
				'value' => function (User $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Updated By'),
				'value' => function (User $model) {
					return $model->updater ? Html::a($model->updater->fullName, ['/user-manager/user/view', 'id' => $model->updater->id]) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Updated At'),
				'value' => function (User $model) {
					return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Status'),
				'value' => function (User $model) {
					$status = User::getStatusLabels()[$model->status];
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
			<div class="tools">
				<?= Html::a(null, 'javascript:void(0);', [
					'class' => 'collapse',
					'title' => Yii::t('label', 'Expand') . '/' . Yii::t('common', 'Collapse'),
				]) ?>
			</div>
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
							data.model = ' . json_encode(User::class) . '; 
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

	<div class="portlet box blue-hoki mt-15">
		<div class="portlet-title">
			<div class="caption"><?= Yii::t('common', 'Event Logs') . ' - ' . $model->fullName ?></div>
			<div class="tools">
				<?= Html::a(null, 'javascript:void(0);', [
					'class' => 'collapse',
					'title' => Yii::t('common', 'Expand') . '/' . Yii::t('common', 'Collapse'),
				]) ?>
			</div>
		</div>
		<div class="portlet-body">
			<?= DataTable::widget([
				'id' => 'dt-user-logs',
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
							data.user_id = "' . $model->uuid . '";
						}'),
						'reloadInterval' => 5 * 60000,
					],
					'order' => [
						[4, 'desc'],
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
							'data' => 'operation',
							'title' => Yii::t('label', 'Operation'),
							'filter' => ['select', ArrayHelper::getColumn(EventLog::getActions(), 'label')],
						],
						[
							'data' => 'resource',
							'title' => Yii::t('label', 'Resource'),
							'filter' => ['select', ArrayHelper::map(EventLog::findDistinctResources(), 'resource', 'translatedResource')],
						],
						[
							'data' => 'model_key',
							'title' => Yii::t('label', 'Resource ID'),
							'filter' => ['text'],
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
