<?php

namespace common\widgets\datatable;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\JsExpression;

/**
 * Class DataTable
 *
 * @package common\widgets\datatable
 * @author AlinHort <alinhort@gmail.com>
 * @link https://datatables.net/reference/option/
 */
class DataTable extends Widget
{
	/**
	 * @var bool Flag that indicates if column filters should be visible.
	 */
	public $showColumnFilters = true;

	/**
	 * @var array Array with DataTable cached configuration.
	 */
	public $cacheData = [];

	/**
	 * @var array The widget options.
	 */
	public $options = [];

	/**
	 * @var array The client (JS) options.
	 */
	public $clientOptions = [];

	/**
	 * @var array The client (JS) events.
	 */
	public $clientEvents = [];

	/**
	 * @var string The client (JS) selector.
	 */
	private $_clientSelector;

	/**
	 * @var string The global widget JS hash variable.
	 */
	private $_hashVar;

	/**
	 * @var array A collection of column filters.
	 */
	private $_columnFilters = [];

	/**
	 * @inheritdoc
	 * @throws InvalidConfigException
	 */
	public function init()
	{
		// Call the parent
		parent::init();
		// Set properties
		$this->setupProperties();
		// Set columns
		$this->setupColumns();
		// Register assets
		$this->registerAssets();
		// Begin widget content
		ob_start();
	}

	/**
	 * @inheritdoc
	 */
	public function run()
	{
		// End widget content - Get the HTML as the widget content
		$content = ob_get_clean();
		// Render the table
		return Html::tag('table', $content, $this->options);
	}

	/**
	 * Gets the client selector.
	 *
	 * @return string
	 */
	public function getClientSelector()
	{
		if (!$this->_clientSelector) {
			$this->_clientSelector = '#' . $this->options['id'] ?: $this->getId();
		}
		return $this->_clientSelector;
	}

	/**
	 * Gets the hash variable.
	 *
	 * @return string
	 */
	public function getHashVar()
	{
		if (!$this->_hashVar) {
			$this->_hashVar = 'datatable_' . hash('crc32', $this->buildClientOptions());
		}
		return $this->_hashVar;
	}

	/**
	 * Sets the widget properties.
	 */
	protected function setupProperties()
	{
		// Ensure that options array contains an id key
		$this->options['id'] = $this->options['id'] ?: $this->getId();
		// Ensure default CSS class for the table
		Html::addCssClass($this->options, 'data-table');
	}

	/**
	 * Sets the DataTable columns.
	 *
	 * @throws InvalidConfigException
	 */
	protected function setupColumns()
	{
		// Return if the DataTable columns option is not set
		if (!isset($this->clientOptions['columns'])) {
			return;
		}
		// Loop through the columns
		foreach ($this->clientOptions['columns'] as $i => $column) {
			// If the column contains a string, use it as data key
			if (is_string($column)) {
				$this->clientOptions['columns'][$i] = [
					'data' => $column,
					'name' => $column,
				];
			}
			// If the column is an array
			if (is_array($column)) {
				// Ensure BaseDataTableColumn class if a custom one is not set
				if (!isset($column['class'])) {
					$column['class'] = BaseDataTableColumn::class;
				}
				// Ensure default column name attribute value equal to data attribute value
				if (!isset($column['name'])) {
					$column['name'] = $column['data'];
				}
				// Create an object for the current column
				$columnObject = Yii::createObject($column);
				// Skip the current column if is invisible
				if ($columnObject->visible === false) {
					unset($this->clientOptions['columns'][$i]);
					$this->updateSortOrder($i);
					continue;
				}
				// Push to column filters array
				if ($this->showColumnFilters === true) {
					$this->_columnFilters[] = $columnObject->filter;
				}
				// Update client options for the current column
				$this->clientOptions['columns'][$i] = $this->filterColumnAttributes($columnObject);
				// If the column class is CheckboxColumn and the DataTable select option is not set
				if ($column['class'] === CheckboxColumn::class && !$this->clientOptions['select']) {
					// Automatically add options that handles selection for the DataTable
					$this->clientOptions['select'] = [
						'style' => 'multi',
						'selector' => '.checkbox-column :checkbox',
					];
				}
			}
		}
		// Ensure columns array correct indexing
		$this->clientOptions['columns'] = array_values($this->clientOptions['columns']);
	}

	/**
	 * Updates order property starting from an index.
	 * Subtracts one for ordering rule.
	 *
	 * @param $index
	 */
	protected function updateSortOrder($index)
	{
		if (!empty($this->clientOptions['order'])) {
			foreach ($this->clientOptions['order'] as &$sortOrder) {
				if ($sortOrder[0] >= $index) {
					$sortOrder[0] -= 1;
				}
			}
		}
	}

