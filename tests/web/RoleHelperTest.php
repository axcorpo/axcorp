<?php

namespace tests\web;

use common\helpers\RoleHelper;
use common\models\User;
use tests\WebTestCase;
use Yii;

/**
 * The privilege-escalation guard behind the role and account-management screens.
 *
 * Roles are created at runtime as bags of permissions, so there is no rank to compare.
 * Every rule here reduces to one question — does this grant authority the caller does
 * not already hold — and these cases pin that down, including the ones the assessment
 * exploited: a limited user-manager promoting itself, and the same escalation taken the
 * long way round by editing a role's permissions first.
 */
class RoleHelperTest extends WebTestCase
{
	/**
	 * @var User
	 */
	private $limitedManager;

	/**
	 * @var User
	 */
	private $administrator;

	protected function setUp(): void
	{
		parent::setUp();

		$auth = Yii::$app->authManager;

		foreach (['viewUser', 'updateUser', 'manageBilling', 'clearCacheSetting'] as $name) {
			$auth->add($auth->createPermission($name));
		}

		// Can manage accounts, and nothing else.
		$userManager = $auth->createRole('userManager');
		$auth->add($userManager);
		$auth->addChild($userManager, $auth->getPermission('viewUser'));
		$auth->addChild($userManager, $auth->getPermission('updateUser'));

		// Everything the userManager has, plus authority it does not.
		$administrator = $auth->createRole('administrator');
		$auth->add($administrator);
		foreach (['viewUser', 'updateUser', 'manageBilling', 'clearCacheSetting'] as $name) {
			$auth->addChild($administrator, $auth->getPermission($name));
		}

		// A role strictly weaker than userManager.
		$viewer = $auth->createRole('viewer');
		$auth->add($viewer);
		$auth->addChild($viewer, $auth->getPermission('viewUser'));

		$auth->add($auth->createRole(RoleHelper::SUPER_ADMIN));

		$this->limitedManager = $this->insertUserModel('manager@example.test');
		$this->administrator = $this->insertUserModel('admin@example.test');

		$auth->assign($auth->getRole('userManager'), $this->limitedManager->getId());
		$auth->assign($auth->getRole('administrator'), $this->administrator->getId());
	}

	/**
	 * @param string $email
	 * @return User
	 */
	private function insertUserModel($email)
	{
		$id = $this->insertRow('user', [
			'email' => $email,
			'first_name' => 'Test',
			'last_name' => 'User',
			'status' => User::STATUS_ACTIVE,
			'deleted' => User::NO,
		]);

		return User::findOne(['id' => $id]);
	}

	/**
	 * Runs the request as the given account.
	 *
	 * @param User $user
	 */
	private function actingAs(User $user)
	{
		Yii::$app->user->setIdentity($user);
	}

	public function testARoleWithinReachMayBeGranted()
	{
		$this->actingAs($this->limitedManager);

		$this->assertTrue(RoleHelper::canGrantRole('viewer'));
	}

	public function testARoleCarryingUnheldPermissionsMayNotBeGranted()
	{
		$this->actingAs($this->limitedManager);

		$this->assertFalse(
			RoleHelper::canGrantRole('administrator'),
			'administrator carries manageBilling, which a userManager does not hold'
		);
	}

	public function testSuperAdminIsNeverGrantableByAnyoneElse()
	{
		$this->actingAs($this->administrator);

		$this->assertFalse(RoleHelper::canGrantRole(RoleHelper::SUPER_ADMIN));
	}

	public function testClearingAnAssignmentGrantsNothingAndIsAllowed()
	{
		$this->actingAs($this->limitedManager);

		$this->assertTrue(RoleHelper::canGrantRole(''));
		$this->assertTrue(RoleHelper::canGrantRole(null));
	}

	public function testAnAccountHoldingMoreThanTheCallerCannotBeManaged()
	{
		$this->actingAs($this->limitedManager);

		$this->assertFalse(RoleHelper::canManageUser($this->administrator->getId()));
	}

	public function testAnAccountHoldingLessThanTheCallerCanBeManaged()
	{
		$this->actingAs($this->administrator);

		$this->assertTrue(RoleHelper::canManageUser($this->limitedManager->getId()));
	}

	public function testChangingYourOwnRoleIsAlwaysRefused()
	{
		$this->actingAs($this->administrator);

		$this->assertTrue(
			RoleHelper::canManageUser($this->administrator->getId()),
			'acting on your own account is otherwise fine'
		);
		$this->assertFalse(
			RoleHelper::canChangeRoleOf($this->administrator->getId()),
			'but not to change your own privilege level'
		);
	}

	public function testARoleCannotBeDefinedWithPermissionsTheCallerLacks()
	{
		$this->actingAs($this->limitedManager);

		$this->assertFalse(
			RoleHelper::canDefineRole('viewer', ['viewUser', 'manageBilling']),
			'writing an unheld permission into a role is the same escalation, one step longer'
		);
	}

	public function testARoleMayBeDefinedWithinTheCallersOwnPermissions()
	{
		$this->actingAs($this->limitedManager);

		$this->assertTrue(RoleHelper::canDefineRole('viewer', ['viewUser', 'updateUser']));
	}

	public function testARoleThatOutranksTheCallerCannotBeEditedAtAll()
	{
		$this->actingAs($this->limitedManager);

		$this->assertFalse(
			RoleHelper::canDefineRole('administrator', ['viewUser']),
			'stripping permissions off a more privileged role is still editing it'
		);
	}

	public function testANewRoleHasNothingToOutrank()
	{
		$this->actingAs($this->limitedManager);

		$this->assertTrue(RoleHelper::canDefineRole(null, ['viewUser']));
	}

	public function testSuperAdminIsExemptFromEveryCheck()
	{
		$superAdmin = $this->insertUserModel('root@example.test');
		Yii::$app->authManager->assign(
			Yii::$app->authManager->getRole(RoleHelper::SUPER_ADMIN),
			$superAdmin->getId()
		);
		$this->actingAs($superAdmin);

		// superAdmin holds no permission children in this fixture, so without the
		// short-circuit the subset test would lock out the one account that must be able
		// to repair the tree.
		$this->assertTrue(RoleHelper::canGrantRole('administrator'));
		$this->assertTrue(RoleHelper::canManageUser($this->administrator->getId()));
		$this->assertTrue(RoleHelper::canDefineRole('administrator', ['manageBilling']));
	}

	public function testTheRolePickerOffersOnlyWhatTheCallerCanGrant()
	{
		$this->actingAs($this->limitedManager);

		$names = array_map(function ($role) {
			return $role->name;
		}, RoleHelper::grantableRoles());

		$this->assertContains('viewer', $names);
		$this->assertNotContains('administrator', $names);
		$this->assertNotContains(RoleHelper::SUPER_ADMIN, $names, 'findAllRoles() already excludes it');
	}
}
