<?php

namespace backend\modules\notification\widgets\notification;

use yii\web\AssetBundle;

class NotificationAsset extends AssetBundle
{
	/**
	 * @inheritdoc
	 */
	public $css = [

	];

	/**
	 * @inheritdoc
	 */
	public $js = [
		'js/notification.js',
	];

	/**
	 * @inheritdoc
	 */
	public $depends = [
		'\yii\web\JqueryAsset',
	];

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->sourcePath = __DIR__ . '/assets';

		if (YII_ENV_DEV) {
			$this->publishOptions['forceCopy'] = true;
		}
	}
}
