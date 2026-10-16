<?php

namespace tests\unit;

use common\helpers\PrivateFileHelper;
use common\models\Affiliate;
use common\models\AffiliateCommission;
use common\models\AffiliatePayout;
use common\models\Invoice;
use common\services\AffiliatePayoutService;
use tests\DatabaseTestCase;
use Yii;
use yii\base\UserException;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

/**
 * The payout lifecycle: grouping PAYABLE commissions per currency, the affiliate's
 * invoice (accept/reject), payment proofs, settlement and cancellation.
 */
class AffiliatePayoutServiceTest extends DatabaseTestCase
{
	/**
	 * @var Affiliate
	 */
	private $affiliate;

	/**
	 * @var string
	 */
	private $storage;

	/**
	 * @var string[]
	 */
	private $tempFiles = [];

	/**
	 * @var mixed
	 */
	private $previousPath;

	protected function setUp(): void
	{
		parent::setUp();
		$this->previousPath = Yii::$app->params['privateUploads.path'] ?? null;
		$this->storage = sys_get_temp_dir() . '/affiliate-payout-test-' . bin2hex(random_bytes(4));
		Yii::$app->params['privateUploads.path'] = $this->storage;

		$id = $this->insertRow('affiliate', [
			'user_id' => $this->insertRow('user', ['status' => 1, 'deleted' => 0]),
			'code' => 'PAY001',
			'commission_percentage' => 10,
			'status' => 1,
			'deleted' => 0,
		]);
		$this->affiliate = Affiliate::findOne(['id' => $id]);
	}

	protected function tearDown(): void
	{
		Yii::$app->params['privateUploads.path'] = $this->previousPath;
		FileHelper::removeDirectory($this->storage);
		foreach ($this->tempFiles as $path) {
			@unlink($path);
		}
		parent::tearDown();
	}

	/**
	 * @param string $amount
	 * @param int $status
	 * @param string $currency
	 * @return string raw id
	 */
	private function commission($amount, $status = AffiliateCommission::STATUS_PAYABLE, $currency = 'RON')
	{
		return $this->insertRow('affiliate_commission', [
			'affiliate_id' => $this->affiliate->getAttribute('id'),
			'invoice_id' => $this->insertRow('invoice', ['type' => Invoice::TYPE_INVOICE, 'status' => Invoice::STATUS_PAID, 'deleted' => Invoice::NO]),
			'base_amount' => $amount * 10,
			'currency' => $currency,
			'percentage' => 10,
			'amount' => $amount,
			'status' => $status,
		]);
	}

	/**
	 * @param string $id
	 * @return AffiliateCommission
	 */
	private function reload($id)
	{
		return AffiliateCommission::findOne(['id' => $id]);
	}

