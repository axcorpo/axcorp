<?php

namespace backend\modules\export\widgets\export;

use yii\web\AssetBundle;

class ExportAsset extends AssetBundle
{
	/**
	 * @inheritdoc
	 */
	public $css = [
		'css/export.css',
	];

	/**
	 * @inheritdoc
	 */
	public $js = [
		'js/export.js',
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
