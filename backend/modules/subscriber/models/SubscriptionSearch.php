<?php

namespace backend\modules\subscriber\models;

use common\helpers\DateHelper;
use common\helpers\UuidHelper;
use common\models\AuthAssignment;
use common\models\Package;
use common\models\PackageTranslation;
use common\models\Subscription;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\Html;

class SubscriptionSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = Subscription::find()
			->alias('sub')
			->select([
				'sub.id',
				'sub.parent_id',
				'sub.subscriber_id',
				'sub.package_id',
				'sub.code',
				'sub.billing_period',
				'sub.billing_cycle',
				'sub.start_at',
				'sub.end_at',
				'sub.price',
				'sub.currency',
				'sub.type',
				'sub.status',
				'sub.updated_by',
				'psub.code AS parent_code',
				'p.type AS package_type',
				'pt.name AS package_name',
				// Role of the user who last touched the subscription: set when an admin
				// cancelled it, null when the customer did (or a console process).
				'uaa.item_name AS updated_by_role',
			])
			->leftJoin(AuthAssignment::tableName() . ' uaa', '[[uaa.user_id]] = [[sub.updated_by]]')
			->joinWith([
				'parent psub',
				'package p' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'p.deleted' => Package::NO,
					]);
				},
				'package.packageTranslations pt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'pt.language_id' => Yii::$app->language,
						'pt.deleted' => PackageTranslation::NO,
					]);
				},
			])
			->andWhere([
				'sub.deleted' => isset($this->requestParams['deleted']) ? $this->requestParams['deleted'] : Subscription::NO,
			])
			->andFilterWhere(['sub.subscriber_id' => $this->requestParams['subscriber_id']])
			->addOrderBy(new Expression('[[sub.parent_id]] IS NOT NULL'));
	}

	/**
	 * @inheritdoc
	 * @throws \yii\db\Exception
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		$data = [];

		foreach ($query->createCommand()->queryAll() as $model) {
			$data[] = [
				// The ids are BINARY(16) UUIDs — an (int) cast would collapse them all to 0.
				'id' => $model['id'] !== null ? UuidHelper::fromBytes($model['id']) : null,
				'package_id' => $model['package_id'] !== null ? UuidHelper::fromBytes($model['package_id']) : null,
				'group' => call_user_func(function () use ($model) {
					$group = [];
					if (!empty($model['parent_id'])) {
						$group['label'] = Yii::t('common', 'Features');
					} else {
						$group['label'] = Yii::t('common', 'Packages');
					}
					return $group;
				}),
				'action' => call_user_func(function () use ($model) {
					$actions = [];

					if ($this->requestParams['deleted'] == Subscription::YES) {
						if (Yii::$app->user->can('restoreSubscriber')) {
							$actions[] = Html::a('<span class="fa fa-undo"></span>', ['subscription/restore', 'subscriber_id' => $model['subscriber_id'], 'id' => $model['id']], [
								'class' => 'action-view btn btn-xs btn-success',
								'title' => Yii::t('common', 'Restore'),
								'data' => [
									'toggle' => 'tooltip',
									'dt-operation' => 'restore',
									'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							]);
						}
						if (Yii::$app->user->can('deleteSubscriber')) {
							$actions[] = Html::a('<span class="fa fa-trash"></span>', ['subscription/delete', 'subscriber_id' => $model['subscriber_id'], 'id' => $model['id']], [
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
						if (Yii::$app->user->can('viewSubscriber')) {
							$actions[] = Html::a('<span class="fa fa-eye"></span>', ['subscription/view', 'subscriber_id' => $model['subscriber_id'], 'id' => $model['id']], [
								'class' => 'btn btn-xs btn-info',
								'title' => Yii::t('common', 'View'),
								'data' => [
									'toggle' => 'tooltip',
									'popup-action' => '',
								],
							]);
						}
						if (Yii::$app->user->can('updateSubscriber')) {
							if ($model['package_type'] != Package::TYPE_FREE && $model['status'] != Subscription::STATUS_CANCELLED) {
								$actions[] = Html::a('<span class="fa fa-edit"></span>', ['subscription/update', 'subscriber_id' => $model['subscriber_id'], 'id' => $model['id']], [
									'class' => 'btn btn-xs btn-primary',
									'title' => Yii::t('common', 'Update'),
									'data' => [
										'toggle' => 'tooltip',
										'popup-action' => '',
										'popup-done' => ['redrawDataTable' => '#dt-subscriptions'],
									],
								]);
							}
							if (empty($model['parent_id'])) {
								// A subscription cancelled by an admin is final: no activate button.
								// One cancelled by the customer (updated_by is not an admin user)
								// keeps the activate button, so the admin can reactivate it.
								$cancelledByAdmin = $model['status'] == Subscription::STATUS_CANCELLED && !empty($model['updated_by_role']);
								if ($model['status'] != Subscription::STATUS_ACTIVE && !$cancelledByAdmin) {
									$actions[] = Html::a('<span class="fa fa-check"></span>', ['subscription/activate', 'subscriber_id' => $model['subscriber_id'], 'id' => $model['id']], [
										'class' => 'btn btn-xs btn-success',
										'title' => Yii::t('common', 'Activate'),
										'data' => [
											'toggle' => 'tooltip',
											'popup-action' => '',
											'popup-done' => ['redrawDataTable' => '#dt-subscriptions'],
										],
									]);
								}
								if ($model['status'] != Subscription::STATUS_CANCELLED) {
									$actions[] = Html::a('<span class="fa fa-ban"></span>', ['subscription/cancel', 'subscriber_id' => $model['subscriber_id'], 'id' => $model['id']], [
										'class' => 'btn btn-xs btn-danger',
										'title' => Yii::t('common', 'Cancel'),
										'data' => [
											'toggle' => 'tooltip',
											'popup-action' => '',
											'popup-method' => 'POST',
											'popup-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
											'popup-done' => ['redrawDataTable' => '#dt-subscriptions'],
										],
									]);
								}
							}
						}
                        if (in_array($model['status'], [Subscription::STATUS_INCOMPLETE, Subscription::STATUS_CANCELLED])) {
                            $actions[] = Html::a('<span class="fa fa-trash"></span>', ['subscription/delete', 'subscriber_id' => $model['subscriber_id'], 'id' => $model['id']], [
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
					}, array_chunk($actions, 5));

					return implode('', $actions);
				}),
				'code' => call_user_func(function () use ($model) {
					return $model['code'] ? Html::tag('code', $model['code']) : '&mdash;';
				}),
				'billing_cycle' => call_user_func(function () use ($model) {
					if ($model['package_type'] == Package::TYPE_FREE) {
						return '&mdash;';
					}
					return Subscription::formatBillingCycle($model['billing_period'], $model['billing_cycle']);
				}),
				'package' => call_user_func(function () use ($model) {
					if ($model['parent_id']) {
						return Html::a("#{$model['parent_code']}" . ($model['package_name'] ? " ({$model['package_name']})" : ""), ['/subscriber-manager/subscription/view', 'id' => $model['parent_id']], [
							'data' => [
								'popup-action' => '',
							],
						]);
					} elseif ($model['package_name']) {
						return Html::a($model['package_name'], ['/nomenclature-manager/package/view', 'id' => $model['package_id']], [
							'data' => [
								'popup-action' => '',
							],
						]);
					}
					return '&mdash;';
				}),
				'price' => call_user_func(function () use ($model) {
					return Yii::$app->formatter->asCurrency($model['price'], $model['currency']);
				}),
				'start_at' => call_user_func(function () use ($model) {
					return $model['start_at'] ? Yii::$app->formatter->asDatetime($model['start_at']) : '&mdash;';
				}),
				'end_at' => call_user_func(function () use ($model) {
					return $model['end_at'] ? Yii::$app->formatter->asDatetime($model['end_at']) : '&mdash;';
				}),
				'status' => call_user_func(function () use ($model) {
					$status = Subscription::getStatusLabels()[$model['status']];
					return Html::tag('span', $status['label'], ['class' => 'label label-block label-' . $status['color']]);
				}),
			];
		}

		return $data;
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
				case 'package':
					$query->$filterOperator(['LIKE', 'pt.name', $value]);
					break;
					break;
				case 'start_at':
					$query->$filterOperator(['LIKE', 'sub.start_at', DateHelper::formatAsDate($value)]);
					break;
				case 'end_at':
					$query->$filterOperator(['LIKE', 'sub.end_at', DateHelper::formatAsDate($value)]);
					break;
				default:
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 'sub.' . $column['data'], $value]);
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
				case 'package':
					$query->addOrderBy(['pt.name' => $sort]);
					break;
				default:
					$query->addOrderBy(['sub.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
