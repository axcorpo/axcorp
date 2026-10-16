<?php

use common\db\Migration;
use yii\rbac\DbManager;

/**
 * RBAC permissions of the affiliate module (docs/afiliere.md), granted to superAdmin so
 * the module is usable right away; other roles get them through the role editor
 * (AuthItem::getAllPermissions()). There is deliberately no affiliate ROLE: affiliates are
 * identified by their `affiliate` row and must not reach the backend.
 */
class m261001_120000_add_affiliate_permissions extends Migration
{
	/**
	 * @var string The role every new permission is granted to.
	 */
	private $role = 'superAdmin';

	/**
	 * @var string[]
	 */
	private $permissions = [
		'viewAffiliate',
		'createAffiliate',
		'updateAffiliate',
		'deleteAffiliate',
		'manageAffiliatePayout',
		'updateAffiliateSetting',
	];

	public function up()
	{
		$now = time();
		$roleExists = (new \yii\db\Query())
			->from('{{%auth_item}}')
			->where(['name' => $this->role, 'type' => 1])
			->exists($this->db);

		foreach ($this->permissions as $permission) {
			$exists = (new \yii\db\Query())
				->from('{{%auth_item}}')
				->where(['name' => $permission])
				->exists($this->db);
			if ($exists) {
				echo "    permission `{$permission}` already exists, skipping.\n";
			} else {
				$this->insert('{{%auth_item}}', [
					'name' => $permission,
					'type' => 2,
					'created_at' => $now,
					'updated_at' => $now,
				]);
			}

			if (!$roleExists) {
				continue;
			}
			$granted = (new \yii\db\Query())
				->from('{{%auth_item_child}}')
				->where(['parent' => $this->role, 'child' => $permission])
				->exists($this->db);
			if (!$granted) {
				$this->insert('{{%auth_item_child}}', [
					'parent' => $this->role,
					'child' => $permission,
				]);
			}
		}
		if (!$roleExists) {
			echo "    role `{$this->role}` not found, permissions created without a grant.\n";
		}
		$this->invalidateRbacCache();
	}

	public function down()
	{
		foreach ($this->permissions as $permission) {
			$this->delete('{{%auth_item_child}}', ['child' => $permission]);
			$this->delete('{{%auth_item}}', ['name' => $permission]);
		}
		$this->invalidateRbacCache();
	}

	/**
	 * The RBAC tree is cached (`rbac` key); drop it so the grants apply immediately.
	 */
	private function invalidateRbacCache()
	{
		$authManager = Yii::$app->has('authManager') ? Yii::$app->getAuthManager() : null;
		if ($authManager instanceof DbManager) {
			$authManager->invalidateCache();
		}
	}
}
