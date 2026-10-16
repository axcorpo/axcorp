<?php

namespace common\models;

use common\helpers\HtmlSanitizer;
use Yii;

/**
 * This is the base model class for the common ActiveRecord models.
 */
class CommonActiveRecord extends \yii\db\ActiveRecord
{
	const NO = 0;
	const YES = 1;

	const STATUS_INACTIVE = 0;
	const STATUS_ACTIVE = 1;

	const GENDER_MALE = 1;
	const GENDER_FEMALE = 2;

	/**
	 * {@inheritdoc}
	 */
	public function init()
	{
		parent::init();

		$this->on(self::EVENT_BEFORE_INSERT, [$this, 'sanitizeStoredText']);
		$this->on(self::EVENT_BEFORE_UPDATE, [$this, 'sanitizeStoredText']);
	}

	/**
	 * Keeps executable markup out of every text column, whatever form or service writes it.
	 *
	 * Output escaping is the real defence, but a value printed raw in a single forgotten
	 * place is enough, so nothing executable is stored in the first place:
	 * - short text columns (VARCHAR/CHAR: names, titles, subjects) are plain text, and any
	 *   tag in them is removed;
	 * - long text columns (TEXT: contents, notes, templates) hold the HTML the admin
	 *   edits, and only the executable parts are removed, see HtmlSanitizer::stripExecutable();
	 * - serialized and JSON values (settings, metadata) are left alone, rewriting them would
	 *   corrupt them;
	 * - the attributes named by [[selfSanitizedAttributes()]] are left to the model.
	 *
	 * Only the attributes being written are looked at.
	 */
	public function sanitizeStoredText()
	{
		$columns = static::getTableSchema()->columns;
		$skipped = $this->selfSanitizedAttributes();
		foreach ($this->getDirtyAttributes() as $name => $value) {
			if (!is_string($value) || $value === '' || !isset($columns[$name]) || in_array($name, $skipped, true) || strpos($value, '<') === false && stripos($value, 'script:') === false) {
				continue;
			}
			if (preg_match('/^(a|O|s|i|b|d|N):[\d;:]/', $value) || (in_array($value[0], ['{', '['], true) && json_decode($value) !== null)) {
				continue;
			}

			$type = $columns[$name]->type;
			if ($type === \yii\db\Schema::TYPE_STRING || $type === \yii\db\Schema::TYPE_CHAR) {
				$this->setAttribute($name, HtmlSanitizer::plainText($value));
			} elseif ($type === \yii\db\Schema::TYPE_TEXT) {
				$this->setAttribute($name, HtmlSanitizer::stripExecutable($value));
			}
		}
	}

	/**
	 * Attributes this model cleans itself, with a stricter rule of its own, and that
	 * [[sanitizeStoredText()]] therefore leaves alone.
	 *
	 * @return string[]
	 */
	protected function selfSanitizedAttributes()
	{
		return [];
	}

	/**
	 * @inheritdoc
	 */
	public static function find()
	{
		return new CommonActiveQuery(get_called_class());
	}

	/**
	 * @inheritdoc
	 * @param bool $isPermanent Flag that indicates if the deletion is forced to be permanent.
	 */
	public function delete($isPermanent = false)
	{
		if (!$isPermanent && Yii::$app->settings->get('enableSoftDelete') && $this->getBehavior('SoftDeleteBehavior')) {
			// Detach PositionBehavior because it breaks the sort_order on soft delete
			if ($this->getBehavior('PositionBehavior')) {
				$this->detachBehavior('PositionBehavior');
			}
			return parent::softDelete();
		}
		return parent::delete();
	}

	/**
	 * Restores a deleted record.
	 * No database query will be performed if the record is not marked as deleted.
	 *
	 * @return bool
	 */
	public function restore()
	{
		if ($this->hasAttribute('deleted')) {
			if ($this->getAttribute('deleted') == static::NO) {
				return true;
			}
			return (bool) $this->updateAttributes(['deleted' => static::NO]);
		}
		return false;
	}

	/**
	 * Gets the unserialized value of an attribute.
	 *
	 * @param string $attribute
	 * @param mixed $defaultValue
	 * @return mixed
	 */
	public function getUnserializedValue($attribute, $defaultValue = null)
	{
		if ($this->hasAttribute($attribute)) {
			$value = $this->$attribute;
			// The attribute may already hold the unserialized form when it was assigned at
			// runtime (e.g. init()/afterFind() filling it before save). Only strings coming
			// from the database need unserializing — on PHP 8 unserialize(array) is a TypeError.
			if (is_array($value)) {
				return $value;
			}
			if (!is_string($value)) {
				return $defaultValue;
			}
			return @unserialize($value) ?: $defaultValue;
		}
		return $defaultValue;
	}

