<?php

namespace backend\modules\helpdesk\models;

use common\helpers\DateHelper;
use common\models\SupportTicketStatus;
use common\models\SupportTicketStatusTranslation;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class SupportTicketStatusSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = SupportTicketStatus::find()
			->alias('sts')
			->select([
				'sts.id',
				'sts.sort_order',
				'sts.icon',
				'sts.color',
				'sts.default',
				'sts.created_by',
				'sts.created_at',
				'sts.updated_at',
				'sts.status',
			])
			->joinWith([
				'supportTicketStatusTranslations stst' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'stst.language_id' => Yii::$app->language,
						'stst.deleted' => SupportTicketStatusTranslation::NO,
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
				'sts.deleted' => isset($this->requestParams['deleted']) ? $this->requestParams['deleted'] : SupportTicketStatus::NO,
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			SupportTicketStatus::class => [
				'id',
				'action' => function (SupportTicketStatus $model) {
					$actions = [];

					if ($this->requestParams['deleted'] == SupportTicketStatus::YES) {
						if (Yii::$app->user->can('restoreHelpdeskSupportTicketStatus')) {
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
						if (Yii::$app->user->can('deleteHelpdeskSupportTicketStatus')) {
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
						if (Yii::$app->user->can('viewHelpdeskSupportTicketStatus')) {
							$actions[] = Html::a('<span class="fa fa-eye"></span>', ['view', 'id' => $model->id], [
								'class' => 'action-view btn btn-xs btn-info',
								'title' => Yii::t('common', 'View'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('updateHelpdeskSupportTicketStatus')) {
							$actions[] = Html::a('<span class="fa fa-edit"></span>', ['update', 'id' => $model->id], [
								'class' => 'action-update btn btn-xs btn-primary',
								'title' => Yii::t('common', 'Update'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('deleteHelpdeskSupportTicketStatus') && !$model->default) {
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
				'sort_order' => function (SupportTicketStatus $model) {
					return $model->sort_order ?: '&mdash;';
				},
				'name' => function (SupportTicketStatus $model) {
					return $model->getFormattedName() ?: '&mdash;';
				},
				'default' => function (SupportTicketStatus $model) {
					return [
						'label' => Yii::$app->formatter->asBoolean($model->default),
						'value' => $model->default,
					];
				},
				'created_by' => function (SupportTicketStatus $model) {
					return $model->creator ? $model->creator->fullName : '&mdash;';
				},
				'created_at' => function (SupportTicketStatus $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
				'updated_at' => function (SupportTicketStatus $model) {
					return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
				},
				'status' => function (SupportTicketStatus $model) {
					$status = SupportTicketStatus::getStatusLabels()[$model->status];
					return Html::tag('span', $status['label'], ['class' => 'label label-block label-' . $status['color']]);
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
				case 'name':
					$query->$filterOperator(['LIKE', 'stst.name', $value]);
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
					$query->$filterOperator(['LIKE', 'sts.created_at', DateHelper::formatAsDate($value)]);
					break;
				case 'updated_at':
					$query->$filterOperator(['LIKE', 'sts.updated_at', DateHelper::formatAsDate($value)]);
					break;
				default:
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 'sts.' . $column['data'], $value]);
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
				case 'sort_order':
					$query->addOrderBy(new Expression('[[sts.sort_order]] IS NULL'));
					$query->addOrderBy(['sts.sort_order' => $sort]);
					break;
				case 'name':
					$query->addOrderBy(['stst.name' => $sort]);
					break;
				case 'created_by':
					$query->addOrderBy([
						'cr.first_name' => $sort,
						'cr.middle_name' => $sort,
						'cr.last_name' => $sort,
					]);
					break;
				default:
					$query->addOrderBy(['sts.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
