<?php

namespace frontend\modules\account\models;

use common\helpers\DateHelper;
use common\models\Package;
use common\models\PackageTranslation;
use common\models\Subscriber;
use common\models\Subscription;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\bootstrap\Dropdown;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\Html;

class PackageSubscriptionSearch extends DataTableAction
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
				'sub.trial_period',
				'sub.trial_cycle',
				'sub.billing_period',
				'sub.billing_cycle',
				'sub.start_at',
				'sub.end_at',
				'sub.price',
				'sub.currency',
				'sub.status',
				'p.type AS package_type',
				'pt.name AS package_name',
			])
			->joinWith([
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
				'subscriber s' => function (ActiveQuery $query) {
					$query->andWhere([
						's.user_id' => Yii::$app->user->id,
						's.deleted' => Subscriber::NO,
					]);
				},
			])
			->andWhere(['IS', 'sub.parent_id', null])
			->andWhere([
				'sub.deleted' => Subscription::NO,
			])
			// An incomplete subscription is a checkout that was started and not paid;
			// it stays out of the list until the payment completes.
			->andWhere(['!=', 'sub.status', Subscription::STATUS_INCOMPLETE]);
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
				'id' => $model['id'],
				'package_id' => $model['package_id'],
				'action' => call_user_func(function () use ($model) {
					$actions = [];
					$items = [
						'view' => [
							'label' => '<span class="action-icon fa fa-eye color-info"></span> ' . Yii::t('common', 'View'),
							'url' => ['view', 'id' => $model['id']],
							'linkOptions' => [
								'data' => [
									'popup-action' => '',
								],
							],
						],
						'update' => [
							'label' => '<span class="action-icon fa fa-edit color-primary"></span> ' . Yii::t('common', 'Update'),
							'url' => ['update', 'id' => $model['id']],
							'linkOptions' => [
								'data' => [
									'popup-action' => '',
									'popup-done' => ['redrawDataTable' => '#dt-subscriptions'],
								],
							],
						],
						'cancel' => [
							'label' => '<span class="action-icon fa fa-ban color-danger"></span> ' . Yii::t('common', 'Cancel'),
							'url' => ['cancel', 'id' => $model['id']],
							'linkOptions' => [
								'data' => [
									'method' => 'POST',
									'confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							],
						],
						'reactivate' => [
							'label' => '<span class="action-icon fa fa-refresh color-success"></span> ' . Yii::t('common', 'Reactivate'),
							'url' => ['reactivate', 'id' => $model['id']],
							'linkOptions' => [
								'data' => [
									'method' => 'POST',
									'confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							],
						],
						'payment' => [
							'label' => '<span class="action-icon fa fa-check color-success"></span> ' . Yii::t('common', 'Activate'),
							'url' => ['payment/subscription', 'id' => Yii::$app->security->maskToken((string) $model['id'])],
						],
					];
					$isOnTrialPeriod = !empty($model['trial_period']) && !empty($model['trial_cycle']) && (new \DateTime($model['end_at']) > new \DateTime());

					$actions[] = $items['view'];
					if ($model['status'] == Subscription::STATUS_ACTIVE) {
						$actions[] = $items['update'];
					}
					if ($model['status'] != Subscription::STATUS_CANCELLED) {
						$actions[] = $items['cancel'];
					} else {
						$currentDate = date('Y-m-d H:i:s');
						if ($currentDate < $model['end_at']) {
							$actions[] = $items['reactivate'];
						}
					}
					if ($isOnTrialPeriod || in_array($model['status'], [Subscription::STATUS_INACTIVE, Subscription::STATUS_SUSPENDED, Subscription::STATUS_INCOMPLETE])) {
						$actions[] = $items['payment'];
					}

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
				}),
				'code' => call_user_func(function () use ($model) {
					return $model['code'] ? Html::tag('code', $model['code']) : '&mdash;';
				}),
				'package' => call_user_func(function () use ($model) {
					return $model['package_name'] ?: '&mdash;';
				}),
				'price' => call_user_func(function () use ($model) {
					if ($model['package_type'] == Package::TYPE_FREE) {
						return '&mdash;';
					}
					return implode(' ', array_filter([
						Yii::$app->formatter->asCurrency($model['price'], $model['currency']),
						mb_strtolower(Subscription::formatBillingCycle($model['billing_period'], $model['billing_cycle'])),
					]));
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
