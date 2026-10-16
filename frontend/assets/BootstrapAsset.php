<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class BootstrapAsset extends AssetBundle
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
		'css/bootstrap.min.css',
	];

	/**
	 * @inheritdoc
	 */
	public $js = [
		'js/bootstrap.min.js',
	];

	/**
	 * @inheritdoc
	 */
	public $jsOptions = [
		'position' => \yii\web\View::POS_END,
	];

	/**
	 * @inheritdoc
	 */
	public $depends = [
		'yii\web\JqueryAsset',
		'frontend\assets\PopperAsset',
	];
}

