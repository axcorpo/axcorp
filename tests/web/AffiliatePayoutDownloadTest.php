<?php

namespace tests\web;

use common\models\AffiliatePayout;
use common\models\User;
use common\services\AffiliatePayoutService;
use tests\WebTestCase;
use Yii;
use yii\helpers\FileHelper;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

/**
 * The admin download of an affiliate payout's private files (affiliate invoice, payment
 * proofs): only `manageAffiliatePayout` may stream them, only names recorded on that payout
 * resolve, and the payout's state changes are POST-only.
 */
class AffiliatePayoutDownloadTest extends WebTestCase
{
	/**
	 * @var string
	 */
	private $storage;

	/**
	 * @var mixed
	 */
	private $previousPath;

	/**
	 * @var AffiliatePayout
	 */
	private $payout;

	/**
	 * @var string
	 */
	private $tempFile;

	protected function setUp(): void
	{
		parent::setUp();
		Yii::$app->setModule('affiliate-manager', ['class' => \backend\modules\affiliate\Module::class]);

		$this->previousPath = Yii::$app->params['privateUploads.path'] ?? null;
		$this->storage = sys_get_temp_dir() . '/affiliate-download-test-' . bin2hex(random_bytes(4));
		Yii::$app->params['privateUploads.path'] = $this->storage;

		// A signed-in (non-guest) admin; what it may do is set per test.
		$adminId = $this->insertRow('user', ['email' => 'admin@example.test', 'status' => 1, 'deleted' => 0]);
		Yii::$app->user->setIdentity(User::findOne(['id' => $adminId]));

		$affiliateId = $this->insertRow('affiliate', [
			'user_id' => $this->insertRow('user', ['email' => 'aff@example.test', 'status' => 1, 'deleted' => 0]),
			'code' => 'DOWN0001',
			'commission_percentage' => 10,
			'status' => 1,
			'deleted' => 0,
		]);
		$payoutId = $this->insertRow('affiliate_payout', [
			'affiliate_id' => $affiliateId,
			'amount' => 10,
			'currency' => 'RON',
			'status' => AffiliatePayout::STATUS_OPEN,
			'deleted' => 0,
		]);
		$this->payout = AffiliatePayout::findOne(['id' => $payoutId]);

		$this->tempFile = tempnam(sys_get_temp_dir(), 'inv');
		file_put_contents($this->tempFile, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
		(new AffiliatePayoutService())->attachInvoice($this->payout, new UploadedFile([
			'name' => 'invoice.pdf',
			'tempName' => $this->tempFile,
			'type' => 'application/pdf',
			'size' => filesize($this->tempFile),
			'error' => UPLOAD_ERR_OK,
		]), 'F-100', date('Y-m-d'), false);
	}

	protected function tearDown(): void
	{
		Yii::$app->params['privateUploads.path'] = $this->previousPath;
		FileHelper::removeDirectory($this->storage);
		@unlink($this->tempFile);
		parent::tearDown();
	}

	/**
	 * @param string $file
	 * @return mixed
	 */
	private function download($file)
	{
		return $this->runControllerAction('affiliate-manager/payout/download', [
			'id' => $this->payout->uuid,
			'file' => $file,
		]);
	}

	public function testTheInvoiceIsStreamedToAPayoutManager()
	{
		Yii::$app->user->grantedPermissions = ['manageAffiliatePayout'];

		$response = $this->download($this->payout->invoice_file);

		$this->assertInstanceOf(\yii\web\Response::class, $response);
		$this->assertNotNull($response->stream, 'the file is streamed, not linked');
		$this->assertStringContainsString('invoice-F-100.pdf', $response->headers->get('content-disposition'));
		$response->stream = null;
	}

	public function testAnAdminWithoutThePayoutPermissionCannotDownload()
	{
		Yii::$app->user->grantedPermissions = ['viewAffiliate', 'updateAffiliate'];

		$this->expectException(ForbiddenHttpException::class);
		$this->download($this->payout->invoice_file);
	}

	public function testAnAccountWithNoPermissionCannotDownload()
	{
		Yii::$app->user->grantedPermissions = [];

		$this->expectException(ForbiddenHttpException::class);
		$this->download($this->payout->invoice_file);
	}

	public function testOnlyFilesRecordedOnThePayoutResolve()
	{
		Yii::$app->user->grantedPermissions = ['manageAffiliatePayout'];

		foreach (['../../common/config/main-local.php', 'deadbeefdeadbeefdeadbeefdeadbeef.pdf', ''] as $file) {
			try {
				$this->download($file);
				$this->fail("'{$file}' must not resolve");
			} catch (NotFoundHttpException $e) {
				$this->assertTrue(true);
			}
		}
	}

	public function testStateChangesArePostOnly()
	{
		Yii::$app->user->grantedPermissions = ['manageAffiliatePayout'];

		try {
			$this->runControllerAction('affiliate-manager/payout/accept-invoice', ['id' => $this->payout->uuid]);
			$this->fail('a GET must not accept the invoice');
		} catch (ForbiddenHttpException $e) {
			$this->assertTrue(true);
		}
		$this->assertEquals(AffiliatePayout::INVOICE_STATUS_PENDING, (int) AffiliatePayout::findOne(['id' => $this->payout->getAttribute('id')])->invoice_status);
	}
}
