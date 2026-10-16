<?php

namespace common\widgets\datatable;

use yii\web\AssetBundle;

class RowsGroupPluginAsset extends AssetBundle
{
	/**
	 * @inheritdoc
	 */
	public $js = [
		'js/dataTables.rowsGroup.js',
	];

	/**
	 * @inheritdoc
	 */
	public $depends = [
		'\common\widgets\datatable\DataTableAsset',
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
	}
}
