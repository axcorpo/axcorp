<?php

namespace backend\modules\website\models;

use common\helpers\DateHelper;
use common\helpers\FontIcon;
use common\models\Service;
use common\models\ServiceCategoryTranslation;
use common\models\ServiceTranslation;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class ServiceSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = Service::find()
			->alias('s')
			->select([
				's.id',
				's.image',
				's.icon',
				's.video',
				's.featured',
				's.views',
				's.sort_order',
				's.created_by',
				's.updated_by',
				's.created_at',
				's.updated_at',
				's.status',
			])
			->joinWith([
				'serviceTranslations st' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'st.language_id' => Yii::$app->language,
						'st.deleted' => ServiceTranslation::NO,
					]);
				},
				'serviceCategories.serviceCategoryTranslations act' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'act.language_id' => Yii::$app->language,
						'act.deleted' => ServiceCategoryTranslation::NO,
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
				's.deleted' => isset($this->requestParams['deleted']) ? $this->requestParams['deleted'] : Service::NO,
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			Service::class => [
				'id',
				'action' => function (Service $model) {
					$actions = [];

					if ($this->requestParams['deleted'] == Service::YES) {
						if (Yii::$app->user->can('restoreService')) {
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
						if (Yii::$app->user->can('deleteService')) {
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
						if (Yii::$app->user->can('viewService')) {
							$actions[] = Html::a('<span class="fa fa-eye"></span>', ['view', 'id' => $model->id], [
								'class' => 'action-view btn btn-xs btn-info',
								'title' => Yii::t('common', 'View'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('updateService')) {
							$actions[] = Html::a('<span class="fa fa-edit"></span>', ['update', 'id' => $model->id], [
								'class' => 'action-update btn btn-xs btn-primary',
								'title' => Yii::t('common', 'Update'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('deleteService')) {
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
				'sort_order',
				'figure' => function (Service $model) {
					if ($model->video) {
						return Html::a('<span class="fa fa-play-circle-o fa-lg"></span>', $model->video, [
							'title' => Yii::t('common', 'Open Gallery'),
							'data' => [
								'toggle' => 'tooltip',
								'fancybox' => 'services',
								'caption' => $model->translation->title,
							],
						]);
					} elseif ($model->image && is_file(Yii::getAlias("@uploads/service/{$model->uuid}/{$model->image}"))) {
						$imgTag = Html::img($model->getImageUrl(), [
							'class' => 'img-responsive',
							'alt' => $model->translation->title,
						]);
						return Html::a($imgTag, $model->getImageUrl(), [
							'title' => Yii::t('common', 'Open Gallery'),
							'data' => [
								'toggle' => 'tooltip',
								'fancybox' => 'services',
								'caption' => $model->translation->title,
							],
						]);
					} elseif ($model->icon) {
						return FontIcon::render($model->icon, ['class' => 'fa-lg']);
					}
					return '&mdash;';
				},
				'title' => function (Service $model) {
					return $model->translation->title ?: '&mdash;';
				},
				'category' => function (Service $model) {
					if ($model->serviceCategories) {
						return implode(', ', ArrayHelper::getColumn($model->serviceCategories, 'translation.title'));
					}
					return '&mdash;';
				},
				'featured' => function (Service $model) {
					return Yii::$app->formatter->asBoolean($model->featured);
				},
				'views' => function (Service $model) {
					return $model->views ?: 0;
				},
				'created_by' => function (Service $model) {
					return $model->creator ? $model->creator->fullName : '&mdash;';
				},
				'created_at' => function (Service $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
				'updated_at' => function (Service $model) {
					return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
				},
				'status' => function (Service $model) {
					$status = Service::getStatusLabels()[$model->status];
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
					$query->$filterOperator(['LIKE', 'st.title', $value]);
					break;
				case 'category':
					$query->$filterOperator(['LIKE', 'act.title', $value]);
					break;
				case 'views':
					$query->$filterOperator(['=', 's.views', $value]);
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
					$query->$filterOperator(['LIKE', 's.created_at', DateHelper::formatAsDate($value)]);
					break;
				case 'updated_at':
					$query->$filterOperator(['LIKE', 's.updated_at', DateHelper::formatAsDate($value)]);
					break;
				default:
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 's.' . $column['data'], $value]);
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
					$query->addOrderBy(['st.title' => $sort]);
					break;
				case 'category':
					$query->addOrderBy(['act.title' => $sort]);
					break;
				case 'created_by':
					$query->addOrderBy([
						'cr.first_name' => $sort,
						'cr.middle_name' => $sort,
						'cr.last_name' => $sort,
					]);
					break;
				default:
					$query->addOrderBy(['s.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
