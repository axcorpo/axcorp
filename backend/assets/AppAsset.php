<?php

namespace backend\assets;

use yii\web\AssetBundle;

class AppAsset extends AssetBundle
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
		'css/tpl/layout.min.css',
		'css/tpl/skins/default.css',
		'css/main-app.css',
		'css/custom.css',
	];

	/**
	 * @inheritdoc
	 */
	public $js = [
		'js/tpl/app.min.js',
		'js/tpl/layout.min.js',
		'js/tpl/quick-sidebar.min.js',
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
