<?php

namespace backend\modules\setting\modules\currency\models;

use common\models\Currency;
use common\widgets\datatable\DataTableAction;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;

class CurrencySearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = Currency::find()->where([
			'deleted' => Currency::NO,
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			Currency::class => [
				'id',
				'iso_code',
				'symbol',
				'name',
				'status',
			],
		]);
	}
}
