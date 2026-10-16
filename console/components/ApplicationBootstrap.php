<?php

namespace console\components;

use Yii;

class ApplicationBootstrap extends \common\components\ApplicationBootstrap
{
	/**
	 * @var string The host info.
	 */
	protected $hostInfo;


	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$settings = Yii::$app->settings->getCategory('general');
		$this->hostInfo = $settings['hostInfo'];

		// Set missing environment variables since this is a console application
		if (!isset($_SERVER['SERVER_NAME'])) {
			$_SERVER['SERVER_NAME'] = parse_url($this->hostInfo, PHP_URL_HOST);
		}
		if (!isset($_SERVER['REQUEST_URI'])) {
			$_SERVER['REQUEST_URI'] = '';
		}
	}

	/**
	 * @inheritdoc
	 * @throws \yii\base\InvalidConfigException
	 */
	public function bootstrap($app)
	{
		$this->initUrlManager();
	}

	/**
	 * Initializes UrlManager component.
	 * @link http://www.yiiframework.com/doc-2.0/yii-web-urlmanager.html
	 *
	 * @throws \yii\base\InvalidConfigException
	 */
	protected function initUrlManager()
	{
		if (Yii::$app->has('urlManager')) {
			/** @var \yii\web\UrlManager $urlManager */
			$urlManager = Yii::$app->get('urlManager');
			$urlManager->setHostInfo($this->hostInfo);

			Yii::$app->set('urlManager', $urlManager);
		}
	}
}
