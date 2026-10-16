<?php

namespace backend\modules\workspace\models;

use common\helpers\DateHelper;
use common\models\WorkspaceDatabaseUpdateLog;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\StringHelper;

class WorkspaceDatabaseUpdateLogSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = WorkspaceDatabaseUpdateLog::find()
			->alias('l')
			->select([
				'l.id',
				'l.workspace_id',
				'l.workspace_code',
				'l.workspace_url',
				'l.query',
				'l.status',
				'l.error',
				'l.created_at',
			]);

		// Optionally scope the list to a single workspace code.
		if (!empty($this->requestParams['workspace_code'])) {
			$this->query->andWhere(['l.workspace_code' => $this->requestParams['workspace_code']]);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			WorkspaceDatabaseUpdateLog::class => [
				'id',
				'action' => function (WorkspaceDatabaseUpdateLog $model) {
					$actions = [];
					if (!empty($model->query)) {
						$actions[] = Html::button('<span class="fa fa-copy"></span>', [
							'type' => 'button',
							'class' => 'action-copy-query btn btn-xs btn-default',
							'title' => Yii::t('common', 'Copy'),
							'data' => [
								'toggle' => 'tooltip',
								'clipboard-text' => $model->query,
							],
						]);
					}
					if (Yii::$app->user->can('updateWorkspace')) {
						$actions[] = Html::a('<span class="fa fa-repeat"></span>', ['database-update', 'code' => $model->workspace_code], [
							'class' => 'action-update btn btn-xs btn-warning',
							'title' => Yii::t('common', 'Rerun'),
							'data' => [
								'toggle' => 'tooltip',
							],
						]);
					}
					return implode(' ', $actions);
				},
				'workspace_code' => function (WorkspaceDatabaseUpdateLog $model) {
					return Html::tag('code', Html::encode($model->workspace_code));
				},
				'workspace_url' => function (WorkspaceDatabaseUpdateLog $model) {
					return $model->workspace_url ? Html::encode($model->workspace_url) : '&mdash;';
				},
				'status' => function (WorkspaceDatabaseUpdateLog $model) {
					$status = WorkspaceDatabaseUpdateLog::getResultLabels()[$model->status];
					return Html::tag('span', $status['label'], ['class' => 'label label-block label-' . $status['color']]);
				},
				'query' => function (WorkspaceDatabaseUpdateLog $model) {
					return Html::tag('code', Html::encode(StringHelper::truncate($model->query, 160)), [
						'title' => $model->query,
						'data' => ['toggle' => 'tooltip'],
					]);
				},
				'error' => function (WorkspaceDatabaseUpdateLog $model) {
					if (empty($model->error)) {
						return '&mdash;';
					}
					return Html::tag('span', Html::encode(StringHelper::truncate($model->error, 160)), [
						'class' => 'text-danger',
						'title' => $model->error,
						'data' => ['toggle' => 'tooltip'],
					]);
				},
				'created_at' => function (WorkspaceDatabaseUpdateLog $model) {
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
				$value = $search['value'];
				$filterOperator = 'orFilterWhere';
			} else {
				$value = $column['search']['value'];
				$filterOperator = 'andFilterWhere';
			}

			switch ($column['data']) {
				case 'created_at':
					$query->$filterOperator(['LIKE', 'l.created_at', DateHelper::formatAsDate($value)]);
					break;
				default:
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 'l.' . $column['data'], $value]);
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
			$query->addOrderBy(['l.' . $column['data'] => $sort]);
		}
		return $query;
	}
}
