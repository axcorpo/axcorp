<?php

namespace backend\modules\setting\modules\language\assets;

use yii\web\AssetBundle;

class LanguageTranslateAsset extends AssetBundle
{
	/**
	 * @inheritdoc
	 */
	public $css = [
		'css/language-translate.css',
	];

	/**
	 * @inheritdoc
	 */
	public $js = [
		'js/language-translate.js',
	];

	/**
	 * @inheritdoc
	 */
	public $depends = [
		'yii\web\YiiAsset',
	];

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->sourcePath = dirname(__DIR__) . '/web';
	}
}
