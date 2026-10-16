<?php

namespace backend\modules\affiliate\models;

use common\helpers\DateHelper;
use common\models\AffiliateCommission;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * DataTable of the affiliate commissions, one per master invoice (negative ones offset a
 * storno). Selection feeds the bulk approve / cancel operations.
 *
 * Fixed context: affiliate_id, payout_id; the rest are the DataTable column filters
 * (affiliate / status as selects, dates in the `dateFormat` setting).
 */
class CommissionSearch extends DataTableAction
{
	use AffiliateDataTableTrait;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
		$this->assertCan('viewAffiliate');

		$this->query = AffiliateCommission::find()
			->alias('c')
			->joinWith(['affiliate a', 'affiliate.user au', 'invoice i', 'subscriber s', 'subscriber.user su', 'payout p']);

		// The fixed context of the affiliate / payout views; the rest are column filters.
		$this->query->andFilterWhere(['c.affiliate_id' => $this->getUuidFilter('affiliate_id')]);
		$this->query->andFilterWhere(['c.affiliate_payout_id' => $this->getUuidFilter('payout_id')]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		$canManagePayouts = Yii::$app->user->can('manageAffiliatePayout');

		return ArrayHelper::toArray($query->all(), [
			AffiliateCommission::class => [
				'id',
				'affiliate' => function (AffiliateCommission $model) {
					if (!$model->affiliate) {
						return '&mdash;';
					}
					$label = Html::encode(trim(($model->affiliate->user ? $model->affiliate->user->getFullName() : '') . ' (' . $model->affiliate->code . ')'));
					return Html::a($label, ['/affiliate-manager/affiliate/view', 'id' => $model->affiliate->uuid]);
				},
				'invoice' => function (AffiliateCommission $model) {
					if (!$model->invoice) {
						return '&mdash;';
					}
					$number = trim($model->invoice->document_series . ' ' . $model->invoice->document_number) ?: '#';
					$type = $model->getIsRectify() ? ' <span class="label label-warning">' . Yii::t('common', 'Rectify') . '</span>' : '';
					return Html::encode($number) . $type;
				},
				'subscriber' => function (AffiliateCommission $model) {
					$user = $model->subscriber ? $model->subscriber->user : null;
					if (!$user) {
						return '&mdash;';
					}
					$label = Html::encode($user->getFullName() ?: $user->email);
					return Yii::$app->user->can('viewSubscriber')
						? Html::a($label, ['/subscriber-manager/subscriber/view', 'id' => $model->subscriber->uuid])
						: $label;
				},
				'base_amount' => function (AffiliateCommission $model) {
					return Yii::$app->formatter->asDecimal($model->base_amount, 2);
				},
				'percentage' => function (AffiliateCommission $model) {
					return Yii::$app->formatter->asDecimal($model->percentage, 2) . '%';
				},
				'amount' => function (AffiliateCommission $model) {
					return Yii::$app->formatter->asDecimal($model->amount, 2);
				},
				'currency' => function (AffiliateCommission $model) {
					return Html::encode($model->currency);
				},
				'earned_at' => function (AffiliateCommission $model) {
					return $model->earned_at ? Yii::$app->formatter->asDatetime($model->earned_at) : '&mdash;';
				},
				'payable_at' => function (AffiliateCommission $model) {
					return $model->payable_at ? Yii::$app->formatter->asDatetime($model->payable_at) : '&mdash;';
				},
				'payout' => function (AffiliateCommission $model) use ($canManagePayouts) {
					if (!$model->payout) {
						return '&mdash;';
					}
					$label = Html::encode(Yii::$app->formatter->asDecimal($model->payout->amount, 2) . ' ' . $model->payout->currency);
					return $canManagePayouts ? Html::a($label, ['/affiliate-manager/payout/view', 'id' => $model->payout->uuid]) : $label;
				},
				'status' => function (AffiliateCommission $model) {
					return static::statusLabel(AffiliateCommission::getStatusLabels(), $model->status);
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
					$this->applyRelationFilter($query, $filterOperator, $value, 'c.affiliate_id', [
						['LIKE', 'a.code'],
						['LIKE', 'au.first_name'],
						['LIKE', 'au.last_name'],
					]);
					break;
				case 'invoice':
					$query->$filterOperator([
						'OR',
						['LIKE', 'i.document_series', $value],
						['LIKE', 'i.document_number', $value],
						['LIKE', new Expression('CONCAT_WS(" ", [[i.document_series]], [[i.document_number]])'), $value],
					]);
					break;
				case 'subscriber':
					$query->$filterOperator([
						'OR',
						['LIKE', 'su.first_name', $value],
						['LIKE', 'su.last_name', $value],
						['LIKE', 'su.email', $value],
					]);
					break;
				case 'earned_at':
				case 'payable_at':
					$query->$filterOperator(['LIKE', 'c.' . $column['data'], DateHelper::formatAsDate($value)]);
					break;
				case 'base_amount':
				case 'percentage':
				case 'amount':
				case 'currency':
					$query->$filterOperator(['LIKE', 'c.' . $column['data'], $value]);
					break;
				case 'status':
					$this->applyStatusFilter($query, $filterOperator, $value, 'c.status');
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
					$query->addOrderBy(['i.document_series' => $sort, 'i.document_number' => $sort]);
					break;
				case 'subscriber':
					$query->addOrderBy(['su.first_name' => $sort, 'su.last_name' => $sort]);
					break;
				case 'base_amount':
				case 'percentage':
				case 'amount':
				case 'currency':
				case 'earned_at':
				case 'payable_at':
				case 'status':
					$query->addOrderBy(['c.' . $column['data'] => $sort]);
					break;
				default:
					break;
			}
		}
		return $query;
	}
}
