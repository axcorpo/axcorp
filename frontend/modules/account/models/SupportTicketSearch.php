<?php

namespace frontend\modules\account\models;

use common\helpers\DateHelper;
use common\models\PackageTranslation;
use common\models\SupportTicket;
use common\models\SupportTicketDepartmentTranslation;
use common\models\SupportTicketPriorityTranslation;
use common\models\SupportTicketStatusTranslation;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\bootstrap\Dropdown;
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
				'st.support_ticket_department_id',
				'st.support_ticket_priority_id',
				'st.support_ticket_status_id',
				'st.subscription_id',
				'st.series',
				'st.number',
				'st.subject',
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
				'subscription.package.packageTranslations pt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'pt.language_id' => Yii::$app->language,
						'pt.deleted' => PackageTranslation::NO,
					]);
				},
			])
			->andWhere([
				'st.created_by' => Yii::$app->user->id,
				'st.deleted' => SupportTicket::NO,
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

					$actions[] = [
						'label' => '<span class="action-icon fa fa-eye color-info"></span> ' . Yii::t('common', 'View'),
						'url' => ['support-ticket/view', 'id' => $model->id],
						'linkOptions' => [
							'data' => [
								'popup-action' => '',
							],
						],
					];
					$actions[] = [
						'label' => '<span class="action-icon fa fa-edit color-primary"></span> ' . Yii::t('common', 'Update'),
						'url' => ['support-ticket/update', 'id' => $model->id],
						'linkOptions' => [
							'data' => [
								'popup-action' => '',
								'popup-done' => ['redrawDataTable' => '#dt-support-tickets'],
							],
						],
					];

					$content = [];
					$content[] = Html::beginTag('div', ['class' => 'dropdown']);
					$content[] = Html::tag('button', '<span class="fa fa-ellipsis-v"></span>', [
						'class' => 'dropdown-toggle btn btn-block btn-xs btn-light btn-slide-right',
						'data' => [
							'toggle' => 'dropdown',
							// Bootstrap 5 listens on data-bs-toggle; the legacy attribute stays
							// for the styles and scripts that still key on it.
							'bs-toggle' => 'dropdown',
						],
					]);
					$content[] = Dropdown::widget(['items' => $actions, 'encodeLabels' => false]);
					$content[] = Html::endTag('div');

					return $actions ? implode('', $content) : '&mdash;';
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
					if ($subscription = $model->subscription) {
						return Html::a($subscription->formattedName, ['/account/subscription/view', 'id' => $subscription->id], [
							'data' => [
								'popup-action' => '',
							],
						]);
					}
					return '&mdash;';
				},
				'subject' => function (SupportTicket $model) {
					return $model->subject ?: '&mdash;';
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
					$query->$filterOperator(['LIKE', 'pt.name', $value]);
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
				default:
					$query->addOrderBy(['st.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
