<?php

namespace backend\modules\notification\widgets\notification;

use yii\base\Widget;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use tws\helpers\Url;
use yii\web\JsExpression;
use yii\web\View;

/**
 * Class Notification
 *
 * @author Tree Web Solutions <treewebsolutions.com@gmail.com>
 */
class Notification extends Widget
{
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
	 * @throws \Exception
	 */
	public function run()
	{
		$content = ob_get_clean();

		if (empty($content)) {
			$content = $this->render('@app/modules/notification/views/notification/list', [
				'models' => \common\models\Notification::findAllUnseen(),
			]);
		}

		$tagName = ArrayHelper::remove($this->options, 'tagName');

		return Html::tag($tagName, $content, $this->options);
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
			$this->_hashVar = 'notification_' . hash('crc32', $this->buildClientOptions());
		}
		return $this->_hashVar;
	}

	/**
	 * Sets the widget properties.
	 */
	protected function setupProperties()
	{
		$defaultOptions = [
			'id' => $this->getId(),
			'tagName' => 'div',
		];
		// Merge client options
		$this->options = ArrayHelper::merge($defaultOptions, $this->options);
		// Ensure the default CSS class for the widget container
		Html::addCssClass($this->options, 'notification-container');
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
			'notificationSoundFile' => Url::to(['/audio/notification.mp3']),
		];
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
		NotificationAsset::register($view);
		// Register widget hash JavaScript variable
		$view->registerJs("var {$this->getHashVar()} = {$this->buildClientOptions()};", View::POS_HEAD);
		// Build client script
		$js = "jQuery('{$this->getClientSelector()}').notification({$this->getHashVar()})";
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
