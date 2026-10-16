<?php

namespace backend\modules\nomenclature\models;

use common\helpers\DateHelper;
use common\models\Package;
use common\models\PackageFeature;
use common\models\PackageTranslation;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class PackageSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = Package::find()
			->alias('p')
			->select([
				'p.id',
				'p.trial_period',
				'p.trial_cycle',
				'p.billing_period',
				'p.billing_cycle',
				'p.price',
				'p.currency',
                'p.default',
				'p.sort_order',
				'p.type',
				'p.created_by',
				'p.created_at',
				'p.updated_at',
				'p.status',
			])
			->joinWith([
				'packageTranslations pt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'pt.language_id' => Yii::$app->language,
						'pt.deleted' => PackageTranslation::NO,
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
				'p.deleted' => isset($this->requestParams['deleted']) ? $this->requestParams['deleted'] : Package::NO,
			])
			->orderBy([
				'p.type' => SORT_ASC,
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			Package::class => [
				'id',
				'action' => function (Package $model) {
					$actions = [];

					if ($this->requestParams['deleted'] == Package::YES) {
						if (Yii::$app->user->can('restorePackage')) {
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
						if (Yii::$app->user->can('deletePackage')) {
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
						if (Yii::$app->user->can('viewPackage')) {
							$actions[] = Html::a('<span class="fa fa-eye"></span>', ['view', 'id' => $model->id], [
								'class' => 'action-view btn btn-xs btn-info',
								'title' => Yii::t('common', 'View'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('updatePackage')) {
							$actions[] = Html::a('<span class="fa fa-edit"></span>', ['update', 'id' => $model->id], [
								'class' => 'action-update btn btn-xs btn-primary',
								'title' => Yii::t('common', 'Update'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('deletePackage')) {
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
                        if (Yii::$app->user->can('updatePackage')) {
                            $actions[] = Html::a('<span class="fa fa-arrow-up"></span>', ['upgrade', 'id' => $model->id], [
                                'class' => 'action-upgrade btn btn-xs btn-warning',
                                'title' => Yii::t('common', 'Upgrade'),
                                'data' => [
                                    'toggle' => 'tooltip',
                                ],
                            ]);
                        }
					}

					$actions = array_map(function ($actionsChunk) {
						return Html::tag('div', implode('', $actionsChunk));
					}, array_chunk($actions, 4));

					return implode('', $actions);
				},
				'sort_order' => function (Package $model) {
					return $model->sort_order ?: '&mdash;';
				},
				'name' => function (Package $model) {
					return $model->translation->name ?: '&mdash;';
				},
				'price' => function (Package $model) {
					return Yii::$app->formatter->asCurrency($model->price ?: 0, $model->currency);
				},
				'trial_period' => function (Package $model) {
					return $model->getFormattedTrialPeriod() ?: '&mdash;';
				},
				'billing_cycle' => function (Package $model) {
					return $model->getFormattedBillingCycle() ?: '&mdash;';
				},
                'defaultValue' => function (Package $model) {
                    return $model->default;
                },
                'default' => function (Package $model) {
                    return Yii::$app->formatter->asBoolean($model->default);
                },
				'type' => function (Package $model) {
					return Package::getTypeLabels()[$model->type] ?: '&mdash;';
				},
				'created_at' => function (Package $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
				'updated_at' => function (Package $model) {
					return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
				},
				'status' => function (Package $model) {
					$status = Package::getStatusLabels()[$model->status];
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
					$query->$filterOperator(['LIKE', 'pt.name', $value]);
					break;
				case 'trial_period':
					$query->$filterOperator(['=', 'p.trial_cycle', $value]);
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
				case 'sort_order':
					$query->addOrderBy(new Expression('[[p.sort_order]] IS NULL'));
					$query->addOrderBy(['p.sort_order' => $sort]);
					break;
				case 'name':
					$query->addOrderBy(['pt.name' => $sort]);
					break;
				case 'trial_period':
					$query->addOrderBy([
						'p.trial_period' => $sort,
						'p.trial_cycle' => $sort,
					]);
					break;
				case 'created_by':
					$query->addOrderBy([
						'cr.first_name' => $sort,
						'cr.middle_name' => $sort,
						'cr.last_name' => $sort,
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
