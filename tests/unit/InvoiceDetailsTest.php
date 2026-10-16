<?php

namespace tests\unit;

use common\models\Invoice;
use PHPUnit\Framework\TestCase;
use tests\support\FakeSettings;
use Yii;

/**
 * The invoice snapshot written at payment time. Its readers (the invoice template
 * short codes, the Oblio export, the e-invoice form) look up `merchant` and `client`,
 * so that is what the writers must call and store — the "getCompanyDetails()" name
 * inherited from the source app never existed and broke every paid Stripe checkout.
 */
class InvoiceDetailsTest extends TestCase
{
	protected function tearDown(): void
	{
		Yii::$app->clear('settings');
		parent::tearDown();
	}

	public function testTheMerchantSnapshotComesFromTheContactSettings()
	{
		Yii::$app->set('settings', new FakeSettings(['values' => ['contact' => [
			'company' => 'AXCORP SRL',
			'tin' => 'RO123',
			'registrationNumber' => 'J40/1/2020',
			'firstName' => 'Ana', 'middleName' => '', 'lastName' => 'Pop',
			'email' => 'office@test.local',
			'mobilePhone' => '0700', 'fixedPhone' => '',
			'fax' => '',
			'streetName' => 'Str. Test', 'streetNumber' => '1',
			'zipCode' => '300011', 'locality' => 'Timișoara', 'county' => 'Timiș', 'country' => 'RO',
		]]]));

		$merchant = (new Invoice())->getMerchantDetails();

		$this->assertSame('AXCORP SRL', $merchant['name']);
		$this->assertSame('RO123', $merchant['tin']);
		$this->assertSame('Ana Pop', $merchant['legal_representative']);
		$this->assertSame('0700', $merchant['phone']);
		$this->assertSame('Str. Test, 1', $merchant['address']);
		// The contact setting stores the key as zipCode; the snapshot exposes zip_code.
		$this->assertSame('300011', $merchant['zip_code']);
		foreach (['name', 'tin', 'registration_number', 'legal_representative', 'email', 'phone', 'fax', 'address'] as $key) {
			$this->assertArrayHasKey($key, $merchant, "the template short codes read `$key`");
		}
	}

	public function testTheWritersStoreTheKeysTheReadersLookUp()
	{
		$writers = [
			dirname(__DIR__, 2) . '/frontend/modules/account/models/PaymentResult.php',
			dirname(__DIR__, 2) . '/backend/modules/subscriber/models/SubscriptionActivateForm.php',
			// The renewal invoices (proforma and fiscal) are written by the shared service.
			dirname(__DIR__, 2) . '/common/services/SubscriptionPaymentService.php',
		];

		foreach ($writers as $file) {
			$source = file_get_contents($file);
			$this->assertStringNotContainsString('getCompanyDetails', $source, basename($file) . ' calls a method Invoice never had');
			$this->assertStringContainsString("'merchant' => \$invoice->getMerchantDetails()", $source, basename($file));
			$this->assertStringContainsString("'client' => \$invoice->getClientDetails()", $source, basename($file));
		}

		$this->assertFalse(method_exists(Invoice::class, 'getCompanyDetails'));
		$this->assertTrue(method_exists(Invoice::class, 'getMerchantDetails'));
		$this->assertTrue(method_exists(Invoice::class, 'getClientDetails'));
	}
}
