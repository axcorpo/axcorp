<?php

use common\db\Migration;

/**
 * Converts `auth_assignment.user_id` from a VARCHAR holding the canonical UUID string
 * to BINARY(16), with a foreign key to `user.id`.
 *
 * Requires `common\rbac\DbManager` as the authManager: the identity keeps exposing the
 * UUID string, the manager converts it to bytes at the RBAC boundary.
 *
 * Rows whose user_id does not resolve to an existing user (or is not a UUID string,
 * e.g. leftovers from before the UUID conversion) are dropped — the new foreign key
 * could not hold them anyway.
 */
class m260713_120000_convert_auth_assignment_user_id_to_binary extends Migration
{
	const UUID_PATTERN = '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$';

	public function up()
	{
		$table = $this->db->getTableSchema('{{%auth_assignment}}', true);
		if ($table === null) {
			echo "    auth_assignment does not exist yet, skipping.\n";
			return;
		}
		$column = $table->getColumn('user_id');
		if ($column->type === 'binary' && (int) $column->size === 16) {
			echo "    auth_assignment.user_id is already BINARY(16), skipping.\n";
			return;
		}

		// Backfill the binary value from the canonical UUID string
		$this->execute('ALTER TABLE {{%auth_assignment}} ADD COLUMN [[user_id_bin]] BINARY(16) NULL AFTER [[user_id]]');
		$this->execute(
			"UPDATE {{%auth_assignment}} SET [[user_id_bin]] = UNHEX(REPLACE([[user_id]], '-', ''))"
			. " WHERE [[user_id]] REGEXP '" . self::UUID_PATTERN . "'"
		);

		// Rows that could not be converted or that reference a missing user cannot
		// satisfy the foreign key
		$this->execute('DELETE FROM {{%auth_assignment}} WHERE [[user_id_bin]] IS NULL');
		$this->execute(
			'DELETE a FROM {{%auth_assignment}} a LEFT JOIN {{%user}} u ON u.[[id]] = a.[[user_id_bin]]'
			. ' WHERE u.[[id]] IS NULL'
		);

		// Swap the column, keeping the composite primary key and the user_id index
		$this->execute(
			'ALTER TABLE {{%auth_assignment}}'
			. ' DROP PRIMARY KEY,'
			. ' DROP KEY [[auth_assignment_user_id_idx]],'
			. ' DROP COLUMN [[user_id]],'
			. ' CHANGE [[user_id_bin]] [[user_id]] BINARY(16) NOT NULL,'
			. ' ADD PRIMARY KEY ([[item_name]], [[user_id]])'
		);
		$this->createIndex('auth_assignment_user_id_idx', '{{%auth_assignment}}', 'user_id');
		$this->addForeignKey(
			'fk_auth_assignment_user',
			'{{%auth_assignment}}',
			'user_id',
			'{{%user}}',
			'id',
			'CASCADE',
			'CASCADE'
		);
	}

	public function down()
	{
		$this->dropForeignKey('fk_auth_assignment_user', '{{%auth_assignment}}');
		$this->execute('ALTER TABLE {{%auth_assignment}} ADD COLUMN [[user_id_str]] VARCHAR(64) NULL AFTER [[user_id]]');
		$this->execute(
			"UPDATE {{%auth_assignment}} SET [[user_id_str]] = LOWER(CONCAT("
			. "SUBSTR(HEX([[user_id]]), 1, 8), '-', "
			. "SUBSTR(HEX([[user_id]]), 9, 4), '-', "
			. "SUBSTR(HEX([[user_id]]), 13, 4), '-', "
			. "SUBSTR(HEX([[user_id]]), 17, 4), '-', "
			. "SUBSTR(HEX([[user_id]]), 21, 12)))"
		);
		$this->execute(
			'ALTER TABLE {{%auth_assignment}}'
			. ' DROP PRIMARY KEY,'
			. ' DROP KEY [[auth_assignment_user_id_idx]],'
			. ' DROP COLUMN [[user_id]],'
			. ' CHANGE [[user_id_str]] [[user_id]] VARCHAR(64) NOT NULL,'
			. ' ADD PRIMARY KEY ([[item_name]], [[user_id]])'
		);
		$this->createIndex('auth_assignment_user_id_idx', '{{%auth_assignment}}', 'user_id');
	}
}
