<?php

namespace tests\unit;

use common\models\User;
use tests\DatabaseTestCase;
use Yii;
use yii\db\Query;

/**
 * A password changed on master must reach the workspace copies of the user, which the
 * workspaces sign in against; otherwise the old password keeps working there.
 */
class UserCredentialSyncTest extends DatabaseTestCase
{
	public function testSyncCredentialsCopiesThePasswordAndKeyToTheWorkspaceLink()
	{
		$userId = $this->insertRow('user', [
			'auth_key' => 'old-key',
			'password_hash' => 'old-hash',
			'status' => User::STATUS_ACTIVE,
			'deleted' => 0,
		]);
		// A tenant whose database does not exist: the link row is still updated, and the
		// unreachable database is reported rather than thrown.
		$workspaceId = $this->insertRow('workspace', ['code' => 'nosuchtenant', 'status' => 1]);
		$this->insertRow('workspace_has_user', [
			'workspace_id' => $workspaceId,
			'user_id' => $userId,
			'auth_key' => 'old-key',
			'password_hash' => 'old-hash',
		]);

		$user = User::findOne(['id' => $userId]);
		$user->setPassword('Correct-Horse-9');
		$user->generateAuthKey();
		$user->save(false, ['password_hash', 'auth_key']);

		$this->assertFalse($user->syncCredentials());

		$link = (new Query())
			->select(['auth_key', 'password_hash'])
			->from('workspace_has_user')
			->where(['workspace_id' => $workspaceId, 'user_id' => $userId])
			->one();
		$this->assertSame($user->auth_key, $link['auth_key']);
		$this->assertSame($user->password_hash, $link['password_hash']);
		$this->assertTrue(Yii::$app->security->validatePassword('Correct-Horse-9', $link['password_hash']));
	}

	public function testSyncAuthKeyLeavesThePasswordAlone()
	{
		$userId = $this->insertRow('user', [
			'auth_key' => 'old-key',
			'password_hash' => 'master-hash',
			'status' => User::STATUS_ACTIVE,
			'deleted' => 0,
		]);
		$workspaceId = $this->insertRow('workspace', ['code' => 'nosuchtenant', 'status' => 1]);
		$this->insertRow('workspace_has_user', [
			'workspace_id' => $workspaceId,
			'user_id' => $userId,
			'auth_key' => 'old-key',
			'password_hash' => 'tenant-hash',
		]);

		$user = User::findOne(['id' => $userId]);
		$user->generateAuthKey();
		$user->save(false, ['auth_key']);
		$user->syncAuthKey();

		$link = (new Query())
			->select(['auth_key', 'password_hash'])
			->from('workspace_has_user')
			->where(['workspace_id' => $workspaceId, 'user_id' => $userId])
			->one();
		$this->assertSame($user->auth_key, $link['auth_key']);
		$this->assertSame('tenant-hash', $link['password_hash']);
	}
}
