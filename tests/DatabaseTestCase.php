<?php

namespace tests;

use common\helpers\UuidHelper;
use PHPUnit\Framework\TestCase;
use Yii;

/**
 * Base class for database-backed unit tests.
 *
 * Every test runs inside a transaction that is rolled back in tearDown, so tests are
 * isolated and the `master_test` schema stays empty. Foreign key checks are disabled
 * inside the transaction, so fixtures only need the rows relevant to the scenario.
 */
abstract class DatabaseTestCase extends TestCase
{
	/**
	 * @var \yii\db\Transaction
	 */
	private $transaction;

	protected function setUp(): void
	{
		parent::setUp();
		$this->transaction = Yii::$app->db->beginTransaction();
		Yii::$app->db->createCommand('SET FOREIGN_KEY_CHECKS = 0')->execute();
	}

	protected function tearDown(): void
	{
		if ($this->transaction) {
			$this->transaction->rollBack();
		}
		Yii::$app->db->createCommand('SET FOREIGN_KEY_CHECKS = 1')->execute();
		parent::tearDown();
	}

	/**
	 * A fresh BINARY(16) UUID v7 primary key value.
	 *
	 * @return string raw 16 bytes
	 */
	protected function uuidBytes()
	{
		return UuidHelper::uuid7Bytes();
	}

	/**
	 * Inserts a row filling every NOT NULL column without a default with a
	 * type-appropriate dummy value, so fixtures only specify what the scenario needs.
	 *
	 * @param string $table
	 * @param array $attributes
	 * @return string the row's `id` (raw bytes) when the table has one, otherwise ''
	 */
	protected function insertRow($table, array $attributes = [])
	{
		$schema = Yii::$app->db->getTableSchema($table, true);
		$row = $attributes;

		foreach ($schema->columns as $name => $column) {
			if (array_key_exists($name, $row) || $column->allowNull || $column->defaultValue !== null || $column->autoIncrement) {
				continue;
			}
			if ($column->type === 'binary') {
				$row[$name] = $this->uuidBytes();
			} elseif (in_array($column->type, ['integer', 'smallint', 'bigint', 'tinyint', 'boolean'], true)) {
				$row[$name] = 0;
			} elseif (in_array($column->type, ['decimal', 'float', 'double', 'money'], true)) {
				$row[$name] = 0;
			} elseif (in_array($column->type, ['datetime', 'timestamp', 'date', 'time'], true)) {
				$row[$name] = date('Y-m-d H:i:s');
			} else {
				$row[$name] = 'test-' . substr(bin2hex(random_bytes(6)), 0, 8);
			}
		}

		if (isset($schema->columns['id']) && !array_key_exists('id', $row)) {
			$row['id'] = $schema->columns['id']->type === 'binary' ? $this->uuidBytes() : null;
		}

		Yii::$app->db->createCommand()->insert($table, $row)->execute();

		return isset($row['id']) ? $row['id'] : '';
	}

	/**
	 * Fills every NOT NULL column without a default of an unsaved ActiveRecord with a
	 * type-appropriate dummy value (never `id`: the model generates it), so a test only
	 * sets the attributes the scenario is about.
	 *
	 * @param \yii\db\ActiveRecord $model
	 * @return \yii\db\ActiveRecord the same model
	 */
	protected function fillRequired(\yii\db\ActiveRecord $model)
	{
		foreach ($model::getTableSchema()->columns as $name => $column) {
			if ($name === 'id' || $model->getAttribute($name) !== null || $column->allowNull || $column->defaultValue !== null || $column->autoIncrement) {
				continue;
			}
			if ($column->type === 'binary') {
				$model->setAttribute($name, $this->uuidBytes());
			} elseif (in_array($column->type, ['integer', 'smallint', 'bigint', 'tinyint', 'boolean', 'decimal', 'float', 'double', 'money'], true)) {
				$model->setAttribute($name, 0);
			} elseif (in_array($column->type, ['datetime', 'timestamp', 'date', 'time'], true)) {
				$model->setAttribute($name, date('Y-m-d H:i:s'));
			} else {
				$model->setAttribute($name, 'test-' . substr(bin2hex(random_bytes(6)), 0, 8));
			}
		}
		return $model;
	}

	/**
	 * Creates an active subscription with sensible defaults. A real subscriber row is
	 * created too, so the model's `exist` validator accepts the subscription on save.
	 *
	 * @param array $attributes
	 * @return string subscription id (raw bytes)
	 */
	protected function createSubscription(array $attributes = [])
	{
		if (!array_key_exists('subscriber_id', $attributes)) {
			$attributes['subscriber_id'] = $this->insertRow('subscriber', ['deleted' => 0]);
		} elseif ($attributes['subscriber_id'] !== null) {
			$exists = (new \yii\db\Query())
				->from('subscriber')
				->where(['id' => $attributes['subscriber_id']])
				->exists(Yii::$app->db);
			if (!$exists) {
				$this->insertRow('subscriber', ['id' => $attributes['subscriber_id'], 'deleted' => 0]);
			}
		}

		return $this->insertRow('subscription', array_merge([
			'code' => substr(bin2hex(random_bytes(6)), 0, 8),
			'billing_period' => 1,
			'billing_cycle' => 'month',
			'start_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
			'end_at' => date('Y-m-d H:i:s', strtotime('+20 days')),
			'type' => \common\models\Subscription::TYPE_STANDARD,
			'status' => \common\models\Subscription::STATUS_ACTIVE,
			'deleted' => 0,
		], $attributes));
	}

	/**
	 * Reads one column of a row by binary id.
	 *
	 * @param string $table
	 * @param string $idBytes
	 * @param string $column
	 * @return mixed
	 */
	protected function fetchColumn($table, $idBytes, $column)
	{
		return (new \yii\db\Query())
			->select($column)
			->from($table)
			->where(['id' => $idBytes])
			->scalar(Yii::$app->db);
	}
}
