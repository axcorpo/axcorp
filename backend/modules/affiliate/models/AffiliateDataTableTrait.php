<?php

namespace backend\modules\affiliate\models;

use common\helpers\UuidHelper;
use common\models\Affiliate;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\ForbiddenHttpException;

/**
 * Shared plumbing of the affiliate DataTable actions.
 *
 * - [[assertCan()]]: the actions are also instantiated by export-manager/export, which any
 *   signed-in admin may call with any search class, so each one checks its own permission.
 * - [[getFilter()]]: the fixed context of a list (the affiliate / payout a view shows) arrives
 *   as `data.*` params, or — on export — nested under `dataTable` (the grid's last ajax
 *   params). Everything else is filtered by the DataTable's own column filters.
 */
trait AffiliateDataTableTrait
{
	/**
	 * @param string $permission
	 * @throws ForbiddenHttpException
	 */
	protected function assertCan($permission)
	{
		if (!Yii::$app->user->can($permission)) {
			throw new ForbiddenHttpException(Yii::t('yii', 'You are not allowed to perform this action.'));
		}
	}

	/**
	 * @param string $name
	 * @return string|null trimmed, null when empty
	 */
	protected function getFilter($name)
	{
		$sources = [
			$this->requestParams,
			$this->externalFilters,
		];
		$dataTable = $this->requestParams['dataTable'] ?? null;
		if (is_array($dataTable)) {
			$sources[] = $dataTable;
			if (!empty($dataTable['external_filters']) && is_array($dataTable['external_filters'])) {
				$sources[] = ArrayHelper::map($dataTable['external_filters'], 'name', 'value');
			}
		}
		foreach ($sources as $source) {
			if (isset($source[$name]) && is_scalar($source[$name]) && trim((string) $source[$name]) !== '') {
				return trim((string) $source[$name]);
			}
		}
		return null;
	}

	/**
	 * A uuid filter as raw bytes; null when absent or malformed.
	 *
	 * @param string $name
	 * @return string|null
	 */
	protected function getUuidFilter($name)
	{
		$value = $this->getFilter($name);
		return UuidHelper::isValid($value) ? UuidHelper::toBytes($value) : null;
	}

	/**
	 * A relation column with a select filter (affiliate): the filter sends the
	 * uuid, matched exactly on `$idColumn`; any other text (the global search) is matched
	 * with LIKE on `$textConditions`, as the text filters do.
	 *
	 * @param \yii\db\ActiveQuery $query
	 * @param string $filterOperator andFilterWhere / orFilterWhere
	 * @param string $value
	 * @param string $idColumn
	 * @param array $textConditions LIKE conditions without the value, e.g. [['LIKE', 'a.code']]
	 */
	protected function applyRelationFilter($query, $filterOperator, $value, $idColumn, array $textConditions)
	{
		if ($value === '' || $value === null) {
			return;
		}
		if (UuidHelper::isValid($value)) {
			$query->$filterOperator([$idColumn => UuidHelper::toBytes($value)]);
			return;
		}
		$conditions = ['OR'];
		foreach ($textConditions as $condition) {
			$conditions[] = [$condition[0], $condition[1], $value];
		}
		$query->$filterOperator($conditions);
	}

	/**
	 * A status column with a select filter: exact match on the status value.
	 *
	 * @param \yii\db\ActiveQuery $query
	 * @param string $filterOperator
	 * @param string $value
	 * @param string $column
	 */
	protected function applyStatusFilter($query, $filterOperator, $value, $column)
	{
		$query->$filterOperator([$column => is_numeric($value) ? (int) $value : null]);
	}

	/**
	 * The options of the affiliate select filters: uuid => "Full Name (CODE)".
	 *
	 * @return array
	 */
	public static function affiliateFilterOptions()
	{
		$options = [];
		foreach (Affiliate::find()->with('user')->where(['deleted' => Affiliate::NO])->orderBy(['code' => SORT_ASC])->all() as $affiliate) {
			$options[$affiliate->uuid] = trim(($affiliate->user ? $affiliate->user->getFullName() : '') . " ({$affiliate->code})");
		}
		return $options;
	}

	/**
	 * A status label as the lists render them.
	 *
	 * @param array $labels getStatusLabels() of a model
	 * @param int|null $status
	 * @return string
	 */
	protected static function statusLabel(array $labels, $status)
	{
		$label = $labels[$status] ?? null;
		return $label ? \yii\helpers\Html::tag('span', $label['label'], ['class' => 'label label-block label-' . $label['color']]) : '&mdash;';
	}
}
