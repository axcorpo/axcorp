<?php

namespace backend\modules\notification\models;

use common\helpers\DateHelper;
use common\models\Notification;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class NotificationSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = Notification::find()
			->alias('n')
			->select([
				'n.id',
				'n.type',
				'n.title',
				'n.message',
				'n.icon',
				'n.color',
				'n.created_by',
				'n.updated_by',
				'n.created_at',
				'n.updated_at',
			])
			->joinWith([
				'userHasNotifications uhn',
				'creator c' => function (ActiveQuery $query) {
					$query->select([
						'c.id',
						'c.first_name',
						'c.middle_name',
						'c.last_name',
					]);
				},
			])
			->andWhere([
				'n.deleted' => isset($this->requestParams['deleted']) ? $this->requestParams['deleted'] : Notification::NO,
			])
			->andWhere([
				'OR',
				['=', 'n.created_by', Yii::$app->user->id],
				['=', 'uhn.user_id', Yii::$app->user->id],
			])
			->groupBy(['n.id']);
	}

	/**
	 * @inheritdoc
	 *
	 * Lean count: the grid query LEFT JOINs `userHasNotifications` (one-to-many) and GROUP BYs
	 * `n.id`, which makes Yii wrap the row count as `SELECT COUNT(*) FROM (<grouped join>) c`. Drop
	 * the GROUP BY and count `COUNT(DISTINCT n.id)` instead — exact because every join is a LEFT join
	 * that cannot drop a notification row, and far cheaper (no derived-table materialization). The
	 * `uhn` join is kept because the base WHERE filters on `uhn.user_id`.
	 */
	protected function getRecordsTotal($originalQuery)
	{
		return (int) (clone $originalQuery)
			->select(new Expression('COUNT(DISTINCT n.id)'))
			->groupBy([])
			->orderBy([])
			->limit(-1)
			->offset(-1)
			->scalar();
	}

	/**
	 * @inheritdoc Lean `COUNT(DISTINCT n.id)` over the filtered grid query — see {@see getRecordsTotal()}.
	 */
	protected function getRecordsFiltered($filteredQuery)
	{
		return (int) (clone $filteredQuery)
			->select(new Expression('COUNT(DISTINCT n.id)'))
			->groupBy([])
			->orderBy([])
			->limit(-1)
			->offset(-1)
			->scalar();
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			Notification::class => [
				'id',
				'action' => function (Notification $model) {
					$actions = [];

					if ($this->requestParams['deleted'] == Notification::YES) {
						if (Yii::$app->user->can('restoreNotification')) {
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
						if (Yii::$app->user->can('deleteNotification')) {
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
						if (Yii::$app->user->can('viewNotification')) {
							$actions[] = Html::a('<span class="fa fa-eye"></span>', ['view', 'id' => $model->id], [
								'class' => 'action-view btn btn-xs btn-info',
								'title' => Yii::t('common', 'View'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('updateNotification') && $model->created_by == Yii::$app->user->id) {
							$actions[] = Html::a('<span class="fa fa-edit"></span>', ['update', 'id' => $model->id], [
								'class' => 'action-update btn btn-xs btn-primary',
								'title' => Yii::t('common', 'Update'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('deleteNotification')) {
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
				'type' => function (Notification $model) {
					return Notification::getTypeLabels()[$model->type];
				},
				'title' => function (Notification $model) {
					return $model->getFormattedTitle();
				},
				'message' => function (Notification $model) {
					return $model->message ? $model->getMessageExcerpt() : '&mdash;';
				},
				'created_by' => function (Notification $model) {
					return $model->creator ? $model->creator->fullName : '&mdash;';
				},
				'created_at' => function (Notification $model) {
					return Yii::$app->formatter->asDatetime($model->created_at);
				},
				'updated_at' => function (Notification $model) {
					return Yii::$app->formatter->asDatetime($model->updated_at);
				},
				'seen' => function (Notification $model) {
					$seen = in_array(1, ArrayHelper::getColumn($model->userHasNotifications, 'seen'));
					return Yii::$app->formatter->asBoolean($seen);
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
				case 'created_by':
					$query->$filterOperator([
						'OR',
						['LIKE', 'c.first_name', $value],
						['LIKE', 'c.middle_name', $value],
						['LIKE', 'c.last_name', $value],
						['LIKE', new Expression('CONCAT(c.first_name, " ", c.middle_name, " ", c.last_name)'), $value],
					]);
					break;
				case 'created_at':
					$query->$filterOperator(['LIKE', 'n.created_at', DateHelper::formatAsDate($value)]);
					break;
				case 'updated_at':
					$query->$filterOperator(['LIKE', 'n.updated_at', DateHelper::formatAsDate($value)]);
					break;
				case 'seen':
					$query->$filterOperator(['=', 'uhn.seen', $value]);
					break;
				default:
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 'n.' . $column['data'], $value]);
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
				case 'created_by':
					$query->addOrderBy([
						'c.first_name' => $sort,
						'c.middle_name' => $sort,
						'c.last_name' => $sort,
					]);
					break;
				case 'seen':
					$query->addOrderBy(['uhn.seen' => $sort]);
					break;
				default:
					$query->addOrderBy(['n.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
