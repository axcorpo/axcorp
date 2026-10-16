<?php

namespace tests\unit;

use common\helpers\UuidHelper;
use common\models\Workspace;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\helpers\FileHelper;

/**
 * Every platform has its own common/config/params-local.php (workspaces/<domain>/), written
 * from install/dir with the master's facebook.* params and loaded by the platform's apps:
 *
 * - the file gets the master's values, quoted so they cannot break out of the PHP string;
 * - every app config loads it, and an install without it still boots;
 * - the backfill keeps a params-local.php already edited for the platform.
 */
class WorkspaceParamsConfigTest extends TestCase
{
	/**
	 * @var string The fake tenant directory.
	 */
	private $dir;

	/**
	 * @var array The params snapshot restored after each test.
	 */
	private $originalParams;

	protected function setUp(): void
	{
		parent::setUp();
		$this->originalParams = Yii::$app->params;
		Yii::$app->params['facebook.appId'] = '1396945108770271';
		Yii::$app->params['facebook.appSecret'] = "se'cr\\et";
		Yii::$app->params['facebook.configId'] = '1649084639967042';
		Yii::$app->params['facebook.graphVersion'] = 'v26.0';
		Yii::$app->params['facebook.loginAppId'] = '1878343596638185';
		Yii::$app->params['facebook.loginAppSecret'] = 'login-secret';

		$this->dir = sys_get_temp_dir() . '/ws-params-' . bin2hex(random_bytes(4));
		foreach (Workspace::TENANT_APPS as $app) {
			FileHelper::createDirectory("{$this->dir}/{$app}/config");
			file_put_contents("{$this->dir}/{$app}/config/main.php", "<?php // old {$app} config\n");
		}
		FileHelper::createDirectory("{$this->dir}/common/config");
	}

	protected function tearDown(): void
	{
		Yii::$app->params = $this->originalParams;
		FileHelper::removeDirectory($this->dir);
		parent::tearDown();
	}

	/**
	 * @return Workspace
	 */
	private function workspace()
	{
		$workspace = new FakeDirectoryWorkspace();
		$workspace->dir = $this->dir;
		$workspace->id = UuidHelper::uuid7Bytes();
		$workspace->code = 'econstructii';
		$workspace->url = 'econstructii';

		return $workspace;
	}

	public function testTheParamsLocalGetsTheMastersFacebookValues()
	{
		$this->assertTrue($this->workspace()->writeTenantParamsConfig());

		$params = require "{$this->dir}/common/config/params-local.php";
		$this->assertSame('1396945108770271', $params['facebook.appId']);
		$this->assertSame("se'cr\\et", $params['facebook.appSecret']);
		$this->assertSame('1649084639967042', $params['facebook.configId']);
		$this->assertSame('v26.0', $params['facebook.graphVersion']);
		$this->assertSame('1878343596638185', $params['facebook.loginAppId']);
		$this->assertSame('login-secret', $params['facebook.loginAppSecret']);
	}

	public function testEveryAppConfigLoadsThePlatformsParamsLocal()
	{
		$this->workspace()->writeTenantParamsConfig();

		foreach (Workspace::TENANT_APPS as $app) {
			$config = file_get_contents("{$this->dir}/{$app}/config/main.php");
			$this->assertStringContainsString("require __DIR__ . '/../../common/config/params-local.php'", $config, $app);
			$this->assertStringContainsString("'app-{$app}-", $config, $app);
			$this->assertStringNotContainsString('{{', $config, $app);
		}
	}

	public function testAnInstallWithoutParamsLocalStillLoadsItsParams()
	{
		foreach (Workspace::TENANT_APPS as $app) {
			$template = file_get_contents(Yii::getAlias("@workspace/install/dir/{$app}/config/main.php"));
			$this->assertStringContainsString("is_file(__DIR__ . '/../../common/config/params-local.php') ?", $template, $app);
		}
	}

	public function testTheBackfillKeepsAnEditedParamsLocal()
	{
		file_put_contents("{$this->dir}/common/config/params-local.php", "<?php return ['facebook.appId' => 'edited'];\n");

		$this->assertTrue($this->workspace()->writeTenantParamsConfig());

		$params = require "{$this->dir}/common/config/params-local.php";
		$this->assertSame('edited', $params['facebook.appId']);
	}

	public function testNoTenantDirectoryIsReported()
	{
		$workspace = $this->workspace();
		$workspace->dir = $this->dir . '/missing';

		$this->assertFalse($workspace->writeTenantParamsConfig());
	}
}

/**
 * A workspace whose directory is a temp dir, without a tenant database.
 */
class FakeDirectoryWorkspace extends Workspace
{
	/**
	 * @var string
	 */
	public $dir;

	public function getDirectoryPath()
	{
		return $this->dir;
	}

	public function getWorkspaceDbName()
	{
		return 'ws_econstructii';
	}
}
