<?php

namespace common\helpers;

class NumberHelper
{
	/**
	 * Converts a number without rounding.
	 *
	 * @param int|float $value The value to be converted.
	 * @param int $decimals The number of decimals.
	 * @return float The converted value.
	 */
	public static function toNumber($value, $decimals = 2)
	{
		$factor = pow(10, $decimals);
		$value = $value * $factor;
		$value = intval($value);
		$value = $value / $factor;

		return $value;
	}

	public static function formatNumber($number, $decimals = 2 ) {
		$formattedNumber = number_format($number, $decimals, '.', '');
		$parts = explode('.', $formattedNumber);
		$integerPart = $parts[0];
		$decimalPart = str_pad($parts[1], $decimals, '0', STR_PAD_RIGHT);
		return $integerPart . '.' . $decimalPart;
	}
}