<?php

namespace backend\modules\affiliate\models;

use common\helpers\DateHelper;
use common\models\AffiliatePayout;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * DataTable of the payouts to affiliates.
 *
 * Fixed context: affiliate_id; the rest are the DataTable column filters (affiliate /
 * status as selects, dates in the `dateFormat` setting).
 */
class PayoutSearch extends DataTableAction
{
	use AffiliateDataTableTrait;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
		$this->assertCan('manageAffiliatePayout');

		$this->query = AffiliatePayout::find()
			->alias('p')
			->joinWith(['affiliate a', 'affiliate.user au'])
			->andWhere(['p.deleted' => AffiliatePayout::NO]);

		// The fixed context of the affiliate view; the rest are column filters.
		$this->query->andFilterWhere(['p.affiliate_id' => $this->getUuidFilter('affiliate_id')]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			AffiliatePayout::class => [
				'id',
				'action' => function (AffiliatePayout $model) {
					return Html::tag('div', Html::a('<span class="fa fa-eye"></span>', ['/affiliate-manager/payout/view', 'id' => $model->uuid], [
						'class' => 'action-view btn btn-xs btn-info',
						'title' => Yii::t('common', 'View'),
						'data' => [
							'toggle' => 'tooltip',
						],
					]));
				},
				'affiliate' => function (AffiliatePayout $model) {
					if (!$model->affiliate) {
						return '&mdash;';
					}
					return Html::encode(trim(($model->affiliate->user ? $model->affiliate->user->getFullName() : '') . ' (' . $model->affiliate->code . ')'));
				},
				'amount' => function (AffiliatePayout $model) {
					return Yii::$app->formatter->asDecimal($model->amount, 2);
				},
				'currency' => function (AffiliatePayout $model) {
					return Html::encode($model->currency);
				},
				'invoice' => function (AffiliatePayout $model) {
					if (!$model->invoice_number) {
						return '&mdash;';
					}
					$status = AffiliatePayout::getInvoiceStatusLabels()[$model->invoice_status] ?? null;
					return Html::encode($model->invoice_number)
						. ($model->invoice_date ? ' / ' . Yii::$app->formatter->asDate($model->invoice_date) : '')
						. ($status ? ' ' . Html::tag('span', $status['label'], ['class' => 'label label-' . $status['color']]) : '');
				},
				'created_at' => function (AffiliatePayout $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
				'paid_at' => function (AffiliatePayout $model) {
					return $model->paid_at ? Yii::$app->formatter->asDatetime($model->paid_at) : '&mdash;';
				},
				'status' => function (AffiliatePayout $model) {
					return static::statusLabel(AffiliatePayout::getStatusLabels(), $model->status);
				},
			],
		]);
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
				case 'affiliate':
					$this->applyRelationFilter($query, $filterOperator, $value, 'p.affiliate_id', [
						['LIKE', 'a.code'],
						['LIKE', 'au.first_name'],
						['LIKE', 'au.last_name'],
					]);
					break;
				case 'invoice':
					$query->$filterOperator(['LIKE', 'p.invoice_number', $value]);
					break;
				case 'created_at':
				case 'paid_at':
					$query->$filterOperator(['LIKE', 'p.' . $column['data'], DateHelper::formatAsDate($value)]);
					break;
				case 'amount':
				case 'currency':
					$query->$filterOperator(['LIKE', 'p.' . $column['data'], $value]);
					break;
				case 'status':
					$this->applyStatusFilter($query, $filterOperator, $value, 'p.status');
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
				case 'affiliate':
					$query->addOrderBy(['a.code' => $sort]);
					break;
				case 'invoice':
					$query->addOrderBy(['p.invoice_number' => $sort]);
					break;
				case 'amount':
				case 'currency':
				case 'created_at':
				case 'paid_at':
				case 'status':
					$query->addOrderBy(['p.' . $column['data'] => $sort]);
					break;
				default:
					break;
			}
		}
		return $query;
	}
}
