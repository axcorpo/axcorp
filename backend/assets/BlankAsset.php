<?php

namespace backend\assets;

use yii\web\AssetBundle;

class BlankAsset extends AssetBundle
{
	/**
	 * @inheritdoc
	 */
	public $basePath = '@webroot';

	/**
	 * @inheritdoc
	 */
	public $baseUrl = '@web';

	/**
	 * @inheritdoc
	 */
	public $css = [
		'css/tpl/components.min.css',
		'css/main-app.css',
		'css/pages/blank.css',
	];

	/**
	 * @inheritdoc
	 */
	public $js = [
		'js/tpl/app.min.js',
		'js/main-app.js',
	];

	/**
	 * @inheritdoc
	 */
	public $depends = [
		'yii\web\YiiAsset',
		'yii\bootstrap\BootstrapAsset',
		'yii\bootstrap\BootstrapPluginAsset',
		'backend\assets\BowerAsset',
		'backend\assets\NpmAsset',
	];
}
