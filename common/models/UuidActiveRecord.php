<?php

namespace common\models;

use common\helpers\UuidHelper;

/**
 * Base ActiveRecord for models whose primary key is a UUID v7 stored as BINARY(16).
 *
 * Behaviour is activated per-table by the schema: everything below only kicks in for
 * columns that are actually BINARY(16), so a model whose table still uses an
 * INT AUTO_INCREMENT primary key behaves exactly like before. This makes the class
 * safe to use as the base for every model while tables are converted incrementally.
 *
 * What it does for BINARY(16) tables:
 *  - generates a UUID v7 (in PHP, via ramsey/uuid when available) for `id` on insert;
 *  - normalizes any BINARY(16) attribute that holds a canonical UUID string
 *    (e.g. posted by a form dropdown) to raw bytes before saving;
 *  - exposes `$model->uuid` — the canonical UUID string of the primary key;
 *  - exposes every BINARY(16) attribute as a canonical UUID string in [[fields()]],
 *    so APIs/JSON never leak raw binary;
 *  - provides [[findOneByUuid()]] for controllers' `findModel()` implementations.
 *
 * Queries created via [[find()]] use [[UuidActiveQuery]], which transparently converts
 * UUID strings in `where` conditions to raw bytes for BINARY(16) columns.
 */
class UuidActiveRecord extends CommonActiveRecord
{
	/**
	 * @inheritdoc
	 * @return UuidActiveQuery
	 */
	public static function find()
	{
		return new UuidActiveQuery(get_called_class());
	}

	/**
	 * @inheritdoc
	 *
	 * The generated models still declare their id/foreign key attributes as `integer`
	 * in [[rules()]]. Those columns are BINARY(16) now, so the integer validator would
	 * reject every value (raw bytes loaded from the database or UUID strings posted by
	 * forms) and silently block saving. Instead of rewriting ~110 rule sets, every
	 * BINARY(16) attribute is lifted out of the integer validators here and validated
	 * with [[\common\validators\UuidValidator]] instead.
	 */
	public function createValidators()
	{
		$validators = parent::createValidators();

		$binaryColumns = [];
		foreach (static::getTableSchema()->columns as $name => $column) {
			if ($column->type === 'binary' && (int) $column->size === 16) {
				$binaryColumns[] = $name;
			}
		}
		if (!$binaryColumns) {
			return $validators;
		}

		$lifted = [];
		foreach ($validators as $validator) {
			if ($validator instanceof \yii\validators\NumberValidator && $validator->integerOnly) {
				$matched = array_intersect((array) $validator->attributes, $binaryColumns);
				if ($matched) {
					$validator->attributes = array_values(array_diff((array) $validator->attributes, $matched));
					$lifted = array_merge($lifted, array_values($matched));
				}
			}
		}
		if ($lifted) {
			$validators->append(
				\yii\validators\Validator::createValidator(
					\common\validators\UuidValidator::class,
					$this,
					array_unique($lifted)
				)
			);
		}

		return $validators;
	}

	/**
	 * Checks if the model's `id` column is a BINARY(16) UUID primary key.
	 *
	 * @return bool
	 */
	public static function hasUuidPrimaryKey()
	{
		$column = static::getTableSchema()->getColumn('id');
		return $column !== null && $column->type === 'binary' && (int) $column->size === 16;
	}

	/**
	 * Converts a canonical UUID string to raw 16 bytes (the DB representation).
	 *
	 * @param string $uuid
	 * @return string
	 */
	public static function uuidToBytes($uuid)
	{
		return UuidHelper::toBytes($uuid);
	}

	/**
	 * Converts raw 16 bytes (the DB representation) to a canonical UUID string.
	 *
	 * @param string $bytes
	 * @return string
	 */
	public static function bytesToUuid($bytes)
	{
		return UuidHelper::fromBytes($bytes);
	}

