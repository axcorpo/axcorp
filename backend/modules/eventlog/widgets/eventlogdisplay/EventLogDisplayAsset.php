<?php

namespace backend\modules\eventlog\widgets\eventlogdisplay;

use yii\web\AssetBundle;

class EventLogDisplayAsset extends AssetBundle
{
	/**
	 * @inheritdoc
	 */
	public $js = [
		'js/yii.eventLogDisplay.js',
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
