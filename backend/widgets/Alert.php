<?php
namespace backend\widgets;

use kartik\growl\Growl;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * Alert widget renders a message from session flash. All flash messages are displayed
 * in the sequence they were assigned using setFlash. You can set message as following:
 *
 * ```php
 * Yii::$app->session->setFlash('error', 'This is the message');
 * Yii::$app->session->setFlash('success', 'This is the message');
 * Yii::$app->session->setFlash('info', 'This is the message');
 * ```
 *
 * Multiple messages could be set as follows:
 *
 * ```php
 * Yii::$app->session->setFlash('error', ['Error 1', 'Error 2']);
 * ```
 *
 * @author Kartik Visweswaran <kartikv2@gmail.com>
 * @author Alexander Makarov <sam@rmcreative.ru>
 */
class Alert extends \yii\bootstrap\Widget
{
	/**
	 * @var array the alert types configuration for the flash messages.
	 * This array is setup as $key => $value, where:
	 * - $key is the name of the session flash variable
	 * - $value is the bootstrap alert type (i.e. danger, success, info, warning)
	 */
	public $alertTypes = [
		'info' => [
			'type' => 'info',
			'icon' => 'glyphicon glyphicon-info-sign',
		],
		'success' => [
			'type' => 'success',
			'icon' => 'glyphicon glyphicon-ok-sign',
		],
		'warning' => [
			'type' => 'warning',
			'icon' => 'glyphicon glyphicon-exclamation-sign',
		],
		'danger' => [
			'type' => 'danger',
			'icon' => 'glyphicon glyphicon-remove-sign',
		],
		'error' => [
			'type' => 'danger',
			'icon' => 'glyphicon glyphicon-remove-sign',
		],
	];

	/**
	 * @var array the options for rendering the close button tag.
	 */
	public $closeButton = [];

	/**
	 * @inheritdoc
	 * @throws \Exception
	 */
	public function init()
	{
		parent::init();

		$session = Yii::$app->session;
		$flashes = $session->getAllFlashes();

		foreach ($flashes as $type => $data) {
			if (isset($this->alertTypes[$type])) {
				$data = (array) $data;
				$alertType = $this->alertTypes[$type];

				foreach ($data as $i => $message) {
					$widgetConfig = [
						'id' => $this->getId() . '-' . $type . '-' . $i,
						'type' => $alertType['type'],
						'icon' => $alertType['icon'],
						'showSeparator' => false,
						'useAnimation' => false,
						'delay' => 1000 * $i,
						'closeButton' => $this->closeButton,
						'options' => ArrayHelper::merge($this->options, [
							'class' => 'col-xs-11 col-sm-6 col-md-5 col-lg-4',
						]),
						'pluginOptions' => [
							'z_index' => 9996,
							'delay' => 3000,
							'timer' => 1000,
							'showProgressbar' => true,
							'newest_on_top' => true,
							'allow_dismiss' => true,
							'mouse_over' => 'pause',
							'placement' => [
								'from' => 'top',
								'align' => 'right',
							],
							'animate' => [
								'enter' => 'animated fadeInDown',
								'exit' => 'animated fadeOutUp',
							],
							'offset' => [
								'x' => 20,
								'y' => 60,
							],
						],
					];

					if (is_string($message)) {
						$widgetConfig['title'] = $message;
					} elseif (is_array($message)) {
						if (is_array($message['body']) && !empty($message['body'])) {
							$message['body'] = '<div>' . implode('</div><div>', $message['body']) . '</div>';
						}
						$widgetConfig['showSeparator'] = !empty($message['title']) && !empty($message['body']);
						$widgetConfig = ArrayHelper::merge($widgetConfig, $message);
					}

					echo Growl::widget($widgetConfig);
				}

				$session->removeFlash($type);
			}
		}
	}
}
