<?php

namespace backend\modules\helpdesk\models;

use common\helpers\DateHelper;
use common\models\PackageTranslation;
use common\models\Subscription;
use common\models\SupportTicket;
use common\models\SupportTicketDepartmentTranslation;
use common\models\SupportTicketPriorityTranslation;
use common\models\SupportTicketStatusTranslation;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class SupportTicketSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = SupportTicket::find()
			->alias('st')
			->select([
				'st.id',
				'st.user_id',
				'st.support_ticket_department_id',
				'st.support_ticket_priority_id',
				'st.support_ticket_status_id',
				'st.subscription_id',
				'st.series',
				'st.number',
				'st.subject',
				'st.seen',
				'st.created_by',
				'st.updated_by',
				'st.created_at',
				'st.updated_at',
			])
			->joinWith([
				'supportTicketDepartment.supportTicketDepartmentTranslations stdt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'stdt.language_id' => Yii::$app->language,
						'stdt.deleted' => SupportTicketDepartmentTranslation::NO,
					]);
				},
				'supportTicketPriority.supportTicketPriorityTranslations stpt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'stpt.language_id' => Yii::$app->language,
						'stpt.deleted' => SupportTicketPriorityTranslation::NO,
					]);
				},
				'supportTicketStatus.supportTicketStatusTranslations stst' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'stst.language_id' => Yii::$app->language,
						'stst.deleted' => SupportTicketStatusTranslation::NO,
					]);
				},
				'subscription sub',
				'subscription.package.packageTranslations pt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'pt.language_id' => Yii::$app->language,
						'pt.deleted' => PackageTranslation::NO,
					]);
				},
				'creator cr' => function (ActiveQuery $query) {
					$query->select([
						'cr.id',
						'cr.first_name',
						'cr.middle_name',
						'cr.last_name',
					]);
				},
			])
			->andWhere([
				'st.deleted' => isset($this->requestParams['deleted']) ? $this->requestParams['deleted'] : SupportTicket::NO,
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			SupportTicket::class => [
				'id',
				'action' => function (SupportTicket $model) {
					$actions = [];

					if ($this->requestParams['deleted'] == SupportTicket::YES) {
						if (Yii::$app->user->can('restoreHelpdeskSupportTicket')) {
							$actions[] = Html::a('<span class="fa fa-undo"></span>', ['restore', 'id' => $model->id], [
								'class' => 'action-view btn btn-xs btn-success',
								'title' => Yii::t('common', 'Restore'),
								'data' => [
									'toggle' => 'tooltip',
									'dt-operation' => 'restore',
									'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							]);
						}
						if (Yii::$app->user->can('deleteHelpdeskSupportTicket')) {
							$actions[] = Html::a('<span class="fa fa-trash"></span>', ['delete', 'id' => $model->id], [
								'class' => 'action-delete btn btn-xs btn-danger',
								'title' => Yii::t('common', 'Delete Permanently'),
								'data' => [
									'toggle' => 'tooltip',
									'dt-operation' => 'delete-permanently',
									'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							]);
						}
					} else {
						if (Yii::$app->user->can('viewHelpdeskSupportTicket')) {
							$actions[] = Html::a('<span class="fa fa-eye"></span>', ['view', 'id' => $model->id], [
								'class' => 'action-view btn btn-xs btn-info',
								'title' => Yii::t('common', 'View'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('updateHelpdeskSupportTicket')) {
							$actions[] = Html::a('<span class="fa fa-edit"></span>', ['update', 'id' => $model->id], [
								'class' => 'action-update btn btn-xs btn-primary',
								'title' => Yii::t('common', 'Update'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('deleteHelpdeskSupportTicket')) {
							$actions[] = Html::a('<span class="fa fa-trash"></span>', ['delete', 'id' => $model->id], [
								'class' => 'action-delete btn btn-xs btn-danger',
								'title' => Yii::t('common', 'Delete'),
								'data' => [
									'toggle' => 'tooltip',
									'dt-operation' => 'delete',
									'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							]);
						}
					}

					$actions = array_map(function ($actionsChunk) {
						return Html::tag('div', implode('', $actionsChunk));
					}, array_chunk($actions, 3));

					return implode('', $actions);
				},
				'number' => function (SupportTicket $model) {
					return $model->getDocumentSeriesNumber() ?: '&mdash;';
				},
				'department' => function (SupportTicket $model) {
					if ($model->supportTicketDepartment) {
						return $model->supportTicketDepartment->translation->name ?: '&mdash;';
					}
					return '&mdash;';
				},
				'priority' => function (SupportTicket $model) {
					if ($model->supportTicketPriority) {
						return $model->supportTicketPriority->translation->name ?: '&mdash;';
					}
					return '&mdash;';
				},
				'subscription' => function (SupportTicket $model) {
					if ($model->subscription) {
						$content = [];
						$status = Subscription::getStatusLabels()[$model->subscription->status];
						$status = Html::tag('span', $status['label'], ['class' => 'text-' . $status['color']]);
						$startAt = $model->subscription->start_at ? Yii::$app->formatter->asDatetime($model->subscription->start_at) : '&mdash;';
						$endAt = $model->subscription->end_at ? Yii::$app->formatter->asDatetime($model->subscription->end_at) : '&mdash;';
						$featuresIcon = '';
						if (!empty($model->subscription->parent_id)) {
							$featuresIcon = Html::tag('span', null, [
								'class' => 'fa fa-share-alt',
								'title' => Yii::t('common', 'Features'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}

						$content[] = Html::beginTag('div', ['class' => 'item-adjacent-spacing bordered-top']);
						$content[] = Html::tag('div', "{$model->subscription->formattedName} $featuresIcon &mdash; {$status}");
						if ($model->subscription->type == Subscription::TYPE_FREE) {
							$content[] = Html::tag('div', Yii::t('label', 'Start At') . ": {$startAt}");
						} else {
							$content[] = Html::tag('div', Yii::t('label', 'Next Due At'). ": {$endAt}");
						}
						$content[] = Html::endTag('div');
						return implode('', $content);
					}
					return '&mdash;';
				},
				'subject' => function (SupportTicket $model) {
					return $model->subject ?: '&mdash;';
				},
				'seen' => function (SupportTicket $model) {
					return Yii::$app->formatter->asBoolean($model->seen);
				},
				'created_by' => function (SupportTicket $model) {
					return $model->creator ? $model->creator->fullName : '&mdash;';
				},
				'created_at' => function (SupportTicket $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
				'updated_at' => function (SupportTicket $model) {
					return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
				},
				'status' => function (SupportTicket $model) {
					if ($model->supportTicketStatus) {
						return $model->supportTicketStatus->getFormattedName() ?: '&mdash;';
					}
					return '&mdash;';
				},
			],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function applyFilter(ActiveQuery $query, $columns, $search)
	{
		/** @var \yii\db\ActiveRecord $modelClass */
		$modelClass = $query->modelClass;
		$schema = $modelClass::getTableSchema()->columns;

		foreach ($columns as $column) {
			if ($column['searchable'] == 'false') {
				continue;
			}
			if (!empty($search['value'])) {
				$value = trim($search['value']);
				$filterOperator = 'orFilterWhere';
			} else {
				$value = trim($column['search']['value']);
				$filterOperator = 'andFilterWhere';
			}

			switch ($column['data']) {
				case 'number':
					$query->$filterOperator([
						'OR',
						['LIKE', 'st.series', $value],
						['LIKE', 'st.number', $value],
						['LIKE', new Expression('CONCAT_WS(" ", [[st.series]], LPAD([[st.number]], 5, 0))'), $value],
					]);
					break;
				case 'department':
					$query->$filterOperator(['LIKE', 'stdt.name', $value]);
					break;
				case 'priority':
					$query->$filterOperator(['LIKE', 'stpt.name', $value]);
					break;
				case 'status':
					$query->$filterOperator(['LIKE', 'stst.name', $value]);
					break;
				case 'subscription':
					$query->$filterOperator([
						'OR',
						['LIKE', 'sub.code', $value],
						['LIKE', 'pt.name', $value],
					]);
					break;
				case 'created_by':
					$query->$filterOperator([
						'OR',
						['LIKE', 'cr.first_name', $value],
						['LIKE', 'cr.middle_name', $value],
						['LIKE', 'cr.last_name', $value],
					]);
					break;
				case 'created_at':
					$query->$filterOperator(['LIKE', 'st.created_at', DateHelper::formatAsDate($value)]);
					break;
				case 'updated_at':
					$query->$filterOperator(['LIKE', 'st.updated_at', DateHelper::formatAsDate($value)]);
					break;
				default:
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 'st.' . $column['data'], $value]);
					}
					break;
			}
		}
		return $query;
	}

	/**
	 * @inheritdoc
	 */
	public function applyOrder(ActiveQuery $query, $columns, $order)
	{
		foreach ($order as $key => $item) {
			$column = $columns[$item['column']];
			if (array_key_exists('orderable', $column) && $column['orderable'] === 'false') {
				continue;
			}
			$sort = mb_strtolower($item['dir']) == 'desc' ? SORT_DESC : SORT_ASC;

			switch ($column['data']) {
				case 'number':
					$query->addOrderBy([
						'st.series' => $sort,
						'st.number' => $sort,
					]);
					break;
				case 'department':
					$query->addOrderBy(['stdt.name' => $sort]);
					break;
				case 'priority':
					$query->addOrderBy(['stpt.name' => $sort]);
					break;
				case 'status':
					$query->addOrderBy(['stst.name' => $sort]);
					break;
				case 'subscription':
					$query->addOrderBy(['pt.name' => $sort]);
					break;
				case 'created_by':
					$query->addOrderBy([
						'cr.first_name' => $sort,
						'cr.middle_name' => $sort,
						'cr.last_name' => $sort,
					]);
					break;
				default:
					$query->addOrderBy(['st.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
