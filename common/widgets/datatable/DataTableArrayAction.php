<?php
namespace common\widgets\datatable;

use Yii;
use yii\base\InvalidConfigException;
use yii\data\ArrayDataProvider;
use yii\helpers\ArrayHelper;

/**
 * Action class that process DataTable AJAX request with ArrayDataProvider.
 *
 * @author Tree Web Solutions <treewebsolutions.com@gmail.com>
 */
class DataTableArrayAction extends BaseDataTableAction
{
	/**
	 * @var array The dataset to be used.
	 */
	public $dataset = [];


	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
	}

	/**
	 * Runs this action.
	 *
	 * @return mixed
	 * @throws InvalidConfigException if dataset is not properly configured.
	 */
	public function run()
	{
		if (!is_array($this->dataset)) {
			throw new InvalidConfigException(self::class . '::$dataset must be an array.');
		}

		$originalDataset = $this->dataset;
		$filteredDataset = $originalDataset;

		$draw = $this->getParam('draw');
		$search = $this->getParam('search', ['value' => null, 'regex' => false]);
		$columns = $this->getParam('columns', []);
		$order = $this->getParam('order', []);

		$filteredDataset = $this->applyFilter($filteredDataset, $columns, $search);
		$filteredDataset = $this->applyOrder($filteredDataset, $columns, $order);

		$dataProvider = new ArrayDataProvider([
			'allModels' => $filteredDataset,
			'pagination' => [
				'pageSize' => $this->getParam('length', 10),
				'page' => ceil($this->getParam('start', 0) / $this->getParam('length', 10)),
			],
		]);

		Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

		try {
			$response = [
				'draw' => (int) $draw,
				'recordsTotal' => (int) count($originalDataset),
				'recordsFiltered' => (int) $dataProvider->getTotalCount(),
				'data' => $this->normalizeBinaryUuids($this->formatData(array_values($dataProvider->getModels()), $columns)),
			];
		} catch (\Exception $e) {
			return ['error' => $e->getMessage()];
		}

		return $this->formatResponse($response);
	}

	/**
	 * Applies filtering.
	 *
	 * @param array $dataset The dataset of the table.
	 * @param array $columns The table columns.
	 * @param array $search The table searchable columns.
	 * @return array The filtered dataset.
	 */
	public function applyFilter($dataset, $columns, $search)
	{
		if (is_callable($this->applyFilter)) {
			return call_user_func($this->applyFilter, $dataset, $columns, $search);
		}

		foreach ($columns as $column) {
			if (array_key_exists('searchable', $column) && $column['searchable'] == 'false') {
				continue;
			}
			$value = !empty($search['value']) ? $search['value'] : $column['search']['value'];
			if (empty($value)) {
				continue;
			}
			$dataset = array_filter($dataset, function ($data) use ($column, $value) {
				if (is_array($data[$column['data']])) {
					return array_filter($data[$column['data']], function ($data) use ($value) {
						return stripos((string) $data, (string) $value) !== false;
					});
				}
				return stripos((string) $data[$column['data']], (string) $value) !== false;
			});
		}

		return $dataset;
	}

	/**
	 * Applies ordering.
	 *
	 * @param array $dataset The dataset of the table.
	 * @param array $columns The table columns.
	 * @param array $order The table sortable columns.
	 * @return array The ordered dataset.
	 */
	public function applyOrder($dataset, $columns, $order)
	{
		if (is_callable($this->applyOrder)) {
			return call_user_func($this->applyOrder, $dataset, $columns, $order);
		}

		$orders = [];
		foreach ($order as $key => $item) {
			$column = $columns[$item['column']];
			if (array_key_exists('orderable', $column) && $column['orderable'] == 'false') {
				continue;
			}
			$orders[$column['data']] = mb_strtolower($item['dir']) == 'desc' ? SORT_DESC : SORT_ASC;
		}
		if (!empty($orders)) {
			ArrayHelper::multisort($dataset, array_keys($orders), array_values($orders));
		}

		return $dataset;
	}

	/**
	 * Formats the dataset.
	 *
	 * @param array $dataset The dataset of the table.
	 * @param array $columns The table columns.
	 * @return array The formatted dataset.
	 */
	public function formatData($dataset, $columns)
	{
		if (is_callable($this->formatData)) {
			return call_user_func($this->formatData, $dataset, $columns);
		}

		return $dataset;
	}

	/**
	 * Formats the response.
	 *
	 * @param array $response
	 * @return array The formatted response.
	 */
	public function formatResponse($response)
	{
		if (is_callable($this->formatResponse)) {
			return call_user_func($this->formatResponse, $response);
		}

		return $response;
	}
}
