<?php

namespace tests\unit;

use common\models\AffiliateCommission;
use common\models\Invoice;
use common\models\Subscriber;
use common\models\User;
use common\services\AffiliateCommissionService;
use tests\DatabaseTestCase;
use Yii;
use yii\db\Query;

/**
 * The commission engine (`affiliate/compute-commissions`): which master invoices earn,
 * how much, and how the commissions move through hold, storno and voiding.
 */
class AffiliateCommissionServiceTest extends DatabaseTestCase
{
	/**
	 * @var string raw bytes
	 */
	private $affiliateId;
	/**
	 * @var string raw bytes — the referred master subscriber (subscriber.id)
	 */
	private $subscriberId;

	protected function setUp(): void
	{
		parent::setUp();
		$this->affiliateId = $this->insertRow('affiliate', [
			'user_id' => $this->user(),
			'code' => 'AFF001',
			'commission_percentage' => '10.00',
			'commission_months' => null,
			'status' => 1,
			'deleted' => 0,
		]);
		$this->subscriberId = $this->insertRow('subscriber', [
			'user_id' => $this->user(),
			'affiliate_id' => $this->affiliateId,
			'status' => Subscriber::STATUS_ACTIVE,
			'deleted' => Subscriber::NO,
			'created_at' => '2026-01-10 12:00:00',
		]);
	}

	/**
	 * @return string raw user id
	 */
	private function user()
	{
		return $this->insertRow('user', ['status' => User::STATUS_ACTIVE, 'deleted' => User::NO]);
	}

	/**
	 * @param string $now
	 * @return AffiliateCommissionService
	 */
	private function service($now = '2026-06-01 00:00:00')
	{
		return new AffiliateCommissionService(['now' => $now, 'holdDays' => 30]);
	}

	/**
	 * A master invoice of the referred subscriber: paid fiscal invoice of 119 RON, VAT 19%.
	 *
	 * @param array $attributes
	 * @return string raw id
	 */
	private function invoice(array $attributes = [])
	{
		return $this->insertRow('invoice', $attributes + [
			'subscriber_id' => $this->subscriberId,
			'type' => Invoice::TYPE_INVOICE,
			'status' => Invoice::STATUS_PAID,
			'amount' => '119.00',
			'vat' => '19.0000',
			'currency' => 'RON',
			'issued_at' => '2026-03-01 10:00:00',
			'paid_at' => '2026-03-01 10:00:00',
			'deleted' => 0,
		]);
	}

	/**
	 * @param string $invoiceId
	 * @return AffiliateCommission|null
	 */
	private function commissionOf($invoiceId)
	{
		return AffiliateCommission::find()->where(['invoice_id' => $invoiceId])->one();
	}

	public function testAPaidInvoiceEarnsACommissionOnTheNetAmount()
	{
		$invoiceId = $this->invoice();

		$result = $this->service()->run();

		$this->assertEquals(1, $result['created']);
		$commission = $this->commissionOf($invoiceId);
		$this->assertNotNull($commission);
		// 119 gross at 19% VAT = 100 net; 10% of that.
		$this->assertEquals('100.00', $commission->base_amount);
		$this->assertEquals('10.00', $commission->percentage);
		$this->assertEquals('10.00', $commission->amount);
		$this->assertEquals('RON', $commission->currency);
		$this->assertEquals($this->affiliateId, $commission->affiliate_id);
		$this->assertEquals($this->subscriberId, $commission->subscriber_id);
		$this->assertEquals('2026-03-31 10:00:00', $commission->payable_at);
		$this->assertEquals('2026-03-01 10:00:00', $commission->earned_at);
		// The hold period ended before "now" (2026-06-01): released in the same run.
		$this->assertEquals(AffiliateCommission::STATUS_PAYABLE, (int) $commission->status);
	}

	public function testNetAmountRounding()
	{
		$this->assertSame(84.03, AffiliateCommissionService::netAmount('100.00', '19'));
		$this->assertSame(100.0, AffiliateCommissionService::netAmount('100.00', '0'));
		$this->assertSame(-0.84, AffiliateCommissionService::commissionAmount(-8.4, '10'));
	}

