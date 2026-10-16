<?php

/* @var $this yii\web\View */
/* @var $model common\models\Subscriber */

use common\models\Subscriber;
use common\models\EventLog;
use common\models\Subscription;
use common\models\Workspace;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;
use yii\widgets\DetailView;

$this->title = Yii::t('common', 'View {item}', ['item' => Yii::t('common', 'Subscriber')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Subscribers'),
		'url' => ['default/index'],
	],
	Yii::t('common', 'View'),
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewSubscriber'),
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
		'visible' => Yii::$app->user->can('updateSubscriber'),
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
		'visible' => Yii::$app->user->can('deleteSubscriber'),
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
		'visible' => Yii::$app->user->can('createSubscriber'),
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
				'label' => Yii::t('label', 'Code'),
				'value' => function (Subscriber $model) {
					return $model->code ? Html::tag('code', Html::encode($model->code)) : '&mdash;';
				},
			],
			[
				'format' => 'raw',
				'label' => Yii::t('label', 'Image'),
				'value' => function (Subscriber $model) {
					if ($model->user->image && is_file(Yii::getAlias("@uploads/user/{$model->user->id}/{$model->user->image}"))) {
						$imgTag = Html::img($model->user->getImageUrl(), [
							'class' => 'img-responsive',
							'alt' => $model->user->getFullName(),
						]);
						return Html::a($imgTag, $model->user->getImageUrl(), [
							'class' => 'gallery-thumbnail',
							'title' => Yii::t('common', 'Open Gallery'),
							'data' => [
								'toggle' => 'tooltip',
								'fancybox' => 'subscribers',
								'caption' => $model->user->getFullName(),
							],
						]);
					}
					return '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Name'),
				'value' => function (Subscriber $model) {
					return $model->user ? $model->user->getFullName() : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Email'),
				'value' => function (Subscriber $model) {
					return $model->user->email ? Html::a($model->user->email, "mailto:{$model->user->email}") : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Phone'),
				'value' => function (Subscriber $model) {
					return $model->user->phone ? Html::a($model->user->phone, "tel:{$model->user->phone}") : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Gender'),
				'value' => function (Subscriber $model) {
					return $model->user->gender ? Subscriber::getGenderLabels()[$model->user->gender] : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Date Of Birth'),
				'value' => function (Subscriber $model) {
					return $model->date_of_birth ? Yii::$app->formatter->asDate($model->date_of_birth) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'PIN'),
				'value' => function (Subscriber $model) {
					return $model->pin ? Html::encode($model->pin) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Address'),
				'value' => function (Subscriber $model) {
					return $model->getFullAddress() ?: '&mdash;';
				},
			],
            [
                'format' => 'html',
                'label' => Yii::t('label', 'Notes'),
                'value' => function (Subscriber $model) {
                    return $model->notes ? Html::encode($model->notes) : '&mdash;';
                },
            ],
			[
				'format' => 'raw',
				'label' => Yii::t('common', 'Subscriptions'),
				'contentOptions' => [
					'class' => 'p0',
				],
				'value' => function (Subscriber $model) {
					if ($model->subscriptions) {
						$content[] = Html::tag('div', implode('', [
							Html::tag('div', Yii::t('common', 'Code'), ['class' => 'th col-autowidth']),
							Html::tag('div', Yii::t('common', 'Package'), ['class' => 'th']),
							Html::tag('div', Yii::t('label', 'Start At'), ['class' => 'th']),
							Html::tag('div', Yii::t('label', 'End At'), ['class' => 'th']),
							Html::tag('div', Yii::t('label', 'Status'), ['class' => 'th col-autowidth']),
						]), ['class' => 'tr']);

						foreach ($model->subscriptions as $subscription) {
							$status = Subscription::getStatusLabels()[$subscription->status];
							$content[] = Html::tag('div', implode('', [
								Html::tag('div', $subscription->code ? Html::a(Html::tag('code', $subscription->code), ['/subscriber-manager/subscription/view', 'id' => $subscription->id]) : '&mdash;', ['class' => 'td col-autowidth']),
								Html::tag('div', $subscription->package ? Html::a($subscription->package->translation->name, ['/nomenclature-manager/package/view', 'id' => $subscription->package_id]) : '&mdash;', ['class' => 'td']),
								Html::tag('div', $subscription->start_at ? Yii::$app->formatter->asDatetime($subscription->start_at) : '&mdash;', ['class' => 'td']),
								Html::tag('div', $subscription->end_at ? Yii::$app->formatter->asDatetime($subscription->end_at) : '&mdash;', ['class' => 'td']),
								Html::tag('div', Html::tag('span', $status['label'], ['class' => 'label label-block label-' . $status['color']]), ['class' => 'td col-autowidth']),
							]), ['class' => 'tr']);
						}

						return Html::tag('div', implode('', $content), ['class' => 'table table-bordered table-nested']);
					}
					return Html::tag('div', '&mdash;', ['class' => 'p8']);
				},
			],
			[
				'format' => 'raw',
				'label' => Yii::t('common', 'Workspaces'),
				'contentOptions' => [
					'class' => 'p0',
				],
				'value' => function (Subscriber $model) {
					$hasWorkspaces = Workspace::find()->andWhere(['subscription_id' => array_column($model->subscriptions, 'id')])->exists();
					if ($hasWorkspaces && $model->subscriptions) {
						$content[] = Html::tag('div', implode('', [
							Html::tag('div', Yii::t('label', 'Code'), ['class' => 'th col-autowidth']),
							Html::tag('div', Yii::t('label', 'Url'), ['class' => 'th']),
							Html::tag('div', Yii::t('common', 'Subscription'), ['class' => 'th']),
							Html::tag('div', Yii::t('label', 'Created At'), ['class' => 'th']),
							Html::tag('div', Yii::t('label', 'Status'), ['class' => 'th col-autowidth']),
						]), ['class' => 'tr']);

						foreach ($model->subscriptions as $subscription) {
							foreach ($subscription->workspaces as $workspace) {
								$workspaceDb = $workspace->getWorkspaceDb();

								$status = Subscription::getStatusLabels()[$workspace->status];
								$content[] = Html::tag('div', implode('', [
									Html::tag('div', $workspace->code ? Html::a(Html::tag('code', $workspace->code), ['/workspace-manager/workspace/view', 'id' => $workspace->uuid]) : '&mdash;', ['class' => 'td col-autowidth']),
									Html::tag('div', $workspace->url ? Html::a($workspace->getAbsoluteUrl(), $workspace->getAbsoluteUrl(), ['target' => '_blank']) : '&mdash;', ['class' => 'td']),
									Html::tag('div', Html::a($subscription->formattedName, ['/subscriber-manager/subscription/view', 'id' => $subscription->id]), ['class' => 'td']),
									Html::tag('div', $subscription->created_at ? Yii::$app->formatter->asDatetime($subscription->created_at) : '&mdash;', ['class' => 'td']),
									Html::tag('div', Html::tag('span', $status['label'], ['class' => 'label label-block label-' . $status['color']]), ['class' => 'td col-autowidth']),
								]), ['class' => 'tr']);
							}
						}

						return Html::tag('div', implode('', $content), ['class' => 'table table-bordered table-nested']);
					}
					return Html::tag('div', '&mdash;', ['class' => 'p8']);
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('common', 'Companies'),
				'contentOptions' => [
					'class' => 'p0',
				],
				'value' => function (Subscriber $model) {
					if ($companies = $model->user->companies) {
						$content[] = Html::tag('div', implode('', [
							Html::tag('div', Yii::t('label', 'Name'), ['class' => 'th']),
							Html::tag('div', Yii::t('label', 'Taxpayer Identification Number'), ['class' => 'th']),
							Html::tag('div', Yii::t('label', 'Registration Number'), ['class' => 'th']),
							Html::tag('div', Yii::t('label', 'Created At'), ['class' => 'th']),
						]), ['class' => 'tr']);

						foreach ($companies as $company) {
							$content[] = Html::tag('div', implode('', [
								Html::tag('div', $company->name ?: '&mdash;', ['class' => 'td']),
								Html::tag('div', $company->tin ?: '&mdash;', ['class' => 'td']),
								Html::tag('div', $company->registration_number ?: '&mdash;', ['class' => 'td']),
								Html::tag('div', $company->created_at ? Yii::$app->formatter->asDatetime($company->created_at) : '&mdash;', ['class' => 'td']),
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
				'value' => function (Subscriber $model) {
					return $model->creator ? Html::a($model->creator->fullName, ['/user-manager/user/view', 'id' => $model->creator->id]) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Created At'),
				'value' => function (Subscriber $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Updated By'),
				'value' => function (Subscriber $model) {
					return $model->updater ? Html::a($model->updater->fullName, ['/user-manager/user/view', 'id' => $model->updater->id]) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Updated At'),
				'value' => function (Subscriber $model) {
					return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Status'),
				'value' => function (Subscriber $model) {
					$status = Subscriber::getStatusLabels()[$model->status];
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
							data.model = ' . json_encode(Subscriber::class) . '; 
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
