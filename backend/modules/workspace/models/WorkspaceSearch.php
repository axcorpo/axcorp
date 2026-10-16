<?php

namespace backend\modules\workspace\models;

use common\helpers\DateHelper;
use common\models\PackageTranslation;
use common\models\Subscription;
use common\models\Workspace;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class WorkspaceSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = Workspace::find()
			->alias('w')
			->select([
				'w.id',
				'w.subscription_id',
				'w.code',
				'w.url',
				'w.type',
				'w.created_at',
				'w.status',
				'w.deleted',
			])
			->joinWith([
				'subscription s',
				'subscription.subscriber.user u',
				'subscription.package.packageTranslations pt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'pt.language_id' => Yii::$app->language,
						'pt.deleted' => PackageTranslation::NO,
					]);
				},
			])
			->where([
				'w.deleted' => isset($this->requestParams['deleted']) ? $this->requestParams['deleted'] : Workspace::NO,
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			Workspace::class => [
				'id',
				'action' => function (Workspace $model) {
					$actions = [];

					if ($this->requestParams['deleted'] == Workspace::YES) {
						if (Yii::$app->user->can('restoreWorkspace')) {
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
						if (Yii::$app->user->can('deleteWorkspace')) {
							$actions[] = Html::a('<span class="fa fa-trash"></span>', ['delete', 'id' => $model->id], [
								'class' => 'action-delete btn btn-xs btn-danger',
								'title' => Yii::t('common', 'Delete'),
								'data' => [
									'toggle' => 'tooltip',
									'dt-operation' => 'delete-permanently',
									'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							]);
						}
					} else {
						if (Yii::$app->user->can('viewWorkspace')) {
							$actions[] = Html::a('<span class="fa fa-eye"></span>', ['view', 'id' => $model->id], [
								'class' => 'action-view btn btn-xs btn-info',
								'title' => Yii::t('common', 'View'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('updateWorkspace')) {
							$actions[] = Html::a('<span class="fa fa-edit"></span>', ['update', 'id' => $model->id], [
								'class' => 'action-update btn btn-xs btn-primary',
								'title' => Yii::t('common', 'Update'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
							$actions[] = Html::a('<span class="fa fa-undo"></span>', ['reinstall', 'id' => $model->id], [
								'class' => 'action-update btn btn-xs btn-warning',
								'title' => Yii::t('common', 'Reinstall Application'),
								'data' => [
									'toggle' => 'tooltip',
									'dt-operation' => 'reinstall',
									'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							]);
						}
						if (Yii::$app->user->can('deleteWorkspace')) {
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
					}, array_chunk($actions, 4));

					return implode('', $actions);
				},
				'code' => function (Workspace $model) {
					return $model->code ? Html::tag('code', $model->code) : '&mdash;';
				},
				'url' => function (Workspace $model) {
					return $model->url ? Html::a($model->getAbsoluteUrl(), $model->getAbsoluteUrl(), ['target' => '_blank']) : '&mdash;';
				},
				'subscriber' => function (Workspace $model) {
					if ($model->subscription && ($subscriber = $model->subscription->subscriber)) {
						return Html::a($subscriber->user->fullName, ['/subscriber-manager/subscriber/view', 'id' => $subscriber->id]);
					}
					return '&mdash;';
				},
				'subscription' => function (Workspace $model) {
					if ($model->subscription) {
						$content = [];
							$status = Subscription::getStatusLabels()[$model->subscription->status];
							$status = Html::tag('span', $status['label'], ['class' => 'text-' . $status['color']]);
							$startAt = $model->subscription->start_at ? Yii::$app->formatter->asDatetime($model->subscription->start_at) : '&mdash;';
							$endAt = $model->subscription->end_at ? Yii::$app->formatter->asDatetime($model->subscription->end_at) : '&mdash;';
							$featuresIcon = '';
							if (!empty($model->subscription->parent_id)) {
								$featuresIcon = Html::tag('span', null, [
									'class' => 'fa fa-share-alt',
									'title' => Yii::t('common', 'Features'),
									'data' => [
										'toggle' => 'tooltip',
									],
								]);
							}

							$content[] = Html::beginTag('div', ['class' => 'item-adjacent-spacing bordered-top']);
							$content[] = Html::tag('div', "{$model->subscription->formattedName} $featuresIcon &mdash; {$status}");
							if ($model->subscription->type == Subscription::TYPE_FREE) {
								$content[] = Html::tag('div', Yii::t('label', 'Start At') . ": {$startAt}");
							} else {
								$content[] = Html::tag('div', Yii::t('label', 'Next Due At'). ": {$endAt}");
							}
							$content[] = Html::endTag('div');
						return implode('', $content);
					}
					return '&mdash;';
				},
				'type' => function (Workspace $model) {
					return Workspace::getTypeLabels()[$model->type] ?? '&mdash;';
				},
				'created_at' => function (Workspace $model) {
					return Yii::$app->formatter->asDatetime($model->created_at);
				},
				'status' => function (Workspace $model) {
					$status = Workspace::getStatusLabels()[$model->status];
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
				$value = $search['value'];
				$filterOperator = 'orFilterWhere';
			} else {
				$value = $column['search']['value'];
				$filterOperator = 'andFilterWhere';
			}

			switch ($column['data']) {
				case 'subscriber':
					$query->$filterOperator([
						'OR',
						['LIKE', new Expression('CONCAT_WS(" ", u.first_name, u.middle_name, u.last_name)') , $value],
						['LIKE', new Expression('CONCAT_WS(" ", u.first_name, u.last_name)') , $value],
						['LIKE', new Expression('CONCAT_WS(" ", u.middle_name, u.last_name)') , $value],
					]);
					break;
				case 'subscription':
					$query->$filterOperator(['LIKE', 'pt.name', $value]);
					break;
				case 'created_at':
					$query->$filterOperator(['LIKE', 'w.created_at', DateHelper::formatAsDate($value)]);
					break;
				default:
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 'w.' . $column['data'], $value]);
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
				case 'subscriber':
					$query->addOrderBy([
						'u.first_name' => $sort,
						'u.middle_name' => $sort,
						'u.last_name' => $sort,
					]);
					break;
				case 'subscription':
					$query->addOrderBy(['pt.name' => $sort]);
					break;
				default:
					$query->addOrderBy(['w.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