	/**
	 * Sets the serialized value for an attribute.
	 *
	 * @param string $attribute
	 * @param mixed $value
	 * @param bool $merge Flag that indicates if the value should merged or replaced.
	 * @return $this
	 */
	public function setSerializedValue($attribute, $value, $merge = true)
	{
		if ($this->hasAttribute($attribute)) {
			if ($merge === true) {
				$unserializedValue = $this->getUnserializedValue($attribute);
				if (is_array($unserializedValue) && is_array($value)) {
					$this->$attribute = @serialize(array_merge($unserializedValue, $value));
				} else {
					$this->$attribute = @serialize($value);
				}
			} else {
				$this->$attribute = @serialize($value);
			}
		}
		return $this;
	}

	/**
	 * Model boolean labels.
	 *
	 * @return array
	 */
	public static function getBooleanLabels()
	{
		return [
			static::NO => Yii::t('yii', 'No'),
			static::YES => Yii::t('yii', 'Yes'),
		];
	}

	/**
	 * Model status labels.
	 *
	 * @return array
	 */
	public static function getStatusLabels()
	{
		return [
			static::STATUS_INACTIVE => [
				'label' => Yii::t('label', 'Inactive'),
				'color' => 'danger',
			],
			static::STATUS_ACTIVE => [
				'label' => Yii::t('label', 'Active'),
				'color' => 'success',
			],
		];
	}

	/**
	 * Model gender labels.
	 *
	 * @return array
	 */
	public static function getGenderLabels()
	{
		return [
			static::GENDER_MALE => Yii::t('label', 'Male'),
			static::GENDER_FEMALE => Yii::t('label', 'Female'),
		];
	}

	/**
	 * Gets the translated days of the week.
	 *
	 * @param string $format The ICU date format.
	 * @return array
	 */
	public static function getDaysOfWeek($format = 'EEEE')
	{
		try {
			return [
				1 => Yii::$app->formatter->asDatetime('Monday', $format),
				2 => Yii::$app->formatter->asDatetime('Tuesday', $format),
				3 => Yii::$app->formatter->asDatetime('Wednesday', $format),
				4 => Yii::$app->formatter->asDatetime('Thursday', $format),
				5 => Yii::$app->formatter->asDatetime('Friday', $format),
				6 => Yii::$app->formatter->asDatetime('Saturday', $format),
				7 => Yii::$app->formatter->asDatetime('Sunday', $format),
			];
		} catch (\Exception $e) {
			return [];
		}
	}

	/**
	 * Gets the translated months of the year.
	 *
	 * @param string $format The ICU date format.
	 * @return array
	 */
	public static function getMonthsOfYear($format = 'MMMM')
	{
		try {
			return [
				1 => Yii::$app->formatter->asDatetime('January', $format),
				2 => Yii::$app->formatter->asDatetime('February', $format),
				3 => Yii::$app->formatter->asDatetime('March', $format),
				4 => Yii::$app->formatter->asDatetime('April', $format),
				5 => Yii::$app->formatter->asDatetime('May', $format),
				6 => Yii::$app->formatter->asDatetime('June', $format),
				7 => Yii::$app->formatter->asDatetime('July', $format),
				8 => Yii::$app->formatter->asDatetime('August', $format),
				9 => Yii::$app->formatter->asDatetime('September', $format),
				10 => Yii::$app->formatter->asDatetime('October', $format),
				11 => Yii::$app->formatter->asDatetime('November', $format),
				12 => Yii::$app->formatter->asDatetime('December', $format),
			];
		} catch (\Exception $e) {
			return [];
		}
	}

	/**
	 * Ensures a translation value for a given set of data.
	 *
	 * @param array $data Translation source data.
	 * @param string $language Requested language of the translation.
	 * @param string $defaultLanguage Default language to fallback if the requested language translation does not exist.
	 * @param string $prefix A string prefix for missing translation.
	 * @return null|string
	 */
	public static function ensureTranslationValue($data, $language, $defaultLanguage = 'en-US', $prefix = '***MISSING_TRANSLATION***')
	{
		if (!is_array($data)) {
			return null;
		}

		// Return translation for requested language, if exist
		if (!empty($data[$language])) {
			return trim($data[$language]);
		}

		// Return translation for default language, if exist
		if (!empty($data[$defaultLanguage])) {
			return $prefix . ' ' . trim($data[$defaultLanguage]);
		}

		// Get the first non-empty translation from the data
		foreach ($data as $language_id => $translation) {
			if (!empty($translation)) {
				return $prefix . ' ' . trim($translation);
			}
		}

		return $prefix;
	}
}
