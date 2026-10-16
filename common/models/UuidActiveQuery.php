<?php

namespace common\models;

use common\helpers\UuidHelper;
use Yii;
use yii\db\ExpressionInterface;
use yii\db\Query;

/**
 * ActiveQuery for models whose primary/foreign keys are UUIDs stored as BINARY(16).
 *
 * Canonical UUID strings coming from URLs/forms (e.g. `['id' => $uuid]` conditions in
 * the controllers' `findModel()`) are transparently converted to their raw 16-byte
 * representation for every column that is a BINARY(16) column, so the existing
 * `Model::find()->andWhere(['id' => $id])` code keeps working unchanged.
 *
 * Only hash conditions and simple operator conditions (`=`, `!=`, `<>`, `IN`, `NOT IN`)
 * are normalized; raw SQL string conditions must convert values themselves via
 * [[\common\helpers\UuidHelper::toBytes()]].
 */
class UuidActiveQuery extends CommonActiveQuery
{
	/**
	 * @var array|null Cached map of `column name => true` for BINARY(16) columns across the schema.
	 */
	private static $_binaryUuidColumns;

	/**
	 * @inheritdoc
	 */
	public function prepare($builder)
	{
		if ($this->where !== null) {
			$this->where = $this->normalizeUuidCondition($this->where);
		}
		$query = parent::prepare($builder);
		// Relation link conditions (e.g. lazy-loading `$model->subscription` after a
		// form exposed the FK as a UUID string) are added by the parent inside
		// prepare(), so the built query must be normalized as well.
		if ($query->where !== null) {
			$query->where = $this->normalizeUuidCondition($query->where);
		}
		return $query;
	}

	/**
	 * Recursively converts canonical UUID string values to raw bytes for BINARY(16) columns.
	 *
	 * @param mixed $condition
	 * @return mixed
	 */
	protected function normalizeUuidCondition($condition)
	{
		if (!is_array($condition)) {
			return $condition;
		}

		if (isset($condition[0]) && is_string($condition[0])) {
			// Operator format: [operator, operand1, operand2, ...]
			$operator = strtoupper($condition[0]);
			if (in_array($operator, ['AND', 'OR', 'NOT'], true)) {
				foreach ($condition as $index => $operand) {
					if ($index > 0) {
						$condition[$index] = $this->normalizeUuidCondition($operand);
					}
				}
			} elseif (in_array($operator, ['=', '!=', '<>', 'IN', 'NOT IN'], true)
				&& isset($condition[1], $condition[2])
			) {
				if (is_string($condition[1])) {
					$condition[2] = $this->normalizeUuidValue($condition[1], $condition[2]);
				} elseif (is_array($condition[1]) && count($condition[1]) === 1 && is_string(reset($condition[1]))) {
					// Single-column list form, e.g. ['in', ['id'], $values] as built by
					// ActiveQuery::filterByModels() when lazy-loading relations
					$condition[2] = $this->normalizeUuidValue(reset($condition[1]), $condition[2]);
				}
			}
			return $condition;
		}

		// Hash format: ['column' => value, ...]
		foreach ($condition as $column => $value) {
			if (is_string($column)) {
				$condition[$column] = $this->normalizeUuidValue($column, $value);
			}
		}
		return $condition;
	}

	/**
	 * Converts a single condition value (or list of values) to raw bytes when the
	 * target column is a BINARY(16) column and the value is a canonical UUID string.
	 *
	 * @param string $column Column name, possibly alias-prefixed and/or quoted.
	 * @param mixed $value
	 * @return mixed
	 */
	protected function normalizeUuidValue($column, $value)
	{
		if ($value instanceof ExpressionInterface || $value instanceof Query) {
			return $value;
		}

		$hasUuidString = false;
		foreach ((array) $value as $item) {
			if (UuidHelper::isValid($item)) {
				$hasUuidString = true;
				break;
			}
		}
		if (!$hasUuidString || !$this->isBinaryUuidColumn($column)) {
			return $value;
		}

		if (is_array($value)) {
			foreach ($value as $key => $item) {
				if (UuidHelper::isValid($item)) {
					$value[$key] = UuidHelper::toBytes($item);
				}
			}
			return $value;
		}
		return UuidHelper::toBytes($value);
	}

	/**
	 * Checks if the (possibly alias-prefixed) column is a BINARY(16) column, first against
	 * the schema of the query's model, then against the whole database schema.
	 *
	 * @param string $column
	 * @return bool
	 */
	protected function isBinaryUuidColumn($column)
	{
		$name = $column;
		if (($pos = strrpos($name, '.')) !== false) {
			$name = substr($name, $pos + 1);
		}
		$name = trim($name, ' `"[]');
		if ($name === '' || strpos($name, '(') !== false) {
			return false;
		}

		/* @var $modelClass \yii\db\ActiveRecord */
		$modelClass = $this->modelClass;
		if ($modelClass !== null) {
			$schema = $modelClass::getTableSchema();
			$columnSchema = $schema->getColumn($name);
			if ($columnSchema !== null) {
				return $columnSchema->type === 'binary' && (int) $columnSchema->size === 16;
			}
		}

		// Column not part of this model's table (e.g. joined table alias): fall back to a
		// database-wide map of column names that are BINARY(16) in every table defining them.
		$map = self::getBinaryUuidColumnMap();
		return isset($map[$name]) && $map[$name] === true;
	}

	/**
	 * Builds a database-wide map of `column name => bool` where the value is `true` only
	 * when every table defining that column name declares it as BINARY(16).
	 *
	 * @return array
	 */
	protected static function getBinaryUuidColumnMap()
	{
		if (self::$_binaryUuidColumns !== null) {
			return self::$_binaryUuidColumns;
		}
		$map = [];
		try {
			foreach (Yii::$app->getDb()->getSchema()->getTableSchemas() as $tableSchema) {
				foreach ($tableSchema->columns as $columnSchema) {
					$isBinaryUuid = $columnSchema->type === 'binary' && (int) $columnSchema->size === 16;
					if (!array_key_exists($columnSchema->name, $map)) {
						$map[$columnSchema->name] = $isBinaryUuid;
					} elseif ($map[$columnSchema->name] !== $isBinaryUuid) {
						// Ambiguous column name (BINARY(16) in one table, something else in another)
						$map[$columnSchema->name] = false;
					}
				}
			}
		} catch (\Throwable $e) {
			Yii::warning('Unable to build the BINARY(16) column map: ' . $e->getMessage(), __METHOD__);
		}
		return self::$_binaryUuidColumns = $map;
	}
}
