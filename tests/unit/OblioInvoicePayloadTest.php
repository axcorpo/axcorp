<?php

namespace tests\unit;

use common\models\Invoice;
use common\models\Item;
use common\models\OblioInvoice;
use tests\DatabaseTestCase;

/**
 * The Oblio payload builders — the pure, local half of the OblioInvoice facade (the HTTP
 * leg is not exercised here). These cover exactly the parts adapted to this platform:
 *
 * - the product line is named after the subscription's package plus the billed period
 *   frozen in the item details, falling back to the bare "Abonament";
 * - a rectify (storno) invoice negates the line price;
 * - the client block is B2B (carries `cif`) only when the frozen snapshot has a TIN;
 * - a paid invoice carries a `collect` block, an unpaid one does not;
 * - the seller CIF must come from the frozen merchant snapshot or an explicit option.
 */
class OblioInvoicePayloadTest extends DatabaseTestCase
{
	/**
	 * Inserts an invoice with a frozen merchant/client snapshot.
	 *
	 * @param array $attributes
	 * @param array $details merged over the default snapshot
	 * @return Invoice
	 */
	private function createInvoice(array $attributes = [], array $details = [])
	{
		$id = $this->insertRow('invoice', $attributes + [
			'document_series' => 'TST',
			'document_number' => 7,
			'type' => Invoice::TYPE_INVOICE,
			'status' => Invoice::STATUS_PAID,
			'issued_at' => '2026-08-01 10:00:00',
			'paid_at' => '2026-08-01 10:05:00',
			'amount' => '238.00',
			'currency' => 'RON',
			'vat' => 19,
			'deleted' => 0,
			'details' => serialize($details + [
				'merchant' => ['tin' => 'RO111'],
				'client' => ['name' => 'Client Test'],
				'transaction_id' => 'txn-1',
			]),
		]);

		return Invoice::findOne(['id' => $id]);
	}

	/**
	 * Inserts an active item on the invoice.
	 *
	 * @param Invoice $invoice
	 * @param array $attributes
	 * @return string raw bytes
	 */
	private function createItem(Invoice $invoice, array $attributes = [])
	{
		return $this->insertRow('item', $attributes + [
			'invoice_id' => $invoice->id,
			'type' => Item::TYPE_SUBSCRIPTION,
			'quantity' => 1,
			'price' => '238.00',
			'currency' => 'RON',
			'status' => 1,
			'deleted' => 0,
		]);
	}

	public function testTheProductIsNamedAfterThePackageAndTheBilledPeriod()
	{
		$packageId = $this->insertRow('package', ['deleted' => 0]);
		$this->insertRow('package_translation', [
			'package_id' => $packageId,
			'language_id' => 'en-US',
			'name' => 'Pachet Premium',
			'deleted' => 0,
		]);
		$subscriptionId = $this->insertRow('subscription', [
			'package_id' => $packageId,
			'deleted' => 0,
		]);

		$invoice = $this->createInvoice();
		$this->createItem($invoice, [
			'item_id' => $subscriptionId,
			'details' => serialize([
				'start_at' => '2026-08-01 00:00:00',
				'end_at' => '2026-09-01 00:00:00',
			]),
		]);

		$products = OblioInvoice::buildProductsFromLocalInvoice($invoice);

		$this->assertCount(1, $products);
		$this->assertSame('Abonament (Pachet Premium, 2026-08-01 - 2026-09-01)', $products[0]['name']);
		$this->assertSame(238.0, $products[0]['price']);
		$this->assertSame(19.0, $products[0]['vatPercentage']);
		$this->assertSame(1, $products[0]['vatIncluded']);
		$this->assertSame('Serviciu', $products[0]['productType']);
	}

	public function testTheProductNameFallsBackToTheBareAbonament()
	{
		// No subscription behind the item and no billed period in its details.
		$invoice = $this->createInvoice();
		$this->createItem($invoice);

		$products = OblioInvoice::buildProductsFromLocalInvoice($invoice);

		$this->assertSame('Abonament', $products[0]['name']);
	}

	public function testARectifyInvoiceNegatesTheLinePrice()
	{
		$invoice = $this->createInvoice(['type' => Invoice::TYPE_RECTIFY]);
		$this->createItem($invoice, ['price' => '119.00']);

		$products = OblioInvoice::buildProductsFromLocalInvoice($invoice);

		$this->assertSame(-119.0, $products[0]['price']);
	}

	public function testAnInvoiceWithoutActiveItemsCannotBuildProducts()
	{
		$invoice = $this->createInvoice();
		// The only line is soft-deleted, so the active()/deleted() scopes exclude it.
		$this->createItem($invoice, ['deleted' => 1]);

		$this->expectExceptionMessage('no items');
		OblioInvoice::buildProductsFromLocalInvoice($invoice);
	}

	public function testTheClientBlockIsB2bOnlyWhenTheSnapshotHasATin()
	{
		$b2b = $this->createInvoice([], [
			'client' => [
				'tin' => 'RO999',
				'name' => 'SC Client SRL',
				'registration_number' => 'J00/1/2020',
				'county' => 'Timiș',
				'locality' => 'Timișoara',
			],
		]);
		$client = OblioInvoice::buildClientFromLocalInvoiceDetails($b2b);
		$this->assertSame('RO999', $client['cif']);
		$this->assertSame('J00/1/2020', $client['rc']);
		$this->assertSame('Timiș', $client['state']);
		$this->assertSame('Timișoara', $client['city']);

		$b2c = $this->createInvoice();
		$client = OblioInvoice::buildClientFromLocalInvoiceDetails($b2c);
		$this->assertSame('Client Test', $client['name']);
		$this->assertArrayNotHasKey('cif', $client);
	}

	public function testAPaidInvoiceCarriesACollectBlockAndAnUnpaidOneDoesNot()
	{
		$paid = $this->createInvoice();
		$this->createItem($paid);

		$payload = OblioInvoice::buildInvoicePayloadFromLocalInvoice($paid, [
			'seriesName' => $paid->document_series,
			'disableAutoSeries' => 1,
			'number' => $paid->document_number,
		]);

		$this->assertSame('RO111', $payload['cif']);
		$this->assertSame('TST', $payload['seriesName']);
		$this->assertSame(7, $payload['number']);
		$this->assertSame('2026-08-01', $payload['issueDate']);
		$this->assertSame('Card', $payload['collect']['type']);
		$this->assertSame(238.0, $payload['collect']['value']);
		$this->assertSame('2026-08-01', $payload['collect']['issueDate']);

		$unpaid = $this->createInvoice(['status' => Invoice::STATUS_UNPAID, 'paid_at' => null]);
		$this->createItem($unpaid);

		$payload = OblioInvoice::buildInvoicePayloadFromLocalInvoice($unpaid);
		$this->assertArrayNotHasKey('collect', $payload);
		// Without disableAutoSeries the number is Oblio's to assign.
		$this->assertArrayNotHasKey('number', $payload);
	}

	public function testTheSellerCifMustComeFromTheSnapshotOrAnExplicitOption()
	{
		$invoice = $this->createInvoice([], ['merchant' => []]);
		$this->createItem($invoice);

		$payload = OblioInvoice::buildInvoicePayloadFromLocalInvoice($invoice, ['sellerCif' => 'RO222']);
		$this->assertSame('RO222', $payload['cif']);

		$this->expectExceptionMessage('Missing seller CIF');
		OblioInvoice::buildInvoicePayloadFromLocalInvoice($invoice);
	}
}
