<?php

namespace backend\modules\eventlog\models;

use common\helpers\DateHelper;
use common\models\EventLog;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class EventLogSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = EventLog::find()
			->alias('el')
			->select([
				'el.id',
				'el.user_id',
				'el.model_key',
				'el.module',
				'el.controller',
				'el.model',
				'el.action',
				'el.resource',
				'el.operation',
				'el.ip_address',
				'el.created_at',
			])
			->joinWith([
				'user u' => function (ActiveQuery $query) {
					$query->select([
						'u.id',
						'u.first_name',
						'u.middle_name',
						'u.last_name',
					]);
				},
			])
			->where([
				'el.deleted' => EventLog::NO,
			])
			->andFilterWhere([
				'el.model' => $this->requestParams['model'],
				'el.model_key' => $this->requestParams['model_key'],
				'el.user_id' => $this->requestParams['user_id'],
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function actions()
	{
		return [
			'dt-event-logs' => EventLogSearch::class,
		];
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			EventLog::class => [
				'id',
				'user' => function (EventLog $model) {
					if ($model->user) {
						return Html::a($model->user->getFullName(), ['/user-manager/user/view', 'id' => $model->user_id], [
							'target' => '_blank',
						]);
					}
					return '&mdash;';
				},
				'resource' => function (EventLog $model) {
					return $model->resource ? $model->getTranslatedResource() : '&mdash;';
				},
				'model_key' => function (EventLog $model) {
					if ($model->resource) {
						return Html::a($model->getResourceName(), ["/{$model->module}/{$model->controller}/view", 'id' => $model->model_key], [
							'target' => '_blank',
							'title' => Yii::t('common', 'View'),
							'data' => [
								'toggle' => 'tooltip',
							],
						]);
					}
					return '&mdash;';
				},
				'operation' => function (EventLog $model) {
					return $model->getFormattedOperation();
				},
				'ip_address' => function (EventLog $model) {
					return $model->ip_address;
				},
				'date' => function (EventLog $model) {
					return Yii::$app->formatter->asDatetime($model->created_at);
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
				case 'resource':
					$query->$filterOperator(['=', 'el.resource', $value]);
					break;
				case 'operation':
					$query->$filterOperator(['=', 'el.operation', $value]);
					break;
				case 'user':
					$query->$filterOperator([
						'OR',
						['LIKE', 'u.first_name', $value],
						['LIKE', 'u.middle_name', $value],
						['LIKE', 'u.last_name', $value],
					]);
					break;
				case 'date':
					$query->$filterOperator(['LIKE', 'el.created_at', DateHelper::formatAsDate($value)]);
					break;
				default:
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 'el.' . $column['data'], $value]);
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
				case 'user':
					$query->addOrderBy([
						'u.first_name' => $sort,
						'u.middle_name' => $sort,
						'u.last_name' => $sort,
					]);
					break;
				case 'date':
					$query->addOrderBy(['el.created_at' => $sort]);
					break;
				default:
					$query->addOrderBy(['el.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
