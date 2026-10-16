<?php

namespace backend\modules\user\models;

use common\helpers\DateHelper;
use common\models\AuthAssignment;
use common\models\Subscriber;
use common\models\User;
use common\widgets\datatable\DataTableAction;
use Mpdf\Tag\Sub;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class SubscriberUserSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = User::find()
			->alias('u')
			->select([
				'u.id',
				'u.email',
				'u.phone',
				'u.image',
				'u.first_name',
				'u.middle_name',
				'u.last_name',
				'u.gender',
				'u.created_at',
				'u.last_activity',
				'u.status',
			])
            ->joinWith([
                'authAssignment aa' => function (ActiveQuery $query) {
                    $query->andWhere(new \yii\db\Expression('IF([[aa.item_name]] = "superAdmin", 1, 0) = 0'));
                },
                'subscriber sub',
                'subscriber.subscriptions subs',
            ])
			->andWhere(['!=', 'u.id', Yii::$app->user->id])
            ->andWhere(['IS NOT', 'subs.id', null])
			->andWhere([
				'u.deleted' => isset($this->requestParams['deleted']) ? $this->requestParams['deleted'] : User::NO,
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			User::class => [
				'id',
				'action' => function (User $model) {
					$actions = [];

					if ($this->requestParams['deleted'] == User::YES) {
						if (Yii::$app->user->can('restoreUser')) {
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
						if (Yii::$app->user->can('deleteUser')) {
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
						if (Yii::$app->user->can('viewUser')) {
							$actions[] = Html::a('<span class="fa fa-eye"></span>', ['view', 'id' => $model->id], [
								'class' => 'action-view btn btn-xs btn-info',
								'title' => Yii::t('common', 'View'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('updateUser')) {
							$actions[] = Html::a('<span class="fa fa-edit"></span>', ['update', 'id' => $model->id], [
								'class' => 'action-update btn btn-xs btn-primary',
								'title' => Yii::t('common', 'Update'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('deleteUser')) {
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
				'image' => function (User $model) {
					if ($model->image && is_file(Yii::getAlias("@uploads/user/{$model->uuid}/{$model->image}"))) {
						$imgTag = Html::img($model->getImageUrl(), [
							'class' => 'img-responsive',
							'alt' => $model->getFullName(),
						]);
						return Html::a($imgTag, $model->getImageUrl(), [
							'title' => Yii::t('common', 'Open Gallery'),
							'data' => [
								'toggle' => 'tooltip',
								'fancybox' => 'users',
								'caption' => $model->getFullName(),
							],
						]);
					}
					return '&mdash;';
				},
				'name' => function (User $model) {
					return $model->getFullName() ?: '&mdash;';
				},
				'email' => function (User $model) {
					return $model->email ?: '&mdash;';
				},
				'phone' => function (User $model) {
					return $model->phone ?: '&mdash;';
				},
				'created_at' => function (User $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
				'last_activity' => function (User $model) {
					return $model->last_activity ? Yii::$app->formatter->asDatetime($model->last_activity) : '&mdash;';
				},
				'status' => function (User $model) {
					$status = User::getStatusLabels()[$model->status];
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
					$query->$filterOperator([
						'OR',
						['LIKE', 'u.first_name', $value],
						['LIKE', 'u.middle_name', $value],
						['LIKE', 'u.last_name', $value],
					]);
					break;
				case 'created_at':
					$query->$filterOperator(['LIKE', 'u.created_at', DateHelper::formatAsDate($value)]);
					break;
				case 'last_activity':
					$query->$filterOperator(['LIKE', 'u.last_activity', DateHelper::formatAsDate($value)]);
					break;
				default:
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 'u.' . $column['data'], $value]);
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
				case 'name':
					$query->addOrderBy([
						'u.first_name' => $sort,
						'u.middle_name' => $sort,
						'u.last_name' => $sort,
					]);
					break;
				default:
					$query->addOrderBy(['u.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
