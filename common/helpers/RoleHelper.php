<?php

namespace common\helpers;

use common\models\AuthItem;
use Yii;

/**
 * Privilege-escalation guards for the role and account-management screens.
 *
 * Roles here are not a fixed ladder — administrators create them at runtime as bags of
 * permissions (see [[\backend\modules\user\models\RoleForm]]), so "higher" cannot be read
 * off a rank column. What holds regardless of how the roles are cut is that nobody may
 * hand out authority they do not hold themselves, so every check below reduces to one
 * subset test: the permissions being granted must already be among the caller's own.
 *
 * That alone stops the escalation the assessment found — a limited user-manager granting
 * itself an administrative role, or writing every permission into a role it is allowed to
 * edit and then assigning that. `superAdmin` is additionally sealed off: it is the
 * bootstrap role every permission ultimately hangs off, and it is not meant to be handed
 * around by anyone who is not already in it.
 */
class RoleHelper
{
	/**
	 * The bootstrap role. Only a holder may grant it or act on an account that has it.
	 */
	const SUPER_ADMIN = 'superAdmin';

	/**
	 * Whether the given user holds [[SUPER_ADMIN]].
	 *
	 * @param string|int|null $userId defaults to the current user
	 * @return bool
	 */
	public static function isSuperAdmin($userId = null)
	{
		return in_array(static::SUPER_ADMIN, static::rolesOf($userId), true);
	}

	/**
	 * The role names assigned to a user.
	 *
	 * @param string|int|null $userId defaults to the current user
	 * @return string[]
	 */
	public static function rolesOf($userId = null)
	{
		$userId = $userId === null ? Yii::$app->user->id : $userId;
		if ($userId === null) {
			return [];
		}

		return array_keys(Yii::$app->authManager->getRolesByUser($userId));
	}

	/**
	 * Every permission a user effectively holds, through any of their roles.
	 *
	 * @param string|int|null $userId defaults to the current user
	 * @return string[]
	 */
	public static function permissionsOf($userId = null)
	{
		$userId = $userId === null ? Yii::$app->user->id : $userId;
		if ($userId === null) {
			return [];
		}

		return array_keys(Yii::$app->authManager->getPermissionsByUser($userId));
	}

	/**
	 * The permissions a role carries.
	 *
	 * @param string $roleName
	 * @return string[]
	 */
	public static function permissionsOfRole($roleName)
	{
		return array_keys(Yii::$app->authManager->getPermissionsByRole($roleName));
	}

	/**
	 * Whether the current user may grant the given role.
	 *
	 * An empty role name means "clear the assignment", which grants nothing and is allowed.
	 *
	 * @param string|null $roleName
	 * @return bool
	 */
	public static function canGrantRole($roleName)
	{
		if (static::isSuperAdmin()) {
			return true;
		}
		if ($roleName === null || $roleName === '') {
			return true;
		}
		if ($roleName === static::SUPER_ADMIN) {
			return false;
		}

		return static::grantsNothingNew(static::permissionsOfRole($roleName));
	}

	/**
	 * Whether the current user may act on the target account.
	 *
	 * Refuses when the target holds authority the caller does not, which covers both
	 * equal- and higher-privileged accounts, and refuses any change to one's own roles.
	 *
	 * @param string|int $targetUserId
	 * @return bool
	 */
	public static function canManageUser($targetUserId)
	{
		if (static::isSuperAdmin()) {
			return true;
		}
		if (static::isSuperAdmin($targetUserId)) {
			return false;
		}

		return static::grantsNothingNew(static::permissionsOf($targetUserId));
	}

	/**
	 * Whether the current user may change the role assignment of the target account.
	 *
	 * Changing one's own is always refused: it is the shortest escalation path, and a
	 * legitimate promotion is somebody else's to perform.
	 *
	 * @param string|int $targetUserId
	 * @return bool
	 */
	public static function canChangeRoleOf($targetUserId)
	{
		if (static::isOwnAccount($targetUserId)) {
			return false;
		}

		return static::canManageUser($targetUserId);
	}

	/**
	 * Whether the current user may save a role carrying exactly these permissions.
	 *
	 * Guards role definition, not just assignment: writing a permission into a role you
	 * can edit and then assigning that role is the same escalation by a longer route.
	 *
	 * @param string|null $roleName null for a role being created, which carries nothing yet
	 * @param string[] $permissions the permission set the role is being saved with
	 * @return bool
	 */
	public static function canDefineRole($roleName, array $permissions)
	{
		if (static::isSuperAdmin()) {
			return true;
		}
		if ($roleName === static::SUPER_ADMIN) {
			return false;
		}
		if (!static::grantsNothingNew($permissions)) {
			return false;
		}
		// Anything being removed must be within reach too, so a caller cannot strip
		// permissions off a role more privileged than itself. A role being created
		// carries none yet.
		return $roleName === null || $roleName === ''
			|| static::grantsNothingNew(static::permissionsOfRole($roleName));
	}

	/**
	 * The roles the current user may actually grant, for populating the pickers.
	 *
	 * @return AuthItem[]
	 */
	public static function grantableRoles()
	{
		return array_values(array_filter(AuthItem::findAllRoles(), function ($role) {
			return static::canGrantRole($role->name);
		}));
	}

	/**
	 * @param string|int $userId
	 * @return bool whether the id is the current user's own
	 */
	public static function isOwnAccount($userId)
	{
		return strcasecmp((string) Yii::$app->user->id, (string) $userId) === 0;
	}

	/**
	 * Whether the given permissions are all already held by the current user.
	 *
	 * @param string[] $permissions
	 * @return bool
	 */
	protected static function grantsNothingNew(array $permissions)
	{
		return empty(array_diff($permissions, static::permissionsOf()));
	}
}
