<?php

namespace backend\modules\eventlog\widgets\eventlogdisplay;

use yii\base\Widget;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\JsExpression;
use yii\web\View;

/**
 * Class EventLogDisplay
 *
 * @author Alin Hort <alinhort@gmail.com>
 */
class EventLogDisplay extends Widget
{
	/**
	 * @var string The widget title.
	 */
	public $title;

	/**
	 * @var boolean Flag that indicates if the widget is rendered in a modal window.
	 */
	public $modal = true;

	/**
	 * @var \yii\db\ActiveRecord The event log ActiveRecord model.
	 */
	public $model;

	/**
	 * @var boolean Flag that indicates if the model metadata should be displayed.
	 */
	public $showMetadata = true;

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
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->setupProperties();
		$this->registerAssets();

		ob_start();
	}

	/**
	 * @inheritdoc
	 */
	public function run()
	{
		$content = ob_get_clean();

		if ($this->modal === true) {
			Html::addCssClass($this->options, 'modal-dialog modal-lg');
		}

		$content = $this->render($this->modal === true ? 'modal' : 'index', [
			'content' => $content,
		]);

		return Html::tag('div', $content, $this->options);
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
			$this->_hashVar = 'eventlogdisplay_' . hash('crc32', $this->buildClientOptions());
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
		// Ensure default CSS class for the widget container
		Html::addCssClass($this->options, 'eventlogdisplay-container');
	}

	/**
	 * Builds Client Options.
	 *
	 * @return string
	 */
	protected function buildClientOptions()
	{
		// Ensure default values
		$defaultClientOptions = [
			// Defaults can be added here
		];
		// Merge client options
		$clientOptions = ArrayHelper::merge($defaultClientOptions, $this->clientOptions);
		// Return options as JSON
		return Json::encode($clientOptions);
	}

	/**
	 * Registers widget assets.
	 */
	protected function registerAssets()
	{
		// Get the view
		$view = $this->getView();
		// Register assets
		EventLogDisplayAsset::register($view);
		// Register widget hash JavaScript variable
		$view->registerJs("var {$this->getHashVar()} = {$this->buildClientOptions()};", View::POS_HEAD);
		// Build client script
		$js = "jQuery('{$this->getClientSelector()}').yiiEventLogDisplay({$this->getHashVar()})";
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
}