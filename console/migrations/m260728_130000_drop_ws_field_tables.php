<?php

use common\db\Migration;

/**
 * Drops the workspace field/option tables: the ws-field backend area and the
 * `WsField`/`WsOption`/`WsFieldValue`/`WsCategoryField`/`WsCategoryHasOption`
 * models were removed, so nothing reads or writes these tables anymore.
 *
 * Children first, so every foreign key is gone before its referenced table.
 * Mirrors `master/_database/001_remove_unused_ws_fields_tables.sql`.
 */
class m260728_130000_drop_ws_field_tables extends Migration
{
	/**
	 * @var array The tables to drop, in dependency order (children first).
	 */
	private $tables = [
		'{{%ws_field_value}}',
		'{{%ws_category_field}}',
		'{{%ws_category_has_option}}',
		'{{%ws_option_translation}}',
		'{{%ws_field_translation}}',
		'{{%ws_option}}',
		'{{%ws_field}}',
	];

	public function up()
	{
		foreach ($this->tables as $table) {
			$this->dropTable($table);
		}
	}

	public function down()
	{
		echo "m260728_130000_drop_ws_field_tables cannot be reverted: restore the tables from a backup.\n";
		return false;
	}
}
