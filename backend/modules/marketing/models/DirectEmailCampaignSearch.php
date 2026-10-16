<?php

namespace backend\modules\marketing\models;

use common\helpers\DateHelper;
use common\models\MarketingRecipient;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class DirectEmailCampaignSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = DirectMarketingCampaign::find()
			->alias('mc')
			->select([
				'mc.id',
				'mc.type',
				'mc.variant',
				'mc.frequency',
				'mc.cycle',
				'mc.start_at',
				'mc.end_at',
				'mc.created_by',
				'mc.updated_by',
				'mc.created_at',
				'mc.updated_at',
				'mc.status',
			])
			->joinWith([
				'marketingCampaignTranslations mct' => function (ActiveQuery $query) {
					$query->andOnCondition(['mct.language_id' => Yii::$app->language]);
				},
//				'marketingCampaignHasRecipients mchr', // TODO: check if this should be used (eager loaded) and check the performance impact
				'marketingRecipients mr' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'mr.status' => MarketingRecipient::STATUS_ACTIVE,
						'mr.deleted' => MarketingRecipient::NO,
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
				'mc.type' => DirectMarketingCampaign::TYPE_DIRECT,
				'mc.variant' => DirectMarketingCampaign::VARIANT_EMAIL,
				'mc.deleted' => isset($this->requestParams['deleted']) ? $this->requestParams['deleted'] : DirectMarketingCampaign::NO,
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			DirectMarketingCampaign::class => [
				'id',
				'action' => function (DirectMarketingCampaign $model) {
					$actions = [];

					if ($this->requestParams['deleted'] == DirectMarketingCampaign::YES) {
						if (Yii::$app->user->can('restoreDirectMarketingCampaign')) {
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
						if (Yii::$app->user->can('deleteDirectMarketingCampaign')) {
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
						if (Yii::$app->user->can('viewDirectMarketingCampaign')) {
							$actions[] = Html::a('<span class="fa fa-eye"></span>', ['view', 'id' => $model->id], [
								'class' => 'action-view btn btn-xs btn-info',
								'title' => Yii::t('common', 'View'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('updateDirectMarketingCampaign')) {
							$actions[] = Html::a('<span class="fa fa-edit"></span>', ['update', 'id' => $model->id], [
								'class' => 'action-update btn btn-xs btn-primary',
								'title' => Yii::t('common', 'Update'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('deleteDirectMarketingCampaign')) {
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
						if (Yii::$app->user->can('updateDirectMarketingCampaign')) {
							$startAction = Html::a('<span class="fa fa-play"></span>', ['start', 'id' => $model->id], [
								'class' => 'action-start btn btn-xs btn-success',
								'title' => Yii::t('common', 'Start'),
								'data' => [
									'toggle' => 'tooltip',
									'dt-operation' => 'start',
									'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							]);
							$restartAction = Html::a('<span class="fa fa-refresh"></span>', ['restart', 'id' => $model->id], [
								'class' => 'action-start btn btn-xs btn-success',
								'title' => Yii::t('common', 'Restart'),
								'data' => [
									'toggle' => 'tooltip',
									'dt-operation' => 'restart',
									'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							]);
							$pauseAction = Html::a('<span class="fa fa-pause"></span>', ['pause', 'id' => $model->id], [
								'class' => 'action-start btn btn-xs btn-warning',
								'title' => Yii::t('common', 'Pause'),
								'data' => [
									'toggle' => 'tooltip',
									'dt-operation' => 'pause',
									'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							]);
							$stopAction = Html::a('<span class="fa fa-stop"></span>', ['stop', 'id' => $model->id], [
								'class' => 'aaction-stop btn btn-xs yellow-casablanca',
								'title' => Yii::t('common', 'Stop'),
								'data' => [
									'toggle' => 'tooltip',
									'dt-operation' => 'stop',
									'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							]);

							switch ($model->status) {
								case DirectMarketingCampaign::STATUS_INACTIVE:
								case DirectMarketingCampaign::STATUS_PAUSED:
									$actions[] = $startAction;
									if ($model->status == DirectMarketingCampaign::STATUS_PAUSED) {
										$actions[] = $stopAction;
									}
									break;
								case DirectMarketingCampaign::STATUS_ACTIVE:
									$actions[] = $pauseAction;
									$actions[] = $stopAction;
									break;
								case DirectMarketingCampaign::STATUS_COMPLETE:
									$actions[] = $restartAction;
									break;
								default: break;
							}
						}
					}

					// Split actions in multiple rows
					$actions = array_map(function ($action) {
						return Html::tag('div', implode('', $action));
					}, array_chunk($actions, 5));

					return implode('', $actions);
				},
				'name' => function (DirectMarketingCampaign $model) {
					return $model->translation->name ?: '&mdash;';
				},
				'recipients' => function (DirectMarketingCampaign $model) {
					return count($model->marketingRecipients);
				},
				'frequency' => function (DirectMarketingCampaign $model) {
					return $model->getFormattedFrequency();
				},
				'progress' => function (DirectMarketingCampaign $model) {
					return $model->getFormattedProgress();
				},
				'start_at' => function (DirectMarketingCampaign $model) {
					return $model->start_at ? Yii::$app->formatter->asDatetime($model->start_at) : '&mdash;';
				},
				'end_at' => function (DirectMarketingCampaign $model) {
					return $model->end_at ? Yii::$app->formatter->asDatetime($model->end_at) : '&mdash;';
				},
				'created_by' => function (DirectMarketingCampaign $model) {
					return $model->creator ? $model->creator->fullName : '&mdash;';
				},
				'created_at' => function (DirectMarketingCampaign $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
				'updated_at' => function (DirectMarketingCampaign $model) {
					return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
				},
				'status' => function (DirectMarketingCampaign $model) {
					$status = DirectMarketingCampaign::getStatusLabels()[$model->status];
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
					$query->$filterOperator(['LIKE', 'mct.name', $value]);
					break;
				case 'created_by':
					$query->$filterOperator([
						'OR',
						['LIKE', 'cr.first_name', $value],
						['LIKE', 'cr.middle_name', $value],
						['LIKE', 'cr.last_name', $value],
					]);
					break;
				case 'start_at':
					$query->$filterOperator(['LIKE', 'mc.start_at', DateHelper::formatAsDate($value)]);
					break;
				case 'end_at':
					$query->$filterOperator(['LIKE', 'mc.end_at', DateHelper::formatAsDate($value)]);
					break;
				case 'created_at':
					$query->$filterOperator(['LIKE', 'mc.created_at', DateHelper::formatAsDate($value)]);
					break;
				case 'updated_at':
					$query->$filterOperator(['LIKE', 'mc.updated_at', DateHelper::formatAsDate($value)]);
					break;
				default:
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 'mc.' . $column['data'], $value]);
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
					$query->addOrderBy(['mct.name' => $sort]);
					break;
				case 'created_by':
					$query->addOrderBy([
						'cr.first_name' => $sort,
						'cr.middle_name' => $sort,
						'cr.last_name' => $sort,
					]);
					break;
				default:
					$query->addOrderBy(['mc.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
