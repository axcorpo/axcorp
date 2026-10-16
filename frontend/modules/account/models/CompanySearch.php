<?php

namespace frontend\modules\account\models;

use common\helpers\DateHelper;
use common\models\Company;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\bootstrap\Dropdown;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class CompanySearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = Company::find()
			->alias('c')
			->select([
				'c.id',
				'c.user_id',
				'c.registration_number',
				'c.tin',
				'c.name',
				'c.phone',
				'c.fax',
				'c.email',
				'c.created_at',
				'c.updated_at',
			])
			->andWhere([
				'c.user_id' => Yii::$app->user->id,
				'c.deleted' => Company::NO,
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			Company::class => [
				'id',
				'action' => function (Company $model) {
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
								'popup-done' => ['redrawDataTable' => '#dt-companies'],
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
				'name' => function (Company $model) {
					return $model->name ?: '&mdash;';
				},
				'phone' => function (Company $model) {
					$items = [];
					if ($model->phone) {
						$items[] = Html::tag('li', $model->phone, [
							'class' => 'fa-phone',
							'title' => Yii::t('label', 'Phone'),
						]);
					}
					if ($model->fax) {
						$items[] = Html::tag('li', $model->fax, [
							'class' => 'fa-fax',
							'title' => Yii::t('label', 'Fax'),
						]);
					}
					return !empty($items) ? Html::tag('ul', implode('', $items), ['class' => 'list-icon list-spacing']) : '&mdash;';
				},
				'email' => function (Company $model) {
					return $model->email ?: '&mdash;';
				},
				'created_at' => function (Company $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
				'updated_at' => function (Company $model) {
					return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
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
				case 'phone':
					$query->$filterOperator([
						'OR',
						['LIKE', 'c.phone', $value],
						['LIKE', 'c.fax', $value],
					]);
					break;
				case 'created_at':
					$query->$filterOperator(['LIKE', 'c.created_at', DateHelper::formatAsDate($value)]);
					break;
				case 'updated_at':
					$query->$filterOperator(['LIKE', 'c.updated_at', DateHelper::formatAsDate($value)]);
					break;
				default:
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 'c.' . $column['data'], $value]);
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
				case 'phone':
					$query->addOrderBy([
						'c.phone' => $sort,
						'c.fax' => $sort,
					]);
					break;
				default:
					$query->addOrderBy(['c.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
