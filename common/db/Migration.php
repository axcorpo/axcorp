<?php

namespace common\db;

use common\helpers\UuidHelper;

/**
 * Base migration with helpers for UUID v7 primary keys stored as BINARY(16).
 *
 * Usage:
 * ```php
 * $this->createTable('{{%order}}', [
 *     'id' => $this->binaryUuidPrimaryKey(),
 *     'user_id' => $this->binaryUuid(),
 *     ...
 * ]);
 * ```
 *
 * Note: UUIDs are always generated in PHP ([[UuidHelper::uuid7Bytes()]]) — never with
 * MySQL's `UUID()` (v1) and never with `UUID_TO_BIN(..., 1)` (its byte-swap is meant
 * for v1 timestamps and would destroy the v7 time-ordering).
 */
class Migration extends \yii\db\Migration
{
	/**
	 * BINARY(16) UUID primary key column definition.
	 *
	 * @return string
	 */
	public function binaryUuidPrimaryKey()
	{
		return 'BINARY(16) NOT NULL PRIMARY KEY';
	}

	/**
	 * BINARY(16) UUID column definition, for foreign keys and plain UUID columns.
	 *
	 * @param bool $notNull
	 * @return string
	 */
	public function binaryUuid($notNull = true)
	{
		return $notNull ? 'BINARY(16) NOT NULL' : 'BINARY(16) NULL DEFAULT NULL';
	}

	/**
	 * Generates a new UUID v7 as raw bytes, for seeding rows from migrations.
	 *
	 * @return string 16 raw bytes
	 */
	public function newUuidBytes()
	{
		return UuidHelper::uuid7Bytes();
	}
}
