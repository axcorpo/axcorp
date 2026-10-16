<?php

namespace backend\modules\user\models;

use common\models\AuthItem;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\rbac\Item;

class RoleSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = AuthItem::find()
			->alias('ai')
			->select([
				'ai.name',
				'ai.rule_name',
				'ai.type',
				'ai.description',
				'ai.data',
				'ai.created_at',
				'ai.updated_at',
			])
			->where([
				'AND',
				['=', 'ai.type', Item::TYPE_ROLE],
				['!=', 'ai.name', 'superAdmin'],
			])
			->andFilterWhere([
				'AND',
				['!=', 'ai.name', Yii::$app->user->identity->authAssignment->item_name],
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			AuthItem::class => [
				'id' => function ($model) {
					return Html::encode($model->name);
				},
				'action' => function (AuthItem $model) {
					$actions = [];

					if ($this->requestParams['deleted'] == AuthItem::YES) {
						if (Yii::$app->user->can('restoreUserRole')) {
							$actions[] = Html::a('<span class="fa fa-undo"></span>', ['restore', 'id' => $model->name], [
								'class' => 'action-view btn btn-xs btn-success',
								'title' => Yii::t('common', 'Restore'),
								'data' => [
									'toggle' => 'tooltip',
									'dt-operation' => 'restore',
									'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							]);
						}
						if (Yii::$app->user->can('deleteUserRole')) {
							$actions[] = Html::a('<span class="fa fa-trash"></span>', ['delete', 'id' => $model->name], [
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
						if (Yii::$app->user->can('viewUserRole')) {
							$actions[] = Html::a('<span class="fa fa-eye"></span>', ['view', 'id' => $model->name], [
								'class' => 'action-view btn btn-xs btn-info',
								'title' => Yii::t('common', 'View'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('updateUserRole')) {
							$actions[] = Html::a('<span class="fa fa-edit"></span>', ['update', 'id' => $model->name], [
								'class' => 'action-update btn btn-xs btn-primary',
								'title' => Yii::t('common', 'Update'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('deleteUserRole')) {
							$actions[] = Html::a('<span class="fa fa-trash"></span>', ['delete', 'id' => $model->name], [
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
				'description' => function (AuthItem $model) {
					return Html::encode($model->description);
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
		// Loop through the DataTable columns
		foreach ($columns as $column) {
			// Continue if the column is not searchable
			if ($column['searchable'] == 'false') {
				continue;
			}
			// Get the filter value
			if (!empty($search['value'])) {
				$value = $search['value'];
				$filterOperator = 'orFilterWhere';
			} else {
				$value = $column['search']['value'];
				$filterOperator = 'andFilterWhere';
			}
			// Handle custom column filter
			switch ($column['data']) {
				default:
					// Apply default filter if column exist in table schema
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 'ai.' . $column['data'], $value]);
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
		// Loop through the DataTable order items
		foreach ($order as $key => $item) {
			// Get the order targeted column
			$column = $columns[$item['column']];
			// Continue if the column is not orderable
			if (array_key_exists('orderable', $column) && $column['orderable'] === 'false') {
				continue;
			}
			// Get the order value
			$sort = mb_strtolower($item['dir']) == 'desc' ? SORT_DESC : SORT_ASC;
			// Handle custom column filter
			switch ($column['data']) {
				default:
					// Apply default order
					$query->addOrderBy(['ai.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
