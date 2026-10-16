<?php
namespace console\controllers;

use common\models\AuthItem;
use common\models\User;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\ArrayHelper;

class RbacController extends Controller
{
	/**
	 * @var array The permissions list.
	 */
	public $permissions = [];

	/**
	 * @var \yii\rbac\Role The superAdmin Role object.
	 */
	private $_superAdminRole;

	/**
	 * @inheritdoc
	 * @throws \Exception
	 */
	public function init()
	{
		parent::init();

		$this->permissions = AuthItem::getAllPermissions();
	}

	/**
	 * Initializes RBAC items.
	 *
	 * @throws \yii\base\Exception
	 * @throws \Exception
	 */
	public function actionInit()
	{
		if (!$this->confirm('Are you sure? Old RBAC records will be removed!')) {
			return ExitCode::UNSPECIFIED_ERROR;
		}

		$authManager = Yii::$app->authManager;

		// Keep the current superAdmin assignees across the rebuild; primary keys are
		// UUIDs now, so the old hardcoded user id `1` no longer exists (and would
		// violate the auth_assignment -> user foreign key)
		$superAdminUserIds = $authManager->getUserIdsByRole('superAdmin');

		// Start clean
		$authManager->removeAll();

		// Add modules permissions
		$permissions = self::getPermissionsList($this->permissions);

		foreach ($permissions as $permissionName) {
			$permission = $authManager->createPermission($permissionName);
			$authManager->add($permission);
			$authManager->addChild($this->getSuperAdminRole(), $permission);
		}

		// Re-assign the superAdmin role; on a fresh setup fall back to the "admin"
		// user, then to the oldest user. The identity exposes the canonical UUID
		// string via User::getId(); common\rbac\DbManager converts it to the
		// BINARY(16) auth_assignment.user_id.
		if (empty($superAdminUserIds)) {
			$admin = User::findOne(['username' => 'admin'])
				?: User::find()->orderBy(['id' => SORT_ASC])->one();
			if ($admin !== null) {
				$superAdminUserIds = [$admin->getId()];
			}
		}
		foreach ($superAdminUserIds as $userId) {
			$authManager->assign($this->getSuperAdminRole(), $userId);
		}

		echo "\n[RBAC] successfully initialized! superAdmin assigned to " . count($superAdminUserIds) . " user(s).\n";

		return ExitCode::OK;
	}

	/**
	 * Optimize RBAC items.
	 *
	 * @throws \yii\base\Exception
	 * @throws \Exception
	 */
	public function actionOptimize()
	{
		if (!$this->confirm('Are you sure? RBAC records could be added or removed from database!')) {
			return ExitCode::UNSPECIFIED_ERROR;
		}

		$authManager = Yii::$app->authManager;
		$existingPermissions = array_keys($authManager->getPermissions());
		$allPermissions = self::getPermissionsList($this->permissions);
		$newPermissions = array_diff($allPermissions, $existingPermissions);
		$deletePermissions = array_diff($existingPermissions, $allPermissions);

		foreach ($newPermissions as $newPermission) {
			$permission = $authManager->createPermission($newPermission);
			$authManager->add($permission);
			$authManager->addChild($this->getSuperAdminRole(), $permission);
		}
		echo "\n[RBAC] successfully added " . count($newPermissions) . " permissions!\n";

		foreach ($deletePermissions as $deletePermission) {
			$permission = $authManager->getPermission($deletePermission);
			$authManager->remove($permission);
		}
		echo "[RBAC] successfully deleted " . count($deletePermissions) . " permissions!\n";

		return ExitCode::OK;
	}

	/**
	 * Removes all RBAC items.
	 */
	public function actionRemoveAll()
	{
		if (!$this->confirm('Are you sure you want to remove all RBAC records?')) {
			return ExitCode::UNSPECIFIED_ERROR;
		}

		Yii::$app->authManager->removeAll();

		echo "\n[RBAC] Successfully removed all permissions!\n";

		return ExitCode::OK;
	}

	/**
	 * Gets the superAdmin Role object.
	 *
	 * @return null|string|\yii\rbac\Role
	 * @throws \Exception
	 */
	public function getSuperAdminRole()
	{
		if (!$this->_superAdminRole) {
			$authManager = Yii::$app->authManager;

			if ($superAdmin = $authManager->getRole('superAdmin')) {
				$this->_superAdminRole = $superAdmin;
			} else {
				$superAdmin = $authManager->createRole('superAdmin');
				$superAdmin->description = 'Super Admin';
				$authManager->add($superAdmin);

				$this->_superAdminRole = $superAdmin;
			}
		}
		return $this->_superAdminRole;
	}

	/**
	 * Gets the permissions list as a single dimensional array.
	 *
	 * @param array $data
	 * @return array
	 */
	protected static function getPermissionsList($data)
	{
		$permissions = [];

		foreach ($data as $key => $val) {
			if (is_array($val)) {
				$permissions = ArrayHelper::merge($permissions, self::getPermissionsList($val));
			} elseif ($key !== 'heading' && $key !== 'items') {
				$permissions[] = $key;
			}
		}

		return $permissions;
	}
}