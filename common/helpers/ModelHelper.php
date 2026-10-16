<?php

namespace common\helpers;

use Yii;

class ModelHelper
{
	/**
	 * Creates multiple models limited by a counter.
	 *
	 * @param int $count
	 * @param string $modelClass
	 * @param array $attributes
	 * @return array
	 */
	public static function createMultiple($count, $modelClass, $attributes = null)
	{
		$models = [];
		if (!$count) {
			$count = 1;
		}

		for ($i = 0; $i < $count; $i++) {
			$models[$i] = new $modelClass;

			if ($attributes) {
				foreach ($attributes as $attribute => $value) {
					$models[$i]->$attribute = $value;
				}
			}
		}

		return $models;
	}

	/**
	 * Gets the translation string or a default placeholder if does not exist.
	 *
	 * @param array $data
	 * @param string $language
	 * @param string $preferredLanguage
	 * @param string $prefix
	 * @return null|string
	 */
	public static function getTranslation($data, $language, $preferredLanguage = 'en-US', $prefix = '***MISSING_TRANSLATION***')
	{
		if (!is_array($data)) {
			return null;
		}
		// Return the translation if exist
		if (!empty($data[$language])) {
			return trim($data[$language]);
		}
		// Return the preferred translation if exist
		if (!empty($data[$preferredLanguage])) {
			return $prefix . ' ' . trim($data[$preferredLanguage]);
		}
		// Get the first non-empty translation from the data
		foreach ($data as $language_id => $translation) {
			if (!empty($translation)) {
				return $prefix . ' ' . trim($translation);
			}
		}
		// Return the missing string prefix
		return $prefix;
	}
}