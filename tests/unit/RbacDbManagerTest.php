<?php

namespace tests\unit;

use common\helpers\UuidHelper;
use common\rbac\DbManager;
use tests\DatabaseTestCase;
use Yii;
use yii\db\Query;

/**
 * RBAC with `auth_assignment.user_id` stored as BINARY(16): the application keeps
 * passing the identity's UUID string, the manager converts at the boundary.
 */
class RbacDbManagerTest extends DatabaseTestCase
{
	/**
	 * @var DbManager
	 */
	private $authManager;

	/**
	 * @var string canonical UUID string of the fixture user
	 */
	private $userUuid;

	protected function setUp(): void
	{
		parent::setUp();

		$this->authManager = Yii::$app->authManager;
		$this->assertInstanceOf(DbManager::class, $this->authManager);
		$this->authManager->invalidateCache();

		$this->insertRow('auth_item', ['name' => 'tester', 'type' => 1, 'created_at' => time(), 'updated_at' => time()]);
		$this->userUuid = UuidHelper::fromBytes($this->insertRow('user', ['status' => 1, 'deleted' => 0]));
	}

	public function testAssignmentIsStoredAsBytesAndReadBackAsString()
	{
		$role = $this->authManager->getRole('tester');
		$assignment = $this->authManager->assign($role, $this->userUuid);

		$this->assertSame($this->userUuid, $assignment->userId);

		$stored = (new Query())->select('user_id')->from('auth_assignment')->where(['item_name' => 'tester'])->scalar();
		$this->assertSame(UuidHelper::toBytes($this->userUuid), $stored, 'the column holds the raw 16 bytes');

		$assignments = $this->authManager->getAssignments($this->userUuid);
		$this->assertArrayHasKey('tester', $assignments);
		$this->assertSame($this->userUuid, $assignments['tester']->userId);

		$this->assertSame($this->userUuid, $this->authManager->getAssignment('tester', $this->userUuid)->userId);
		$this->assertSame([$this->userUuid], $this->authManager->getUserIdsByRole('tester'));
		$this->assertArrayHasKey('tester', $this->authManager->getRolesByUser($this->userUuid));
	}

	public function testCheckAccessAndRevokeWithTheUuidString()
	{
		$role = $this->authManager->getRole('tester');
		$this->authManager->assign($role, $this->userUuid);

		$this->assertTrue($this->authManager->checkAccess($this->userUuid, 'tester'));
		$this->assertFalse($this->authManager->checkAccess(UuidHelper::uuid7(), 'tester'));

		$this->assertTrue($this->authManager->revoke($role, $this->userUuid));
		$this->assertSame([], $this->authManager->getAssignments($this->userUuid));
	}

	public function testRevokeAllWithTheUuidString()
	{
		$this->authManager->assign($this->authManager->getRole('tester'), $this->userUuid);

		$this->assertTrue($this->authManager->revokeAll($this->userUuid));
		$this->assertSame(0, (int) (new Query())->from('auth_assignment')->where(['item_name' => 'tester'])->count());
	}
}
