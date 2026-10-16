<?php

namespace tests\unit;

use PHPUnit\Framework\TestCase;

/**
 * Saving the workspace form must not push anything into the tenant database:
 * the category sync that saveModel() used to run was removed.
 */
class WorkspaceFormNoSyncTest extends TestCase
{
	public function testTheSaveChainHasNoTenantSyncHook()
	{
		$this->assertFalse(
			method_exists(\backend\models\WorkspaceForm::class, 'syncLinkedContentToTenant'),
			'The syncLinkedContentToTenant hook must stay removed from the workspace form save chain.'
		);
	}

	public function testTheModuleFormHasNoCategorySync()
	{
		$this->assertFalse(
			method_exists(\backend\modules\workspace\models\WorkspaceForm::class, 'syncCategoriesToWorkspaceDatabase'),
			'The category wipe-and-replace sync must stay removed.'
		);
	}

	public function testTheModuleFormAddsNoBehaviorOfItsOwn()
	{
		// The subclass exists only for the controllers' type references; any method
		// added back here would run on every workspace save — this test forces that
		// to be a conscious decision.
		$declared = array_filter(
			(new \ReflectionClass(\backend\modules\workspace\models\WorkspaceForm::class))->getMethods(),
			function (\ReflectionMethod $method) {
				return $method->getDeclaringClass()->getName() === \backend\modules\workspace\models\WorkspaceForm::class;
			}
		);

		$this->assertSame([], array_map(function ($method) {
			return $method->getName();
		}, $declared));
	}
}
