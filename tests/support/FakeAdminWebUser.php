<?php

namespace tests\support;

/**
 * Test double for yii\web\User that passes every RBAC check, so backend
 * controllers guarded by AccessControl role rules can run without seeding the
 * whole auth_* hierarchy.
 *
 * Tests that are about authorization itself set [[grantedPermissions]] to the
 * permissions the caller is supposed to hold — an empty array models an ordinary
 * account with no rights at all, which is what the negative cases need.
 */
class FakeAdminWebUser extends \yii\web\User
{
	/**
	 * @var string[]|null The permissions this user holds, or null to pass everything.
	 */
	public $grantedPermissions;

	/**
	 * @inheritdoc
	 */
	public function can($permissionName, $params = [], $allowCaching = true)
	{
		if ($this->grantedPermissions === null) {
			return true;
		}

		return in_array($permissionName, $this->grantedPermissions, true);
	}
}
