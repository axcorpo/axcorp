<?php

namespace backend\modules\setting\modules\language\models;

use common\models\LanguageSource;
use common\widgets\datatable\DataTableAction;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;

class LanguageTranslationSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = LanguageSource::find()
			->alias('ls')
			->joinWith([
				'languageTranslates lt' => function (ActiveQuery $query) {
					$query->andOnCondition(['lt.language' => $this->requestParams['language_id']]);
				},
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			LanguageSource::class => [
				'id',
				'category',
				'message' => function ($model) {
					return $model->message;
				},
				'translation' => function ($model) {
					return $model->languageTranslates[0]->translation;
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
				case 'translation':
					$query->$filterOperator(['LIKE', 'lt.translation', $value]);
					break;
				default:
					// Apply default filter if column exist in table schema
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 'ls.' . $column['data'], $value]);
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
				case 'translation':
					$query->addOrderBy(['lt.translation' => $sort]);
					break;
				default:
					// Apply default order
					$query->addOrderBy(['ls.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
