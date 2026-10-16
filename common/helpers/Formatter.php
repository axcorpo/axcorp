<?php

namespace common\helpers;

/**
 * @inheritdoc
 */
class Formatter extends \yii\i18n\Formatter
{
	/**
	 * @inheritdoc
	 * Additionally, this method allows mapping the currency symbol.
	 */
	public function asCurrency($value, $currency = null, $options = [], $textOptions = [])
	{
		$currencyCodesMap = [
			'RON' => 'Lei',
		];

		if (array_key_exists(mb_strtoupper($currency), $currencyCodesMap)) {
			$currency = $currencyCodesMap[mb_strtoupper($currency)];
		}

		return parent::asCurrency($value, $currency, $options, $textOptions);
	}
}
