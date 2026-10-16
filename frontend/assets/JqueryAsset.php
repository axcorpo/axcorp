<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class JqueryAsset extends AssetBundle
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
		'js/jquery-3.7.1.min.js',
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

