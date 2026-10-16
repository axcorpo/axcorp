<?php

namespace backend\modules\affiliate\models;

use common\helpers\DateHelper;
use common\models\Affiliate;
use common\models\AffiliateCommission;
use common\models\Subscriber;
use common\models\Subscription;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * DataTable of the affiliates: account, code, commission terms, active referred
 * subscribers and commission totals per currency (pending / payable / paid).
 * With `applications`, the applications from the public page instead.
 */
class AffiliateSearch extends DataTableAction
{
	use AffiliateDataTableTrait;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
		$this->assertCan('viewAffiliate');

		$deleted = $this->getFilter('deleted') == Affiliate::YES;
		$this->query = Affiliate::find()
			->alias('a')
			->joinWith(['user u'])
			->andWhere([
				'a.deleted' => $deleted ? Affiliate::YES : Affiliate::NO,
			]);
		// The applications from the public page (pending, rejected) have their own list;
		// the trash keeps everything deleted.
		if (!$deleted) {
			$applicationStatuses = [Affiliate::STATUS_PENDING, Affiliate::STATUS_REJECTED];
			$this->query->andWhere($this->getFilter('applications') == Affiliate::YES
				? ['a.status' => $applicationStatuses]
				: ['not in', 'a.status', $applicationStatuses]);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		/** @var Affiliate[] $models */
		$models = $query->all();
		$ids = ArrayHelper::getColumn($models, function (Affiliate $model) {
			return $model->getAttribute('id');
		});
		$activeSubscribers = $this->findActiveSubscriberCounts($ids);
		$totals = $this->findCommissionTotals($ids);

		return ArrayHelper::toArray($models, [
			Affiliate::class => [
				'id',
				'action' => function (Affiliate $model) {
					return $this->renderActions($model);
				},
				'name' => function (Affiliate $model) {
					return $model->user ? Html::encode($model->user->getFullName()) : '&mdash;';
				},
				'email' => function (Affiliate $model) {
					return $model->user && $model->user->email ? Html::encode($model->user->email) : '&mdash;';
				},
				'code' => function (Affiliate $model) {
					return Html::encode($model->code);
				},
				'commission_percentage' => function (Affiliate $model) {
					return Yii::$app->formatter->asDecimal($model->commission_percentage, 2) . '%';
				},
				'commission_months' => function (Affiliate $model) {
					return $model->commission_months ?: Yii::t('common', 'Lifetime');
				},
				'active_subscribers' => function (Affiliate $model) use ($activeSubscribers) {
					return (int) ($activeSubscribers[$model->getAttribute('id')] ?? 0);
				},
				'pending' => function (Affiliate $model) use ($totals) {
					return static::formatAmounts($totals[$model->getAttribute('id')][AffiliateCommission::STATUS_PENDING] ?? []);
				},
				'payable' => function (Affiliate $model) use ($totals) {
					return static::formatAmounts($totals[$model->getAttribute('id')][AffiliateCommission::STATUS_PAYABLE] ?? []);
				},
				'paid' => function (Affiliate $model) use ($totals) {
					return static::formatAmounts($totals[$model->getAttribute('id')][AffiliateCommission::STATUS_PAID] ?? []);
				},
				'created_at' => function (Affiliate $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
				'status' => function (Affiliate $model) {
					return static::statusLabel(Affiliate::getStatusLabels(), $model->status);
				},
			],
		]);
	}

	/**
	 * @param Affiliate $model
	 * @return string
	 */
	protected function renderActions(Affiliate $model)
	{
		$actions = [];
		if ((int) $model->deleted === Affiliate::YES) {
			if (Yii::$app->user->can('deleteAffiliate')) {
				$actions[] = Html::a('<span class="fa fa-undo"></span>', ['restore', 'id' => $model->uuid], [
					'class' => 'action-view btn btn-xs btn-success',
					'title' => Yii::t('common', 'Restore'),
					'data' => [
						'toggle' => 'tooltip',
						'dt-operation' => 'restore',
						'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
					],
				]);
			}
		} else {
			$actions[] = Html::a('<span class="fa fa-eye"></span>', ['view', 'id' => $model->uuid], [
				'class' => 'action-view btn btn-xs btn-info',
				'title' => Yii::t('common', 'View'),
				'data' => [
					'toggle' => 'tooltip',
				],
			]);
			if (Yii::$app->user->can('updateAffiliate')) {
				$actions[] = Html::a('<span class="fa fa-edit"></span>', ['update', 'id' => $model->uuid], [
					'class' => 'action-update btn btn-xs btn-primary',
					'title' => Yii::t('common', 'Update'),
					'data' => [
						'toggle' => 'tooltip',
					],
				]);
			}
			if (Yii::$app->user->can('deleteAffiliate')) {
				$actions[] = Html::a('<span class="fa fa-trash"></span>', ['delete', 'id' => $model->uuid], [
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
	}

	/**
	 * Attributed subscribers with at least one active, non-deleted subscription.
	 *
	 * @param string[] $ids raw affiliate ids
	 * @return array raw id => count
	 */
	protected function findActiveSubscriberCounts(array $ids)
	{
		if (!$ids) {
			return [];
		}
		return (new Query())
			->select(['count' => 'COUNT(DISTINCT [[s.id]])', 's.affiliate_id'])
			->from(['s' => Subscriber::tableName()])
			->innerJoin(['sub' => Subscription::tableName()], '[[sub.subscriber_id]] = [[s.id]]')
			->where([
				's.affiliate_id' => $ids,
				's.deleted' => Subscriber::NO,
				'sub.status' => Subscription::STATUS_ACTIVE,
				'sub.deleted' => Subscription::NO,
			])
			->groupBy('s.affiliate_id')
			->indexBy('affiliate_id')
			->column();
	}

	/**
	 * @param string[] $ids raw affiliate ids
	 * @return array raw id => status => currency => total (cancelled left out)
	 */
	protected function findCommissionTotals(array $ids)
	{
		if (!$ids) {
			return [];
		}
		$rows = (new Query())
			->select(['affiliate_id', 'status', 'currency', 'total' => 'SUM([[amount]])'])
			->from(AffiliateCommission::tableName())
			->where(['affiliate_id' => $ids])
			->andWhere(['!=', 'status', AffiliateCommission::STATUS_CANCELLED])
			->groupBy(['affiliate_id', 'status', 'currency'])
			->all();
		$totals = [];
		foreach ($rows as $row) {
			$totals[$row['affiliate_id']][(int) $row['status']][$row['currency']] = $row['total'];
		}
		return $totals;
	}

	/**
	 * Amounts per currency, one per line (never converted — totals stay per currency).
	 *
	 * @param array $amounts currency => amount
	 * @return string
	 */
	public static function formatAmounts(array $amounts)
	{
		if (!$amounts) {
			return '&mdash;';
		}
		ksort($amounts);
		$lines = [];
		foreach ($amounts as $currency => $amount) {
			$lines[] = Html::encode(Yii::$app->formatter->asDecimal($amount, 2) . ' ' . $currency);
		}
		return implode('<br>', $lines);
	}

	/**
	 * @inheritdoc
	 */
	public function applyFilter(ActiveQuery $query, $columns, $search)
	{
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
				case 'email':
					$query->$filterOperator(['LIKE', 'u.email', $value]);
					break;
				case 'created_at':
					$query->$filterOperator(['LIKE', 'a.created_at', DateHelper::formatAsDate($value)]);
					break;
				case 'code':
				case 'commission_percentage':
				case 'commission_months':
					$query->$filterOperator(['LIKE', 'a.' . $column['data'], $value]);
					break;
				case 'status':
					$this->applyStatusFilter($query, $filterOperator, $value, 'a.status');
					break;
				default:
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
		foreach ($order as $item) {
			$column = $columns[$item['column']];
			if (array_key_exists('orderable', $column) && $column['orderable'] === 'false') {
				continue;
			}
			$sort = mb_strtolower($item['dir']) == 'desc' ? SORT_DESC : SORT_ASC;

			switch ($column['data']) {
				case 'name':
					$query->addOrderBy([
						'u.first_name' => $sort,
						'u.last_name' => $sort,
					]);
					break;
				case 'email':
					$query->addOrderBy(['u.email' => $sort]);
					break;
				case 'code':
				case 'commission_percentage':
				case 'commission_months':
				case 'created_at':
				case 'status':
					$query->addOrderBy(['a.' . $column['data'] => $sort]);
					break;
				default:
					break;
			}
		}
		return $query;
	}
}
