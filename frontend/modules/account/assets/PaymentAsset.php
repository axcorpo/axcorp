<?php

namespace frontend\modules\account\assets;

use yii\web\AssetBundle;

class PaymentAsset extends AssetBundle
{
	/**
	 * @inheritdoc
	 */
	public $js = [
		'js/payment.js',
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
