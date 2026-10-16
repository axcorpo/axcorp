<?php

namespace frontend\modules\account\models;

use common\helpers\DateHelper;
use common\models\PackageTranslation;
use common\models\Workspace;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\bootstrap\Dropdown;
use yii\db\ActiveQuery;
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
				'w.created_at',
				'w.status',
				'w.deleted',
			])
			->joinWith([
				'subscription sub',
				'subscription.package.packageTranslations pt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'pt.language_id' => Yii::$app->language,
						'pt.deleted' => PackageTranslation::NO,
					]);
				},
				'subscription.subscriber s' => function (ActiveQuery $query) {
					$query->andWhere(['s.user_id' => Yii::$app->user->id]);
				},
			])
			->andWhere([
				'w.status' => Workspace::STATUS_ACTIVE,
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

					$actions[] = [
						'label' => '<span class="action-icon fa fa-eye color-info"></span> ' . Yii::t('common', 'View'),
						'url' => ['view', 'id' => $model->id],
						'linkOptions' => [
							'data' => [
								'popup-action' => '',
							],
						],
					];
					$actions[] = [
						'label' => '<span class="action-icon fa fa-edit color-primary"></span> ' . Yii::t('common', 'Update'),
						'url' => ['update', 'id' => $model->id],
						'linkOptions' => [
							'data' => [
								'popup-action' => '',
								'popup-done' => ['redrawDataTable' => '#dt-workspaces'],
							],
						],
					];

					$content = [];
					$content[] = Html::beginTag('div', ['class' => 'dropdown']);
					$content[] = Html::tag('button', '<span class="fa fa-ellipsis-v"></span>', [
						'class' => 'dropdown-toggle btn btn-block btn-xs btn-light btn-slide-right',
						'data' => [
							'toggle' => 'dropdown',
							// Bootstrap 5 listens on data-bs-toggle; the legacy attribute stays
							// for the styles and scripts that still key on it.
							'bs-toggle' => 'dropdown',
						],
					]);
					$content[] = Dropdown::widget(['items' => $actions, 'encodeLabels' => false]);
					$content[] = Html::endTag('div');

					return $actions ? implode('', $content) : '&mdash;';
				},
				'code' => function (Workspace $model) {
					return $model->code ? Html::tag('code', $model->code) : '&mdash;';
				},
				'subscription' => function (Workspace $model) {
					if ($subscription = $model->subscription) {
						return Html::a($subscription->formattedName, ['/account/subscription/view', 'id' => $subscription->id], [
							'data' => [
								'popup-action' => '',
							],
						]);
					}
					return '&mdash;';
				},
				'url' => function (Workspace $model) {
					if ($model->url) {
						return Html::a($model->getAbsoluteUrl(), $model->getAbsoluteUrl(), ['target' => '_blank']);
					}
					return '&mdash;';
				},
				'created_at' => function (Workspace $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
				'status' => function (Workspace $model) {
					$status = Workspace::getStatusLabels()[$model->status];
					return Html::tag('span', $status['label'], ['class' => 'label label-' . $status['color']]);
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
				case 'subscription':
					$query->$filterOperator([
						'OR',
						['LIKE', 'pt.name', $value],
						['LIKE', 'sub.code', $value],
					]);
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
				case 'subscription':
					$query->addOrderBy(['sub.code' => $sort]);
					break;
				default:
					$query->addOrderBy(['w.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
