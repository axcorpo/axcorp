<?php
/* @var $this yii\web\View */
/* @var $model common\models\SupportTicket */

use common\models\EventLog;
use common\models\SupportTicket;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;
use yii\widgets\DetailView;

$this->title = Yii::t('common', 'View {item}', ['item' => Yii::t('common', 'Support Ticket') . " #{$model->getDocumentSeriesNumber()}"]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Helpdesk'),
		'url' => ['default/index'],
	],
	[
		'label' => Yii::t('common', 'Support Tickets'),
		'url' => ['index'],
	],
	Yii::t('common', 'View {item}', ['item' => "#{$model->getDocumentSeriesNumber()}"]),
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewHelpdeskSupportTicket'),
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
		'visible' => Yii::$app->user->can('updateHelpdeskSupportTicket'),
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
		'visible' => Yii::$app->user->can('deleteHelpdeskSupportTicket'),
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
		'visible' => Yii::$app->user->can('createHelpdeskSupportTicket'),
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
				'label' => '#',
				'value' => function (SupportTicket $model) {
					return $model->getDocumentSeriesNumber() ?: '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Department'),
				'value' => function (SupportTicket $model) {
					if ($model->supportTicketDepartment) {
						return Html::a($model->supportTicketDepartment->translation->name, ['/helpdesk-manager/support-ticket-department/view', 'id' => $model->support_ticket_department_id]);
					}
					return '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Priority'),
				'value' => function (SupportTicket $model) {
					if ($model->supportTicketPriority) {
						return Html::a($model->supportTicketPriority->translation->name, ['/helpdesk-manager/support-ticket-priority/view', 'id' => $model->support_ticket_priority_id]);
					}
					return '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Subscription'),
				'value' => function (SupportTicket $model) {
					if ($subscription = $model->subscription) {
						return Html::a($subscription->formattedName, ['/subscriber-manager/subscription/view', 'id' => $subscription->id]);
					}
					return '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Subject'),
				'value' => function (SupportTicket $model) {
					return $model->subject ? Html::encode($model->subject) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('common', 'Messages'),
				'contentOptions' => [
					'class' => 'p0',
				],
				'value' => function (SupportTicket $model) {
					$content[] = Html::tag('div', implode('', [
						Html::tag('div', Yii::t('label', 'Created At'), ['class' => 'th col-autowidth']),
						Html::tag('div', Yii::t('label', 'Created By'), ['class' => 'th col-autowidth']),
						Html::tag('div', Yii::t('label', 'Message'), ['class' => 'th']),
					]), ['class' => 'tr']);

					$supportTicketComments = array_reverse($model->supportTicketComments);
					foreach ($supportTicketComments as $supportTicketComment) {
						$content[] = Html::tag('div', implode('', [
							Html::tag('div', $supportTicketComment->created_at ? Yii::$app->formatter->asDatetime($supportTicketComment->created_at) : '&mdash;', ['class' => 'td col-autowidth']),
							Html::tag('div', $supportTicketComment->creator ? Html::a($supportTicketComment->creator->getFullName(), ['/user-manager/user/view', 'id' => $supportTicketComment->created_by]) : '&mdash;', ['class' => 'td col-autowidth']),
							Html::tag('div', $supportTicketComment->content ?: '&mdash;', ['class' => 'td']),
						]), ['class' => 'tr']);
					}

					$content[] = Html::tag('div', implode('', [
						Html::tag('div', $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;', ['class' => 'td col-autowidth']),
						Html::tag('div', $model->creator ? Html::a($model->creator->fullName, ['/user-manager/user/view', 'id' => $model->created_by]) : '&mdash;', ['class' => 'td col-autowidth']),
						Html::tag('div', $model->content ?: '&mdash;', ['class' => 'td']),
					]), ['class' => 'tr']);

					return Html::tag('div', implode('', $content), ['class' => 'table table-bordered table-nested']);
				},
			],
			[
				'format' => 'raw',
				'label' => Yii::t('label', 'Attachments'),
				'contentOptions' => [
					'class' => 'p0',
				],
				'value' => function (SupportTicket $model) {
					if ($attachments = $model->getAttachments(true, true)) {
						$content = [];

						foreach ($attachments as $attachment) {
							$content[] = Html::tag('div', implode('', [
								Html::tag('div', Html::a($attachment['name'], $attachment['url'], ['download' => $attachment['name']]), ['class' => 'td col-autowidth']),
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
				'value' => function (SupportTicket $model) {
					return $model->creator ? Html::a($model->creator->fullName, ['/user-manager/user/view', 'id' => $model->creator->id]) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Created At'),
				'value' => function (SupportTicket $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Updated By'),
				'value' => function (SupportTicket $model) {
					return $model->updater ? Html::a($model->updater->fullName, ['/user-manager/user/view', 'id' => $model->updater->id]) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Updated At'),
				'value' => function (SupportTicket $model) {
					return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
				},
			],
			[
				'format' => 'html',
				'label' => Yii::t('label', 'Status'),
				'value' => function (SupportTicket $model) {
					if ($model->supportTicketStatus) {
						return Html::a($model->supportTicketStatus->getFormattedName(), ['/helpdesk-manager/support-ticket-status/view', 'id' => $model->support_ticket_status_id]);
					}
					return '&mdash;';
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
							data.model = ' . json_encode(SupportTicket::class) . '; 
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
