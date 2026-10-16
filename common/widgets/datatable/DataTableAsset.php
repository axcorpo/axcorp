<?php

namespace common\widgets\datatable;

use Yii;
use yii\web\AssetBundle;

class DataTableAsset extends AssetBundle
{
	/**
	 * @inheritdoc
	 */
	public $css = [
		'css/datatables.min.css',
	];

	/**
	 * @inheritdoc
	 */
	public $js = [
		'js/datatables.min.js',
		'js/yii.dataTable.js',
	];

	/**
	 * @inheritdoc
	 */
	public $depends = [
		'\yii\web\JqueryAsset',
	];

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		// Call the parent
		parent::init();
		// Set the sourcePath
		$this->sourcePath = __DIR__ . '/assets';
		// Get the two letters application language
		$language = substr(Yii::$app->language, 0, 2);
		// Add DataTable existing language file
		if (file_exists($this->sourcePath . "/i18n/{$language}.js")) {
			$this->js[] = "i18n/{$language}.js";
		}
		// Handle DEV environment
		if (YII_ENV_DEV) {
			$this->publishOptions['forceCopy'] = true;
		}
	}
}
