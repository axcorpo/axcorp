<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class BootstrapBundleAsset extends AssetBundle
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
	public $js = [
		'js/bootstrap.bundle.min.js',
	];

	/**
	 * @inheritdoc
	 */
	public $jsOptions = [
		'position' => \yii\web\View::POS_HEAD,
	];

	/**
	 * @inheritdoc
	 */
	public $depends = [];
}

