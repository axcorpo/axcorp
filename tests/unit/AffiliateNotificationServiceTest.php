<?php

namespace tests\unit;

use common\models\Affiliate;
use common\models\AffiliateCommission;
use common\models\AffiliatePayout;
use common\models\Invoice;
use common\models\Template;
use common\services\AffiliateNotificationService;
use common\services\AffiliatePayoutService;
use tests\DatabaseTestCase;
use tests\support\FakeSettings;
use Yii;
use yii\helpers\FileHelper;

/**
 * The payout e-mails: AffiliatePayoutService's EVENT_AFTER_PAID / EVENT_INVOICE_REJECTED
 * send the 109 / 110 database templates to the affiliate, with the payout shortcodes.
 */
class AffiliateNotificationServiceTest extends DatabaseTestCase
{
	/**
	 * @var string
	 */
	private $mailPath;

	/**
	 * @var Affiliate
	 */
	private $affiliate;

	protected function setUp(): void
	{
		parent::setUp();
		$this->mailPath = sys_get_temp_dir() . '/affiliate-mail-test-' . bin2hex(random_bytes(4));
		Yii::$app->set('settings', new FakeSettings());
		Yii::$app->set('mailer', [
			'class' => 'yii\symfonymailer\Mailer',
			'useFileTransport' => true,
			'fileTransportPath' => $this->mailPath,
			'messageConfig' => ['from' => 'noreply@example.test'],
		]);
		AffiliateNotificationService::attachEventHandlers();

		$userId = $this->insertRow('user', [
			'email' => 'partner@example.test',
			'first_name' => 'Maria',
			'last_name' => 'Ionescu',
			'status' => 1,
			'deleted' => 0,
		]);
		$id = $this->insertRow('affiliate', [
			'user_id' => $userId,
			'code' => 'MAIL0001',
			'commission_percentage' => 10,
			'status' => 1,
			'deleted' => 0,
		]);
		$this->affiliate = Affiliate::findOne(['id' => $id]);

		foreach ([Template::EMAIL_VARIANT_AFFILIATE_PAYOUT_PAID => 'Paid {{AMOUNT}} {{CURRENCY}}', Template::EMAIL_VARIANT_AFFILIATE_INVOICE_REJECTED => 'Rejected {{AMOUNT}} {{CURRENCY}}'] as $variant => $subject) {
			$templateId = $this->insertRow('template', [
				'type' => Template::TYPE_EMAIL,
				'variant' => $variant,
				'default' => 1,
				'status' => 1,
				'deleted' => 0,
			]);
			$this->insertRow('template_translation', [
				'template_id' => $templateId,
				'language_id' => 'en-US',
				'name' => "Variant {$variant}",
				'subject' => $subject,
				'content' => '<p>Hi {{FIRST_NAME}} {{LAST_NAME}}: {{AMOUNT}} {{CURRENCY}}. {{REASON}}</p>',
				'deleted' => 0,
			]);
		}
	}

	protected function tearDown(): void
	{
		FileHelper::removeDirectory($this->mailPath);
		Yii::$app->clear('mailer');
		Yii::$app->clear('settings');
		parent::tearDown();
	}

	/**
	 * @return AffiliatePayout an OPEN payout of 25.50 RON with an accepted invoice
	 */
	private function payout($invoiceStatus = AffiliatePayout::INVOICE_STATUS_ACCEPTED)
	{
		$id = $this->insertRow('affiliate_payout', [
			'affiliate_id' => $this->affiliate->getAttribute('id'),
			'amount' => '25.50',
			'currency' => 'RON',
			'status' => AffiliatePayout::STATUS_INVOICE_RECEIVED,
			'invoice_number' => 'F-7',
			'invoice_status' => $invoiceStatus,
			'deleted' => 0,
		]);
		$this->insertRow('affiliate_commission', [
			'affiliate_id' => $this->affiliate->getAttribute('id'),
			'affiliate_payout_id' => $id,
			'invoice_id' => $this->insertRow('invoice', ['type' => Invoice::TYPE_INVOICE, 'status' => Invoice::STATUS_PAID, 'deleted' => Invoice::NO]),
			'currency' => 'RON',
			'amount' => '25.50',
			'status' => AffiliateCommission::STATUS_PAYABLE,
		]);
		return AffiliatePayout::findOne(['id' => $id]);
	}

	/**
	 * @return string[] the raw .eml files written
	 */
	private function mails()
	{
		return array_map('file_get_contents', glob($this->mailPath . '/*.eml') ?: []);
	}

	public function testMarkingAPayoutPaidEmailsTheAffiliate()
	{
		(new AffiliatePayoutService())->markPaid($this->payout());

		$mails = $this->mails();
		$this->assertCount(1, $mails);
		$this->assertStringContainsString('partner@example.test', $mails[0]);
		$this->assertStringContainsString('Paid 25.50 RON', $mails[0]);
		$this->assertStringContainsString('Hi Maria Ionescu: 25.50 RON', quoted_printable_decode($mails[0]));
	}

	public function testRejectingAnInvoiceEmailsTheReason()
	{
		(new AffiliatePayoutService())->rejectInvoice($this->payout(AffiliatePayout::INVOICE_STATUS_PENDING), 'Wrong <b>VAT</b> code');

		$mails = $this->mails();
		$this->assertCount(1, $mails);
		$this->assertStringContainsString('Rejected 25.50 RON', $mails[0]);
		$body = quoted_printable_decode($mails[0]);
		$this->assertStringContainsString('Wrong &lt;b&gt;VAT&lt;/b&gt; code', $body, 'the reason is HTML-encoded');
	}

	public function testAMissingTemplateDoesNotBreakThePayout()
	{
		Yii::$app->db->createCommand()->delete('template')->execute();

		$payout = (new AffiliatePayoutService())->markPaid($this->payout());

		$this->assertEquals(AffiliatePayout::STATUS_PAID, (int) $payout->status);
		$this->assertCount(0, $this->mails());
		$this->assertFalse((new AffiliateNotificationService())->sendPayoutPaid($payout));
	}
}
