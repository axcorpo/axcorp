<?php

namespace backend\modules\website\models;

use common\helpers\DateHelper;
use common\models\Page;
use common\models\PageTranslation;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Inflector;

class PageSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = Page::find()
			->alias('p')
			->select([
				'p.id',
				'p.module',
				'p.controller',
				'p.action',
				'p.default',
				'p.created_at',
				'p.updated_at',
				'p.created_by',
				'p.status',
			])
			->joinWith([
				'pageTranslations pt' => function (ActiveQuery $query) {
					return $query->andOnCondition([
						'pt.language_id' => Yii::$app->language,
						'pt.deleted' => PageTranslation::NO,
					]);
				},
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
				'p.deleted' => isset($this->requestParams['deleted']) ? $this->requestParams['deleted'] : Page::NO,
			])
			->orderBy([
				'p.module' => SORT_ASC,
				'p.default' => SORT_DESC,
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			Page::class => [
				'id',
				'module' => function (Page $model) {
					return $model->module ? Inflector::titleize($model->module) : Yii::t('common', 'Application');
				},
				'actions' => function (Page $model) {
					$actions = [];

					if ($this->requestParams['deleted'] == Page::YES) {
						if (Yii::$app->user->can('restorePage')) {
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
						if (Yii::$app->user->can('deletePage')) {
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
						if (Yii::$app->user->can('viewPage')) {
							$actions[] = Html::a('<span class="fa fa-eye"></span>', ['view', 'id' => $model->id], [
								'class' => 'action-view btn btn-xs btn-info',
								'title' => Yii::t('common', 'View'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('updatePage')) {
							$actions[] = Html::a('<span class="fa fa-edit"></span>', ['update', 'id' => $model->id], [
								'class' => 'action-update btn btn-xs btn-primary',
								'title' => Yii::t('common', 'Update'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('deletePage') && $model->default == Page::NO) {
							if (empty($model->module) && $model->controller == 'site' && $model->action == 'page') {
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
					}

					$actions = array_map(function ($actionsChunk) {
						return Html::tag('div', implode('', $actionsChunk));
					}, array_chunk($actions, 3));

					return implode('', $actions);
				},
				'title' => function (Page $model) {
					return Html::encode($model->translation->title);
				},
				'defaultValue' => function (Page $model) {
					return $model->default;
				},
				'default' => function (Page $model) {
					return Yii::$app->formatter->asBoolean($model->default);
				},
				'created_by' => function (Page $model) {
					return $model->creator->fullName;
				},
				'created_at' => function (Page $model) {
					return Yii::$app->formatter->asDatetime($model->created_at);
				},
				'updated_at' => function (Page $model) {
					return Yii::$app->formatter->asDatetime($model->updated_at);
				},
				'status' => function (Page $model) {
					$status = Page::getStatusLabels()[$model->status];
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
				case 'title':
					$query->$filterOperator(['LIKE', 'pt.title', $value]);
					break;
				case 'created_by':
					$query->$filterOperator([
						'OR',
						['LIKE', 'c.first_name', $value],
						['LIKE', 'c.middle_name', $value],
						['LIKE', 'c.last_name', $value],
					]);
					break;
				case 'created_at':
					$query->$filterOperator(['LIKE', 'p.created_at', DateHelper::formatAsDate($value)]);
					break;
				case 'updated_at':
					$query->$filterOperator(['LIKE', 'p.updated_at', DateHelper::formatAsDate($value)]);
					break;
				default:
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 'p.' . $column['data'], $value]);
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
				case 'title':
					$query->addOrderBy(['pt.title' => $sort]);
					break;
				case 'created_by':
					$query->addOrderBy([
						'c.first_name' => $sort,
						'c.middle_name' => $sort,
						'c.last_name' => $sort,
					]);
					break;
				default:
					$query->addOrderBy(['p.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
