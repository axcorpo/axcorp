<?php

use common\db\Migration;

/**
 * Removes the RBAC permissions of the workspace Fields area: the ws-field /
 * ws-field-option / ws-category-field / ws-category-field-option screens were
 * dropped from the backend, so nothing checks these permissions anymore.
 */
class m260728_120000_remove_ws_field_permissions extends Migration
{
	/**
	 * @var array The permission names to remove.
	 */
	private $permissions = [
		'viewField',
		'createField',
		'updateField',
		'deleteField',
		'restoreField',
	];

	public function up()
	{
		foreach ($this->permissions as $permission) {
			$this->delete('{{%auth_item_child}}', ['child' => $permission]);
			$this->delete('{{%auth_item}}', ['name' => $permission]);
		}
	}

	public function down()
	{
		$now = time();

		foreach ($this->permissions as $permission) {
			$this->insert('{{%auth_item}}', [
				'name' => $permission,
				'type' => 2,
				'created_at' => $now,
				'updated_at' => $now,
			]);
		}
	}
}