	/**
	 * @param string $name
	 * @return UploadedFile
	 */
	private function pdf($name = 'factura.pdf')
	{
		$path = tempnam(sys_get_temp_dir(), 'payout');
		file_put_contents($path, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
		$this->tempFiles[] = $path;

		return new UploadedFile([
			'name' => $name,
			'tempName' => $path,
			'type' => 'application/pdf',
			'size' => filesize($path),
			'error' => UPLOAD_ERR_OK,
		]);
	}

	public function testCreatingAPayoutGroupsThePayableCommissionsOfOneCurrency()
	{
		$a = $this->commission('10.00');
		$b = $this->commission('5.50');
		$storno = $this->commission('-2.00');
		$pending = $this->commission('7.00', AffiliateCommission::STATUS_PENDING);
		$euro = $this->commission('3.00', AffiliateCommission::STATUS_PAYABLE, 'EUR');

		$service = new AffiliatePayoutService();
		$summary = $service->getPayableSummary($this->affiliate);
		$this->assertEquals(13.5, $summary['RON']['amount']);
		$this->assertEquals(3, $summary['RON']['count']);
		$this->assertEquals(3.0, $summary['EUR']['amount']);

		$payout = $service->createPayout($this->affiliate, 'RON');

		$this->assertEquals(AffiliatePayout::STATUS_OPEN, (int) $payout->status);
		$this->assertEquals('13.50', $payout->amount);
		$this->assertEquals('RON', $payout->currency);
		foreach ([$a, $b, $storno] as $id) {
			$this->assertEquals($payout->getAttribute('id'), $this->reload($id)->affiliate_payout_id);
		}
		$this->assertNull($this->reload($pending)->affiliate_payout_id);
		$this->assertNull($this->reload($euro)->affiliate_payout_id);

		// Nothing left in RON for a second payout.
		$this->expectException(UserException::class);
		$service->createPayout($this->affiliate, 'RON');
	}

	public function testTheMinimumPayoutIsRespected()
	{
		$this->commission('5.00');
		$settings = new \tests\support\FakeSettings();
		$settings->set('minimumPayout', 50, 'affiliate');
		Yii::$app->set('settings', $settings);
		try {
			(new AffiliatePayoutService())->createPayout($this->affiliate, 'RON');
			$this->fail('a total below minimumPayout must be refused');
		} catch (UserException $e) {
			$this->assertEquals(0, AffiliatePayout::find()->count());
		} finally {
			Yii::$app->clear('settings');
		}
	}

	public function testFullLifecycleInvoiceRejectedThenAcceptedThenPaid()
	{
		$a = $this->commission('10.00');
		$service = new AffiliatePayoutService();
		$payout = $service->createPayout($this->affiliate, 'RON');

		// Paying before an accepted invoice is refused.
		try {
			$service->markPaid($payout);
			$this->fail('paying without an accepted invoice must be refused');
		} catch (UserException $e) {
		}

		$service->attachInvoice($payout, $this->pdf(), 'AF-1', '2026-10-01', false);
		$this->assertEquals(AffiliatePayout::STATUS_INVOICE_RECEIVED, (int) $payout->status);
		$this->assertEquals(AffiliatePayout::INVOICE_STATUS_PENDING, (int) $payout->invoice_status);
		$first = $payout->invoice_file;
		$this->assertNotNull($payout->getFilePath($first));

		$service->rejectInvoice($payout, 'Wrong VAT code');
		$this->assertEquals(AffiliatePayout::STATUS_OPEN, (int) $payout->status);
		$this->assertEquals(AffiliatePayout::INVOICE_STATUS_REJECTED, (int) $payout->invoice_status);
		$this->assertEquals('Wrong VAT code', $payout->invoice_rejection_note);

		$service->attachInvoice($payout, $this->pdf(), 'AF-2', '2026-10-02', false);
		// The replaced invoice file is removed from the storage.
		$this->assertNull(PrivateFileHelper::resolve($payout->getFileSubdir(), $first));
		$this->assertNull($payout->invoice_rejection_note);

		$service->acceptInvoice($payout);
		$this->assertEquals(AffiliatePayout::INVOICE_STATUS_ACCEPTED, (int) $payout->invoice_status);

		$proofs = $service->addProofFiles($payout, [$this->pdf('extras.pdf')], false);
		$this->assertCount(1, $proofs);
		$this->assertEquals($proofs, AffiliatePayout::findOne(['id' => $payout->getAttribute('id')])->proofFileList);
		$this->assertNotNull($payout->getFilePath($proofs[0]));

		$service->markPaid($payout, '2026-10-05 12:00:00');
		$payout = AffiliatePayout::findOne(['id' => $payout->getAttribute('id')]);
		$this->assertEquals(AffiliatePayout::STATUS_PAID, (int) $payout->status);
		$this->assertEquals('2026-10-05 12:00:00', $payout->paid_at);
		$this->assertEquals(AffiliateCommission::STATUS_PAID, (int) $this->reload($a)->status);

		// A paid payout is closed.
		$this->expectException(UserException::class);
		$service->cancel($payout);
	}

	public function testCancellingReturnsTheCommissionsToThePool()
	{
		$a = $this->commission('10.00');
		$service = new AffiliatePayoutService();
		$payout = $service->createPayout($this->affiliate, 'RON');

		$service->cancel($payout, 'Created by mistake');

		$this->assertEquals(AffiliatePayout::STATUS_CANCELLED, (int) $payout->status);
		$commission = $this->reload($a);
		$this->assertNull($commission->affiliate_payout_id);
		$this->assertEquals(AffiliateCommission::STATUS_PAYABLE, (int) $commission->status);
		// And they can be paid out again.
		$this->assertEquals('10.00', $service->createPayout($this->affiliate, 'RON')->amount);
	}

	public function testAnInvoiceWithABadExtensionIsRefused()
	{
		$this->commission('10.00');
		$service = new AffiliatePayoutService();
		$payout = $service->createPayout($this->affiliate, 'RON');

		$path = tempnam(sys_get_temp_dir(), 'payout');
		file_put_contents($path, '<?php echo 1;');
		$this->tempFiles[] = $path;
		$file = new UploadedFile(['name' => 'invoice.php', 'tempName' => $path, 'type' => 'application/pdf', 'size' => 13, 'error' => UPLOAD_ERR_OK]);

		try {
			$service->attachInvoice($payout, $file, 'AF-1', '2026-10-01', false);
			$this->fail('a PHP file must not be accepted as an invoice');
		} catch (UserException $e) {
		}
		$this->assertNull($payout->invoice_file);
		$this->assertEquals(AffiliatePayout::STATUS_OPEN, (int) $payout->status);
	}
}
