<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class NpmAsset extends AssetBundle
{
	/**
	 * @inheritdoc
	 */
	public $sourcePath = '@npm';

	/**
	 * @inheritdoc
	 */
	public $css = [
		'font-awesome/css/font-awesome.min.css',
		'animate.css/animate.min.css',
	];

	/**
	 * @inheritdoc
	 */
	public $js = [
		'iframe-resizer/js/iframeResizer.min.js',
	];
}
