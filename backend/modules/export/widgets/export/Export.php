<?php

namespace backend\modules\export\widgets\export;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\bootstrap\Dropdown;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\JsExpression;
use yii\web\View;

/**
 * Class Export
 *
 * @author Alin Hort <alinhort@gmail.com>
 */
class Export extends Widget
{
	const FORMAT_CSV = 'csv';
	const FORMAT_VCF = 'vcf';
	const FORMAT_XLSX = 'xlsx';
	const FORMAT_PDF = 'pdf';

	const DATATABLE_ALL = 'all-records';
	const DATATABLE_SELECTION = 'selected-records';

	/**
	 * @var array The export model columns.
	 */
	public $columns = [];

	/**
	 * @var array The widget export items.
	 */
	public $items = [];

	/**
	 * @var bool The DataTable widget id.
	 */
	public $dataTable;

	/**
	 * @var \yii\db\ActiveRecord The model used to export the data.
	 */
	public $model;

	/**
	 * @var array The widget options.
	 */
	public $options = [];

	/**
	 * @var array The widget button options.
	 */
	public $buttonOptions = [];

	/**
	 * @var array The Dropdown widget options.
	 */
	public $dropdownOptions = [];

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
	 * @inheritdoc
	 * @throws \yii\base\InvalidConfigException
	 */
	public function init()
	{
		parent::init();

		if (!is_array($this->items) || empty($this->items)) {
			throw new InvalidConfigException('The "items" property is invalid.');
		}

		// Keep only the visible items
		$this->items = array_filter($this->items, function ($item) {
			return isset($item['visible']) ? $item['visible'] : true;
		});

		$this->setupProperties();
		$this->registerAssets();
	}

	/**
	 * @inheritdoc
	 * @throws \Exception
	 */
	public function run()
	{
		foreach ($this->items as $format => &$item) {
			if (!isset($item['url'])) {
				$item['url'] = '#';
			}

			$item['linkOptions']['data']['export-format'] = $format;
		}

		$buttonContent = ArrayHelper::remove($this->buttonOptions, 'label', null);

		return implode("\n", [
			Html::beginTag('div', $this->options),
			Html::button($buttonContent, $this->buttonOptions),
			Dropdown::widget([
				'options' => $this->dropdownOptions,
				'items' => $this->items,
				'clientOptions' => false,
				'view' => $this->getView(),
			]),
			Html::endTag('div'),
		]);
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
			$this->_hashVar = 'export_' . hash('crc32', $this->buildClientOptions());
		}
		return $this->_hashVar;
	}

	/**
	 * Sets the widget properties.
	 */
	protected function setupProperties()
	{
		$this->options['id'] = $this->options['id'] ?: $this->getId();
		Html::addCssClass($this->options, 'dropdown export-container');

		$this->buttonOptions = ArrayHelper::merge([
			'data' => [
				'toggle' => 'dropdown',
				'toggle-extend' => 'tooltip',
			],
		], $this->buttonOptions);
		Html::addCssClass($this->buttonOptions, 'dropdown-toggle');
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

		];

		// Set widget items client options
		foreach ($this->items as $format => $item) {
			$this->clientOptions[$format] = $item['clientOptions'];
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
		ExportAsset::register($view);
		// Register widget hash JavaScript variable
		$view->registerJs("var {$this->getHashVar()} = {$this->buildClientOptions()};", View::POS_HEAD);
		// Build client script
		$js = "jQuery('{$this->getClientSelector()}').export({$this->getHashVar()})";
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
	}

	/**
	 * Gets the model columns.
	 *
	 * @throws \yii\base\InvalidConfigException
	 */
	protected function initColumns()
	{
		// Use the provided columns or get the model schema columns
		if (!is_array($this->columns) || empty($this->columns)) {
			$this->columns = ($this->model)::getTableSchema()->columns;
		}
		// Allow only not excluded columns
		$this->columns = array_filter($this->columns, function ($column) {
			return !in_array($column->name, $this->excludedColumns);
		});
	}
}