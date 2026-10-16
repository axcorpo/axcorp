<?php

namespace backend\modules\helpdesk\models;

use common\helpers\DateHelper;
use common\models\SupportTicketDepartment;
use common\models\SupportTicketDepartmentTranslation;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class SupportTicketDepartmentSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = SupportTicketDepartment::find()
			->alias('std')
			->select([
				'std.id',
				'std.sort_order',
				'std.default',
				'std.created_by',
				'std.created_at',
				'std.updated_at',
				'std.status',
			])
			->joinWith([
				'supportTicketDepartmentTranslations stdt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'stdt.language_id' => Yii::$app->language,
						'stdt.deleted' => SupportTicketDepartmentTranslation::NO,
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
				'std.deleted' => isset($this->requestParams['deleted']) ? $this->requestParams['deleted'] : SupportTicketDepartment::NO,
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			SupportTicketDepartment::class => [
				'id',
				'action' => function (SupportTicketDepartment $model) {
					$actions = [];

					if ($this->requestParams['deleted'] == SupportTicketDepartment::YES) {
						if (Yii::$app->user->can('restoreHelpdeskSupportTicketDepartment')) {
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
						if (Yii::$app->user->can('deleteHelpdeskSupportTicketDepartment')) {
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
						if (Yii::$app->user->can('viewHelpdeskSupportTicketDepartment')) {
							$actions[] = Html::a('<span class="fa fa-eye"></span>', ['view', 'id' => $model->id], [
								'class' => 'action-view btn btn-xs btn-info',
								'title' => Yii::t('common', 'View'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('updateHelpdeskSupportTicketDepartment')) {
							$actions[] = Html::a('<span class="fa fa-edit"></span>', ['update', 'id' => $model->id], [
								'class' => 'action-update btn btn-xs btn-primary',
								'title' => Yii::t('common', 'Update'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('deleteHelpdeskSupportTicketDepartment') && !$model->default) {
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
				'sort_order' => function (SupportTicketDepartment $model) {
					return $model->sort_order ?: '&mdash;';
				},
				'name' => function (SupportTicketDepartment $model) {
					return $model->translation->name ?: '&mdash;';
				},
				'default' => function (SupportTicketDepartment $model) {
					return [
						'label' => Yii::$app->formatter->asBoolean($model->default),
						'value' => $model->default,
					];
				},
				'created_by' => function (SupportTicketDepartment $model) {
					return $model->creator ? $model->creator->fullName : '&mdash;';
				},
				'created_at' => function (SupportTicketDepartment $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
				'updated_at' => function (SupportTicketDepartment $model) {
					return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
				},
				'status' => function (SupportTicketDepartment $model) {
					$status = SupportTicketDepartment::getStatusLabels()[$model->status];
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
					$query->$filterOperator(['LIKE', 'stdt.name', $value]);
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
					$query->$filterOperator(['LIKE', 'std.created_at', DateHelper::formatAsDate($value)]);
					break;
				case 'updated_at':
					$query->$filterOperator(['LIKE', 'std.updated_at', DateHelper::formatAsDate($value)]);
					break;
				default:
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 'std.' . $column['data'], $value]);
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
					$query->addOrderBy(new Expression('[[std.sort_order]] IS NULL'));
					$query->addOrderBy(['std.sort_order' => $sort]);
					break;
				case 'name':
					$query->addOrderBy(['stdt.name' => $sort]);
					break;
				case 'created_by':
					$query->addOrderBy([
						'cr.first_name' => $sort,
						'cr.middle_name' => $sort,
						'cr.last_name' => $sort,
					]);
					break;
				default:
					$query->addOrderBy(['std.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