	public function testTheAffiliatePercentageIsCopiedAtComputationTime()
	{
		$invoiceId = $this->invoice();
		$this->service()->run();

		Yii::$app->db->createCommand()->update('affiliate', ['commission_percentage' => 50], ['id' => $this->affiliateId])->execute();
		$this->service()->run();

		$this->assertEquals('10.00', $this->commissionOf($invoiceId)->percentage);
	}

	public function testUnpaidInvoicesAndProformasAreIgnored()
	{
		$unpaid = $this->invoice(['status' => Invoice::STATUS_UNPAID, 'paid_at' => null]);
		$proforma = $this->invoice(['type' => Invoice::TYPE_PROFORMA, 'status' => Invoice::STATUS_PAID]);

		$this->assertEquals(0, $this->service()->run()['created']);
		$this->assertNull($this->commissionOf($unpaid));
		$this->assertNull($this->commissionOf($proforma));
	}

	public function testInvoicesOfUnattributedSubscribersOrWithoutSubscriberAreIgnored()
	{
		$this->invoice(['subscriber_id' => $this->strangerId()]);
		$this->invoice(['subscriber_id' => null]);

		$this->assertEquals(0, $this->service()->run()['created']);
	}

	/**
	 * @return string raw id of a subscriber nobody referred
	 */
	private function strangerId()
	{
		return $this->insertRow('subscriber', [
			'user_id' => $this->user(),
			'affiliate_id' => null,
			'status' => Subscriber::STATUS_ACTIVE,
			'deleted' => Subscriber::NO,
		]);
	}

	public function testAnInvoiceNoLongerPaidCancelsItsUnpaidCommission()
	{
		$invoiceId = $this->invoice();
		$this->service('2026-03-02 00:00:00')->run();

		Yii::$app->db->createCommand()->update('invoice', ['status' => Invoice::STATUS_UNPAID], ['id' => $invoiceId])->execute();
		$result = $this->service('2026-03-03 00:00:00')->run();

		$this->assertEquals(1, $result['cancelled']);
		$this->assertEquals(AffiliateCommission::STATUS_CANCELLED, (int) $this->commissionOf($invoiceId)->status);
	}

	public function testAnInactiveAffiliateEarnsNothing()
	{
		Yii::$app->db->createCommand()->update('affiliate', ['status' => 0], ['id' => $this->affiliateId])->execute();
		$this->invoice();

		$this->assertEquals(0, $this->service()->run()['created']);
	}

	public function testInvoicesOutsideTheCommissionMonthsAreIgnored()
	{
		Yii::$app->db->createCommand()->update('affiliate', ['commission_months' => 3], ['id' => $this->affiliateId])->execute();
		// Signup 2026-01-10: the window closes on 2026-04-10.
		$inside = $this->invoice(['paid_at' => '2026-04-09 23:00:00']);
		$outside = $this->invoice(['paid_at' => '2026-04-11 09:00:00']);

		$this->service()->run();

		$this->assertNotNull($this->commissionOf($inside));
		$this->assertNull($this->commissionOf($outside));
	}

	public function testARectifyInvoiceYieldsANegativeCommission()
	{
		$original = $this->invoice();
		$this->service('2026-03-02 00:00:00')->run();

		// The affiliate's percentage changed meanwhile: the storno offsets at the original's.
		Yii::$app->db->createCommand()->update('affiliate', ['commission_percentage' => 20], ['id' => $this->affiliateId])->execute();
		$rectify = $this->invoice([
			'type' => Invoice::TYPE_RECTIFY,
			'parent_id' => $original,
			'status' => Invoice::STATUS_UNPAID,
			'paid_at' => null,
			'issued_at' => '2026-03-05 10:00:00',
		]);
		$this->service('2026-03-06 00:00:00')->run();

		$commission = $this->commissionOf($rectify);
		$this->assertNotNull($commission);
		$this->assertEquals('-100.00', $commission->base_amount);
		$this->assertEquals('10.00', $commission->percentage);
		$this->assertEquals('-10.00', $commission->amount);
		$this->assertEquals(Invoice::TYPE_RECTIFY, (int) $commission->type);
		$this->assertEquals(AffiliateCommission::STATUS_PENDING, (int) $commission->status);
	}

