<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class PopperAsset extends AssetBundle
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
		'js/popper.min.js',
	];

	/**
	 * @inheritdoc
	 */
	public $jsOptions = [
		// Removed POS_HEAD - Popper JS will load at end of body (POS_END)
	];

	/**
	 * @inheritdoc
	 */
	public $depends = [];
}