	/**
	 * Builds the client options.
	 *
	 * @return string
	 */
	protected function buildClientOptions()
	{
		// Ensure default values
		$defaultClientOptions = [
			// Defaults can be added here
		];
		// Check if the cacheData is set
		if (is_array($this->cacheData) && !empty($this->cacheData)) {
			// Set the order
			if (is_array($this->cacheData['order']) && !empty($this->cacheData['order'])) {
				$this->clientOptions['order'] = array_map(function ($order) {
					return [(int) $order['column'], $order['dir']];
				}, $this->cacheData['order']);
			}
			// Set the searchCols
			if (is_array($this->cacheData['columns']) && !empty($this->cacheData['columns'])) {
				$this->clientOptions['searchCols'] = array_map(function ($column) {
					return !empty($column['search']['value']) ? ['search' => $column['search']['value']] : null;
				}, $this->cacheData['columns']);
			}
			// Set the global search
			if (is_array($this->cacheData['search']) && isset($this->cacheData['search']['value'])) {
				$this->clientOptions['search'] = ['search' => $this->cacheData['search']['value']];
			}
			// Set the displayStart
			if (isset($this->cacheData['start'])) {
				$this->clientOptions['displayStart'] = (int) $this->cacheData['start'];
			}
			// Set the pageLength
			if (isset($this->cacheData['length'])) {
				$this->clientOptions['pageLength'] = (int) $this->cacheData['length'];
			}
		}
		// Ensure that the pageLength always has an integer value and fall back to a default value
		$this->clientOptions['pageLength'] = (int) $this->clientOptions['pageLength'];
		if ($this->clientOptions['pageLength'] == 0) {
			$this->clientOptions['pageLength'] = 25;
		}
		// Build the lengthMenu
		if (is_array($this->clientOptions['lengthMenu']) && $this->clientOptions['lengthMenu']['autoCreate'] === true) {
			$this->clientOptions['lengthMenu'] = self::buildLengthMenu($this->clientOptions['pageLength'], $this->clientOptions['lengthMenu']['displayAll']);
		}
		// Merge client options
		$clientOptions = ArrayHelper::merge($defaultClientOptions, $this->clientOptions);
		// Return options as JSON
		return Json::encode($clientOptions);
	}

	/**
	 * Registers the widget assets.
	 */
	protected function registerAssets()
	{
		// Get the view
		$view = $this->getView();
		// Register assets
		DataTableAsset::register($view);
		// Register widget hash JavaScript variable
//		$view->registerJs("var {$this->getHashVar()} = {$this->buildClientOptions()};", View::POS_HEAD);
		// Build client script
		$js = "jQuery('{$this->getClientSelector()}').yiiDataTable().DataTable({$this->buildClientOptions()})";
		// Build client events
		if (!empty($this->clientEvents)) {
			foreach ($this->clientEvents as $clientEvent => $eventHandler) {
				if (!($eventHandler instanceof JsExpression)) {
					$eventHandler = new JsExpression($eventHandler);
				}
				$js .= ".on('{$clientEvent}', {$eventHandler})";
			}
		}
		// Register widget JavaScript
		$view->registerJs("{$js};");
		// Register DataTable inline JavaScript for rendering column filters row
		if (!empty($this->_columnFilters)) {
			// Append the filters row based on the scrollX/Y property
			if ($this->clientOptions['scrollX'] || $this->clientOptions['scrollY']) {
				$view->registerJs("jQuery('{$this->getClientSelector()}').on('init.dt', function (e) {
					$(e.target).closest('.dataTables_scroll').find('.dataTables_scrollHead thead').append({$this->buildColumnFiltersRow()});
				});");
			} else {
				$view->registerJs("jQuery('{$this->getClientSelector()}').on('init.dt', function (e) {
					$(e.target).children('thead').append({$this->buildColumnFiltersRow()});
				});");
			}
		}
		// Register custom plugins assets
		if (!empty($this->clientOptions['rowsGroup'])) {
			RowsGroupPluginAsset::register($view);
		}
	}

	/**
	 * Builds a table row with column filters controls.
	 *
	 * @return string
	 */
	protected function buildColumnFiltersRow()
	{
		// Wrap each filter into a table heading tag
		$cells = array_map(function ($filter) {
			return Html::tag('th', $filter, ['class' => 'filter-column']);
		}, $this->_columnFilters);
		// Return a table row with its cells
		return Json::encode(Html::tag('tr', implode('', $cells), ['class' => 'filters-row']));
	}

	/**
	 * Filters and keeps DataTable valid column attributes.
	 *
	 * @param $column
	 * @return array
	 */
	protected function filterColumnAttributes($column)
	{
		// Get the DataTable column allowed attributes
		$dtColumnAttributes = get_class_vars(BaseDataTableColumn::class);
		// Get the column object attributes
		$columnAttributes = get_object_vars($column);
		// Loop through the column attributes
		foreach ($columnAttributes as $attribute => $val) {
			// Remove the attribute that is not a DataTable column valid attribute
			if (!array_key_exists($attribute, $dtColumnAttributes)) {
				unset($column->$attribute);
				continue;
			}
			// Remove nulls
			if (is_null($column->$attribute)) {
				unset($column->$attribute);
			}
		}
		// Unset extra custom attributes
		unset($column->filter);
		// Return the altered column object
		return $column;
	}

	/**
	 * Builds the DataTable lengthMenu plugin option to contain the pageLength option value.
	 *
	 * @param int $pageLength The current pageLength.
	 * @param string $displayAll Indicates if all records can be displayed and sets the proper dropdown option.
	 * @return array
	 */
	public static function buildLengthMenu($pageLength, $displayAll = null)
	{
		if (!$pageLength) {
			$pageLength = 25;
		}
		$lengthMenu = [25, 50, 75, 100];

		if (!in_array($pageLength, $lengthMenu)) {
			$lengthMenu[] = $pageLength;
			sort($lengthMenu);
		}

		$lengthMenu = [
			$lengthMenu,
			$lengthMenu,
		];

		if (!empty($displayAll)) {
			$lengthMenu[0][] = -1;
			$lengthMenu[1][] = $displayAll;
		}

		return $lengthMenu;
	}
}
