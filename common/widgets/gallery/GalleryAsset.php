<?php

namespace common\widgets\gallery;

use yii\web\AssetBundle;

class GalleryAsset extends AssetBundle
{
	/**
	 * @inheritdoc
	 */
	public $sourcePath = '@npm/fancyapps--fancybox/dist';

	/**
	 * @inheritdoc
	 */
	public $css = [
		'jquery.fancybox.min.css',
	];

	/**
	 * @inheritdoc
	 */
	public $js = [
		'jquery.fancybox.min.js',
	];

	/**
	 * @inheritdoc
	 */
	public $depends = [
		'\yii\web\JqueryAsset',
	];
}
