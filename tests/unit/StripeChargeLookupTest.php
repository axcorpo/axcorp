<?php

namespace tests\unit;

use frontend\modules\account\models\PaymentResult;
use PHPUnit\Framework\TestCase;

/**
 * The charge id behind a paid Checkout session, across Stripe API generations. Without
 * it payment_id stays empty and the polling treats the subscription as not configured
 * for recurring payment, suspending it while Stripe keeps charging.
 */
class StripeChargeLookupTest extends TestCase
{
	/**
	 * A stand-in for \Stripe\StripeClient with just the three resources the lookup touches.
	 */
	private function stripe(array $invoice, $paymentIntent = null, array $charges = [], $expandThrows = false)
	{
		$invoices = new class($invoice, $expandThrows) {
			public $calls = [];
			public function __construct(private array $invoice, private bool $expandThrows) {}
			public function retrieve($id, $params)
			{
				$this->calls[] = $params;
				if ($this->expandThrows && !empty($params['expand'])) {
					throw new \Exception('Unknown expand');
				}
				return $this->invoice;
			}
		};
		$paymentIntents = new class($paymentIntent) {
			public function __construct(private $paymentIntent) {}
			public function retrieve($id, $params) { return $this->paymentIntent; }
		};
		$chargesApi = new class($charges) {
			public function __construct(private array $charges) {}
			public function all($params) { return ['data' => $this->charges]; }
		};
		return (object) ['invoices' => $invoices, 'paymentIntents' => $paymentIntents, 'charges' => $chargesApi];
	}

	public function testNewApiVersionsCarryTheIntentOnThePaymentsListAndTheChargeInLatestCharge()
	{
		$stripe = $this->stripe(
			['payments' => ['data' => [['payment' => ['payment_intent' => ['id' => 'pi_1', 'latest_charge' => 'ch_new']]]]]]
		);

		$this->assertSame('ch_new', PaymentResult::findSessionChargeId($stripe, ['invoice' => 'in_1', 'customer' => 'cus_1']));
	}

	public function testOldApiVersionsCarryTheIntentIdOnTheInvoiceAndTheChargeInChargesData()
	{
		$stripe = $this->stripe(
			['payment_intent' => 'pi_1'],
			['id' => 'pi_1', 'charges' => ['data' => [['id' => 'ch_old']]]],
			[],
			true
		);

		$this->assertSame('ch_old', PaymentResult::findSessionChargeId($stripe, ['invoice' => 'in_1']));
	}

	public function testAnExpandedLatestChargeObjectIsReducedToItsId()
	{
		$stripe = $this->stripe(
			['payment_intent' => 'pi_1'],
			['id' => 'pi_1', 'latest_charge' => ['id' => 'ch_obj']]
		);

		$this->assertSame('ch_obj', PaymentResult::findSessionChargeId($stripe, ['invoice' => 'in_1']));
	}

	public function testTheCustomersNewestChargeIsTheLastResort()
	{
		$stripe = $this->stripe(['payments' => ['data' => []]], null, [['id' => 'ch_last']]);

		$this->assertSame('ch_last', PaymentResult::findSessionChargeId($stripe, ['invoice' => 'in_1', 'customer' => 'cus_1']));
	}

	public function testNothingFoundGivesNull()
	{
		$stripe = $this->stripe(['payments' => ['data' => []]]);

		$this->assertNull(PaymentResult::findSessionChargeId($stripe, ['invoice' => 'in_1']));
		$this->assertNull(PaymentResult::findSessionChargeId($stripe, []));
	}
}
