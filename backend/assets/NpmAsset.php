<?php

namespace backend\assets;

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
		'simple-line-icons/css/simple-line-icons.css',
		'animate.css/animate.min.css',
	];

	/**
	 * @inheritdoc
	 */
	public $js = [

	];
}
