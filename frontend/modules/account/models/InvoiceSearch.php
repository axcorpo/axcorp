<?php

namespace frontend\modules\account\models;

use common\helpers\DateHelper;
use common\models\Invoice;
use common\models\Item;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\bootstrap\Dropdown;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class InvoiceSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
		$paidProforms = ArrayHelper::getColumn(Invoice::getProformPaidInvoices(), 'parent_id');
		$this->query = Invoice::find()
			->alias('i')
			->select([
				'i.id',
				'i.document_series',
				'i.document_number',
				'i.issued_at',
				'i.paid_at',
				'i.amount',
				'i.currency',
				'i.status',
			])
			->joinWith([
				'items itm' => function (ActiveQuery $query) {
					$query->andWhere([
						'itm.deleted' => Item::NO,
					]);
				},
			])
			->andWhere([
				'i.subscriber_id' => Yii::$app->user->identity->subscriber->id,
				'i.deleted' => Invoice::NO,
			]);
		if (!empty($paidProforms)) {
			$this->query->andWhere([
				'NOT IN', 'i.id', $paidProforms
			]);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			Invoice::class => [
				'id',
				'action' => function (Invoice $model) {
					$actions = [];

					$actions[] = [
						'label' => '<span class="action-icon fa fa-eye color-info"></span> ' . Yii::t('common', 'View'),
						'url' => ['invoice/view', 'id' => $model->id],
						'linkOptions' => [
							'target' => '_blank',
						],
					];
					if ($model->status == Invoice::STATUS_UNPAID) {
						$actions[] = [
							'label' => '<span class="action-icon fa fa-dollar color-success"></span> ' . Yii::t('common', 'Pay'),
							'url' => ['payment/invoice', 'id' => Yii::$app->security->maskToken((string) $model->id)],
							'linkOptions' => [],
						];
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
				},
				'document_number' => function (Invoice $model) {
					return $model->getDocumentSeriesNumber() ?: '&mdash;';
				},
				'issued_at' => function (Invoice $model) {
					return $model->issued_at ? Yii::$app->formatter->asDatetime($model->issued_at) : '&mdash;';
				},
				'paid_at' => function (Invoice $model) {
					return $model->paid_at ? Yii::$app->formatter->asDatetime($model->paid_at) : '&mdash;';
				},
				'amount' => function (Invoice $model) {
					return Yii::$app->formatter->asCurrency($model->amount, $model->currency);
				},
				'status' => function (Invoice $model) {
					$status = Invoice::getStatusLabels()[$model->status];
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
				case 'document_number':
					$query->$filterOperator([
						'OR',
						['LIKE', 'i.document_series', $value],
						['LIKE', 'i.document_number', $value],
						['LIKE', new Expression('CONCAT_WS(" ", [[i.document_series]], LPAD([[i.document_number]], 5, 0))'), $value],
					]);
					break;
				case 'issued_at':
					$query->$filterOperator(['LIKE', 'i.issued_at', DateHelper::formatAsDate($value)]);
					break;
				case 'due_at':
					$query->$filterOperator(['LIKE', 'i.due_at', DateHelper::formatAsDate($value)]);
					break;
				case 'paid_at':
					$query->$filterOperator(['LIKE', 'i.paid_at', DateHelper::formatAsDate($value)]);
					break;
				default:
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 'i.' . $column['data'], $value]);
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
				case 'document_number':
					$query->addOrderBy([
						'i.document_series' => $sort,
						'i.document_number' => $sort,
					]);
					break;
				default:
					$query->addOrderBy(['i.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