	/**
	 * The canonical UUID string of the primary key — use this in URLs and links:
	 * `['view', 'id' => $model->uuid]`.
	 *
	 * For models whose table has not (yet) been converted to BINARY(16), the plain
	 * primary key is returned as a string, so `$model->uuid` is always URL-safe.
	 *
	 * @return string|null
	 */
	public function getUuid()
	{
		if (!$this->hasAttribute('id')) {
			return null;
		}
		$id = $this->getAttribute('id');
		if ($id === null || $id === '') {
			return null;
		}
		if (static::hasUuidPrimaryKey()) {
			return UuidHelper::fromBytes($id);
		}
		return (string) $id;
	}

	/**
	 * The canonical UUID string of any BINARY(16) attribute — handy for building
	 * paths in double-quoted strings: "@uploads/x/{$model->uuidOf('parent_id')}".
	 * Non-binary values (e.g. integer keys of unconverted tables) pass through as strings.
	 *
	 * @param string $attribute
	 * @return string
	 */
	public function uuidOf($attribute)
	{
		$value = $this->getAttribute($attribute);
		if ($value === null || $value === '') {
			return '';
		}
		if (UuidHelper::isBytes($value) && !mb_check_encoding($value, 'UTF-8')) {
			return UuidHelper::fromBytes($value);
		}
		return (string) $value;
	}

	/**
	 * Finds a record by the canonical UUID string received from a URL/API.
	 * Raw 16-byte values are accepted too; invalid values simply return `null`
	 * (the caller decides between 404 and a validation error).
	 *
	 * For models whose table still uses an integer primary key, this falls back
	 * to a plain [[findOne()]], so controllers can use it unconditionally.
	 *
	 * @param string $uuid
	 * @return static|null
	 */
	public static function findOneByUuid($uuid)
	{
		if (!static::hasUuidPrimaryKey()) {
			return is_scalar($uuid) ? static::findOne($uuid) : null;
		}
		if (UuidHelper::isValid($uuid)) {
			return static::findOne(['id' => UuidHelper::toBytes($uuid)]);
		}
		if (UuidHelper::isBytes($uuid)) {
			return static::findOne(['id' => $uuid]);
		}
		return null;
	}

	/**
	 * @inheritdoc
	 */
	public function beforeSave($insert)
	{
		if (!parent::beforeSave($insert)) {
			return false;
		}

		// Generate the UUID v7 primary key for new records
		if ($insert && static::hasUuidPrimaryKey()) {
			$id = $this->getAttribute('id');
			if ($id === null || $id === '') {
				$this->setAttribute('id', UuidHelper::uuid7Bytes());
			}
		}

		// Normalize UUID strings (e.g. posted by forms into FK attributes) to raw bytes
		foreach (static::getTableSchema()->columns as $name => $column) {
			if ($column->type === 'binary' && (int) $column->size === 16) {
				$value = $this->getAttribute($name);
				if (UuidHelper::isValid($value)) {
					$this->setAttribute($name, UuidHelper::toBytes($value));
				} elseif ($value === '' && $column->allowNull) {
					// An optional FK left empty by a form must be stored as NULL, not an
					// empty string, otherwise the foreign key constraint rejects it.
					$this->setAttribute($name, null);
				}
			}
		}

		return true;
	}

	/**
	 * @inheritdoc
	 *
	 * Every BINARY(16) attribute is exposed as a canonical UUID string, so the raw
	 * binary never leaks into JSON/API responses.
	 */
	public function fields()
	{
		$fields = parent::fields();
		foreach (static::getTableSchema()->columns as $name => $column) {
			if ($column->type === 'binary' && (int) $column->size === 16 && array_key_exists($name, $fields)) {
				$fields[$name] = function ($model) use ($name) {
					$value = $model->getAttribute($name);
					if ($value === null || $value === '') {
						return null;
					}
					return UuidHelper::fromBytes($value);
				};
			}
		}
		return $fields;
	}
}
