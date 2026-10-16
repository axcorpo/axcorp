<?php

namespace tests\unit;

use backend\modules\nomenclature\models\PackageForm;
use common\helpers\UuidHelper;
use common\models\Package;
use common\models\PaymentMetadata;
use tests\DatabaseTestCase;
use tests\support\FakeSettings;
use Yii;

/**
 * The package → Stripe product sync ([[PackageForm]]), without the HTTP leg:
 *
 * - the price payload carries whole cents (no float artifacts) and the billing cycle;
 * - the product payload uses the form's current-language name and the canonical UUID
 *   in metadata (raw BINARY(16) bytes are not valid in Stripe metadata);
 * - stripePriceMatches() detects amount/currency/cycle drift (currency case-insensitive);
 * - saveModel(): free packages never touch Stripe, a paid package stores the product id
 *   in external_id, and a Stripe failure does NOT roll back the local save.
 */
class PackageStripeSyncTest extends DatabaseTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		Yii::$app->set('settings', new FakeSettings([
			'values' => [
				'general' => [
					'currencyCode' => 'RON',
				],
				'payment' => [
					'paymentMethods' => [
						PaymentMetadata::PAYMENT_METHOD_CARD => 'Card',
					],
					'paymentProcessors' => [
						PaymentMetadata::PAYMENT_METHOD_CARD => [
							PaymentMetadata::PAYMENT_PROCESSOR_STRIPE => 'Stripe',
						],
					],
				],
			],
		]));
	}

	protected function tearDown(): void
	{
		Yii::$app->clear('settings');
		parent::tearDown();
	}

	/**
	 * A form for a standard paid package; Stripe is stubbed out.
	 *
	 * @return StripeStubPackageForm
	 */
	private function createPaidForm()
	{
		$form = new StripeStubPackageForm();
		$form->name = [Yii::$app->language => 'Pachet Test'];
		$form->content = [Yii::$app->language => null];
		$form->price = 19.99;

		return $form;
	}

	public function testInitDefaultsAMonthlyStandardPackageWithItsFeatureMinimums()
	{
		$form = new PackageForm();

		$this->assertSame(1, $form->billing_period);
		$this->assertSame('month', $form->billing_cycle);
		$this->assertSame('RON', $form->currency);
		$this->assertEquals(PackageForm::TYPE_STANDARD, $form->type);
		$this->assertEquals(1, $form->{\common\models\Feature::WORKSPACES});
		$this->assertEquals(1, $form->{\common\models\Feature::USERS});
	}

	public function testSaveWorksWithoutAnyFeatureInput()
	{
		// The form posts no feature values: the init() minimums carry the required
		// features, and savePackageFeatures() writes one row per catalog feature.
		$form = $this->createPaidForm();
		$form->stripeResult = ['id' => 'prod_test_456'];

		$this->assertNotFalse($form->saveModel(), print_r($form->errors, true));
		$this->assertNotNull(Package::findOne(['id' => $form->id]));
	}

	public function testThePricePayloadCarriesWholeCentsAndTheBillingCycle()
	{
		$form = $this->createPaidForm();

		$priceData = $form->buildStripePriceData();

		// 19.99 × 100 is 1998.9999999999998 in floats — the payload must say 1999.
		$this->assertSame(1999.0, $priceData['unit_amount_decimal']);
		$this->assertSame('RON', $priceData['currency']);
		$this->assertSame('month', $priceData['recurring']['interval']);
		$this->assertSame(1, $priceData['recurring']['interval_count']);
	}

	public function testTheProductPayloadUsesTheFormNameAndTheCanonicalUuid()
	{
		$form = $this->createPaidForm();
		$this->assertNotFalse($form->saveModel());

		$productData = $form->buildStripeProductData();

		$this->assertSame('Pachet Test', $productData['name']);
		$this->assertTrue($productData['active']);
		$this->assertTrue(UuidHelper::isValid($productData['metadata']['package_id']));
	}

	public function testStripePriceMatchesDetectsDrift()
	{
		$priceData = [
			'unit_amount_decimal' => 1999.0,
			'currency' => 'RON',
			'recurring' => ['interval' => 'month', 'interval_count' => 1],
		];
		// Stripe reports the currency lowercase and the amount as a string.
		$current = [
			'unit_amount_decimal' => '1999',
			'currency' => 'ron',
			'recurring' => ['interval' => 'month', 'interval_count' => 1],
		];

		$this->assertTrue(PackageForm::stripePriceMatches($current, $priceData));
		$this->assertFalse(PackageForm::stripePriceMatches(null, $priceData));
		$this->assertFalse(PackageForm::stripePriceMatches(
			array_replace($current, ['unit_amount_decimal' => '2999']), $priceData));
		$this->assertFalse(PackageForm::stripePriceMatches(
			array_replace($current, ['currency' => 'eur']), $priceData));
		$this->assertFalse(PackageForm::stripePriceMatches(
			array_replace_recursive($current, ['recurring' => ['interval' => 'year']]), $priceData));
		$this->assertFalse(PackageForm::stripePriceMatches(
			array_replace_recursive($current, ['recurring' => ['interval_count' => 3]]), $priceData));
	}

	public function testAFreePackageNeverTouchesStripeAndStillSaves()
	{
		$form = $this->createPaidForm();
		$form->type = PackageForm::TYPE_FREE;

		$this->assertNotFalse($form->saveModel());
		$this->assertSame(0, $form->stripeCalls);

		$package = Package::findOne(['id' => $form->id]);
		$this->assertNotNull($package);
		$this->assertNull($package->external_id);
		$this->assertEquals(0.0, (float) $package->price);
	}

	public function testAPaidPackageStoresTheStripeProductId()
	{
		$form = $this->createPaidForm();
		$form->stripeResult = ['id' => 'prod_test_123'];

		$this->assertNotFalse($form->saveModel());
		$this->assertSame(1, $form->stripeCalls);

		$package = Package::findOne(['id' => $form->id]);
		$this->assertSame('prod_test_123', $package->external_id);
	}

	public function testAStripeFailureDoesNotRollBackTheLocalSave()
	{
		$form = $this->createPaidForm();
		$form->stripeResult = null; // what saveStripeProduct() returns on any Stripe error

		$this->assertNotFalse($form->saveModel());
		$this->assertSame(1, $form->stripeCalls);

		// The package exists locally; external_id stays empty so the next save backfills it.
		$package = Package::findOne(['id' => $form->id]);
		$this->assertNotNull($package);
		$this->assertNull($package->external_id);
	}
}

/**
 * PackageForm with the Stripe HTTP leg replaced by a canned result.
 */
class StripeStubPackageForm extends PackageForm
{
	/**
	 * @var int how many times saveStripeProduct() was invoked
	 */
	public $stripeCalls = 0;

	/**
	 * @var array|null the canned saveStripeProduct() result
	 */
	public $stripeResult;

	/**
	 * @inheritdoc
	 */
	public function saveStripeProduct($isNewRecord = true)
	{
		$this->stripeCalls++;
		return $this->stripeResult;
	}
}
