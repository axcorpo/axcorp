<?php

namespace common\components;

/**
 * Shared {@see \borales\extensions\phoneInput\PhoneInput} options (intl-tel-input + libphonenumber on submit).
 *
 * Centralises the widget configuration so every phone field across frontend/backend behaves the same:
 * an international country selector while typing and an E.164 value (e.g. +40256123456) on form submit.
 */
final class PhoneInputConfig
{
	/**
	 * JS options: E.164 on form submit, formatting while typing, Romania as the default region.
	 *
	 * @return array
	 */
	public static function jsOptions()
	{
		return [
			'nationalMode' => false,
			'formatOnDisplay' => true,
			'separateDialCode' => true,
			'initialCountry' => 'ro',
			'preferredCountries' => ['ro', 'md', 'it', 'es', 'de'],
		];
	}

	/**
	 * Merged with {@see \borales\extensions\phoneInput\PhoneInput::$defaultOptions} for the input element.
	 *
	 * @return array
	 */
	public static function inputClassOptions()
	{
		return [
			'class' => 'form-control',
		];
	}
}