	public function testARectifyOfAnInvoiceThatEarnedNothingIsSkipped()
	{
		$unattributedOriginal = $this->invoice(['subscriber_id' => $this->strangerId()]);
		$rectify = $this->invoice([
			'type' => Invoice::TYPE_RECTIFY,
			'parent_id' => $unattributedOriginal,
			'issued_at' => '2026-03-05 10:00:00',
		]);

		$this->service()->run();

		$this->assertNull($this->commissionOf($rectify));
	}

	public function testADeletedInvoiceCancelsItsUnpaidCommission()
	{
		$invoiceId = $this->invoice();
		$this->service('2026-03-02 00:00:00')->run();
		$this->assertEquals(AffiliateCommission::STATUS_PENDING, (int) $this->commissionOf($invoiceId)->status);

		Yii::$app->db->createCommand()->update('invoice', ['deleted' => 1], ['id' => $invoiceId])->execute();
		$result = $this->service('2026-03-03 00:00:00')->run();

		$this->assertEquals(1, $result['cancelled']);
		$this->assertEquals(AffiliateCommission::STATUS_CANCELLED, (int) $this->commissionOf($invoiceId)->status);
	}

	public function testADeletedInvoiceDoesNotTouchAPaidCommission()
	{
		$invoiceId = $this->invoice();
		$this->service()->run();
		Yii::$app->db->createCommand()->update('affiliate_commission', ['status' => AffiliateCommission::STATUS_PAID], ['invoice_id' => $invoiceId])->execute();

		Yii::$app->db->createCommand()->update('invoice', ['deleted' => 1], ['id' => $invoiceId])->execute();
		$this->service()->run();

		$this->assertEquals(AffiliateCommission::STATUS_PAID, (int) $this->commissionOf($invoiceId)->status);
	}

	public function testTheStornoOfACancelledCommissionIsCancelledToo()
	{
		$original = $this->invoice();
		$this->service('2026-03-02 00:00:00')->run();
		$rectify = $this->invoice(['type' => Invoice::TYPE_RECTIFY, 'parent_id' => $original, 'issued_at' => '2026-03-03 10:00:00']);
		$this->service('2026-03-04 00:00:00')->run();
		$this->assertNotNull($this->commissionOf($rectify));

		Yii::$app->db->createCommand()->update('invoice', ['deleted' => 1], ['id' => $original])->execute();
		$this->service('2026-03-05 00:00:00')->run();

		$this->assertEquals(AffiliateCommission::STATUS_CANCELLED, (int) $this->commissionOf($original)->status);
		$this->assertEquals(AffiliateCommission::STATUS_CANCELLED, (int) $this->commissionOf($rectify)->status);
	}

	public function testRerunningIsIdempotent()
	{
		$this->invoice();
		$this->invoice(['paid_at' => '2026-03-10 10:00:00']);

		$first = $this->service()->run();
		$second = $this->service()->run();

		$this->assertEquals(2, $first['created']);
		$this->assertEquals(['created' => 0, 'cancelled' => 0, 'payable' => 0], $second);
		$this->assertEquals(2, (new Query())->from('affiliate_commission')->count());
	}

	public function testPendingBecomesPayableOnlyAfterTheHold()
	{
		$invoiceId = $this->invoice();

		$this->service('2026-03-30 10:00:00')->run();
		$this->assertEquals(AffiliateCommission::STATUS_PENDING, (int) $this->commissionOf($invoiceId)->status);

		$result = $this->service('2026-03-31 10:00:00')->run();
		$this->assertEquals(1, $result['payable']);
		$this->assertEquals(AffiliateCommission::STATUS_PAYABLE, (int) $this->commissionOf($invoiceId)->status);
	}

	public function testBulkApproveAndCancel()
	{
		$a = $this->invoice();
		$b = $this->invoice(['paid_at' => '2026-03-02 10:00:00']);
		$this->service('2026-03-03 00:00:00')->run();

		$service = $this->service('2026-03-03 00:00:00');
		$this->assertEquals(1, $service->approve([$this->commissionOf($a)->uuid]));
		$this->assertEquals(1, $service->cancel([$this->commissionOf($b)->getAttribute('id')]));

		$this->assertEquals(AffiliateCommission::STATUS_PAYABLE, (int) $this->commissionOf($a)->status);
		$this->assertEquals(AffiliateCommission::STATUS_CANCELLED, (int) $this->commissionOf($b)->status);
	}
}
