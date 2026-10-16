<?php

namespace backend\modules\subscriber\models;

use common\helpers\Inflector;
use common\helpers\NumberHelper;
use common\models\Integration;
use common\models\Invoice;
use common\models\Item;
use common\models\Country;
use common\models\County;
use common\models\Subscription;
use common\models\SubscriptionFeature;
use tws\helpers\FileHelper;
use Yii;
use yii\db\Expression;
use yii\helpers\ArrayHelper;

class EInvoiceForm extends Invoice
{
	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [];
	}

	/**
	 * Exports data to XML.
	 *
	 * @return mixed
	 * @throws \yii\base\InvalidConfigException
	 * @throws \yii\db\Exception
	 */
	protected function exportToXML()
	{
		$model = Invoice::findOne(['id' => $this->id]);
		$details = $this->getUnserializedValue('details');

		$data = []; $errors = [];
		$data['document_series'] = $model->document_series;
		$data['document_number'] = str_pad($model->document_number, 5, 0, STR_PAD_LEFT);
		$data['issue_date'] = $model->issued_at;
		$data['due_date'] = $model->due_at;
		$data['currency'] = $model->currency ?: Yii::$app->settings->get('currencyCode');

		if (empty($data['document_series'])) {
			$errors[] = Yii::t('common', 'Value for `{item}` is invalid.', ['item' => Yii::t('label', 'Document Series')]);
		}
		if (empty($data['document_number'])) {
			$errors[] = Yii::t('common', 'Value for `{item}` is invalid.', ['item' => Yii::t('label', 'Document Number')]);
		}
		if (empty($data['issue_date'])) {
			$errors[] = Yii::t('common', 'Value for `{item}` is invalid.', ['item' => Yii::t('label', 'Issue Date')]);
		}
		if (empty($data['currency'])) {
			$errors[] = Yii::t('common', 'Value for `{item}` is invalid.', ['item' => Yii::t('label', 'Currency')]);
		}

		$merchant = $details['merchant'];
		$client = $details['client'];

		$data['merchant']['name'] = $merchant['name'];
		$data['merchant']['tin'] = mb_strtoupper(str_replace(' ', '', $merchant['tin']), 'UTF-8');
		$data['merchant']['registration_number'] = $merchant['registration_number'];
		$data['merchant']['address'] = $merchant['address'];
		$data['merchant']['locality'] = str_replace('Sector ', 'SECTOR', $merchant['locality']);
		$data['merchant']['country_code'] = $merchant['country'];
		$data['merchant']['county_code'] = County::getCountyCode($merchant['county']);

		if (empty($data['merchant']['name'])) {
			$errors[] = Yii::t('common', 'Value for `{item}` is invalid.', ['item' => Yii::t('label', 'Merchant Company Name')]);
		}
		if (empty($data['merchant']['tin'])) {
			$errors[] = Yii::t('common', 'Value for `{item}` is invalid.', ['item' => Yii::t('label', 'Merchant Tax Identification Number')]);
		}
		if (empty($data['merchant']['address'])) {
			$errors[] = Yii::t('common', 'Value for `{item}` is invalid.', ['item' => Yii::t('label', 'Merchant Address')]);
		}
		if (empty($data['merchant']['locality'])) {
			$errors[] = Yii::t('common', 'Value for `{item}` is invalid.', ['item' => Yii::t('label', 'Merchant Locality')]);
		}
		if (empty($data['merchant']['country_code']) || !in_array($data['merchant']['country_code'], ArrayHelper::getColumn(Country::findAllCountries(), 'iso_alpha2'))) {
			$errors[] = Yii::t('common', 'Value for `{item}` is invalid.', ['item' => Yii::t('label', 'Merchant Country Code')]);
		}
		if ($data['merchant']['country_code'] == 'RO' && empty($data['merchant']['county_code'])) {
			$errors[] = Yii::t('common', 'Value for `{item}` is invalid.', ['item' => Yii::t('label', 'Merchant County Code')]);
		}

		$data['client']['name'] = $client['name'];
		$data['client']['tin'] = mb_strtoupper(str_replace(' ', '', $client['tin']), 'UTF-8');
		$data['client']['registration_number'] = $client['registration_number'];
		$data['client']['address'] = $client['address'];
		$data['client']['locality'] = str_replace('Sector ', 'SECTOR', $client['locality']);
		$data['client']['country_code'] = $client['country'];
		$data['client']['county_code'] = County::getCountyCode($client['county']);

		if (empty($data['client']['name'])) {
			$errors[] = Yii::t('common', 'Value for `{item}` is invalid.', ['item' => Yii::t('label', 'Client Company Name')]);
		}
		if (empty($data['client']['tin'])) {
			$errors[] = Yii::t('common', 'Value for `{item}` is invalid.', ['item' => Yii::t('label', 'Client Tax Identification Number')]);
		}
		if (empty($data['client']['address'])) {
			$errors[] = Yii::t('common', 'Value for `{item}` is invalid.', ['item' => Yii::t('label', 'Client Address')]);
		}
		if (empty($data['client']['locality'])) {
			$errors[] = Yii::t('common', 'Value for `{item}` is invalid.', ['item' => Yii::t('label', 'Client Locality')]);
		}
		if (empty($data['client']['country_code']) || !in_array($data['client']['country_code'], ArrayHelper::getColumn(Country::findAllCountries(), 'iso_alpha2'))) {
			$errors[] = Yii::t('common', 'Value for `{item}` is invalid.', ['item' => Yii::t('label', 'Client Country Code')]);
		}
		if ($data['client']['country_code'] == 'RO' && empty($data['client']['county_code'])) {
			$errors[] = Yii::t('common', 'Value for `{item}` is invalid.', ['item' => Yii::t('label', 'Client County Code')]);
		}

		/** @var Item $subscriptionItem */
		$subscriptionItem = $model->getItems()->andWhere(['type' => Item::TYPE_SUBSCRIPTION])->limit(1)->one();
		$subscription = $subscriptionItem->subscription;


		$data['rounding_amount'] = NumberHelper::toNumber($data['totalAmount']);

		if ($subscription->type == Subscription::TYPE_FEATURE) {
			$itemDetails = $subscriptionItem->getUnserializedValue('details');
			/** @var SubscriptionFeature $subscriptionFeature */
			foreach ($subscription->getSubscriptionFeatures()->each() as $subscriptionFeature) {
				$name = $subscriptionFeature->getFormattedName();
				$description = '';
				$description = (strlen($description) <= 200 ? $description : '');

				$quantity = 1;
				$unitPrice = $subscriptionFeature->grossUnitPrice;
				$subtotal = $subscriptionFeature->price;
				$vatRate = $model->vat;
				$vatValue = 0;
				$currency = $model->currency;
				$unitCode = 'H87';
				if ($vatRate) {
					$unitPrice = $subscriptionFeature->netUnitPrice;
					$subtotal = $subscriptionFeature->netPrice;
					$vatValue = $subscriptionFeature->vatValue;
				}

				Yii::$app->formatter->decimalSeparator = '.';
				$data['vatRate'][Yii::$app->formatter->asDecimal($vatRate, 2)]['subtotalAmount'] += $subtotal;
				$data['vatRate'][Yii::$app->formatter->asDecimal($vatRate, 2)]['vatAmount'] += $vatValue;
				$data['vatRate'][Yii::$app->formatter->asDecimal($vatRate, 2)]['totalAmount'] += $subtotal + $vatValue;
				$data['subtotalAmount'] += $subtotal;
				$data['vatAmount'] += $vatValue;
				$data['totalAmount'] += $subtotal + $vatValue;

				$data['items'][Yii::$app->formatter->asDecimal($vatRate, 2)][] = [
					'name' => $name,
					'description' => $description,
					'quantity' => $quantity,
					'unit_price' => $unitPrice,
					'subtotal' => $subtotal,
					'vat_value' => $vatValue,
					'vat_rate' => $vatRate,
					'currency' => $currency,
					'unit_code' => $unitCode,
				];
			}
		} else {
			/** @var Item $item */
			foreach ($model->getItems()->active()->deleted(false)->each() as $item) {
				$itemDetails = $item->getUnserializedValue('details');
				$name = ($itemDetails['subscription'] ? Yii::t('label', 'Subscription') . " " . $itemDetails['subscription'] : Yii::t('label', 'Subscription') . " #{$item->subscription->code} ({$item->subscription->package->translation->name})");
				$description = implode(' - ', [Yii::$app->formatter->asDatetime($itemDetails['start_at']), Yii::$app->formatter->asDatetime($itemDetails['end_at'])]);
				$description = (strlen($description) <= 200 ? $description : '');

				$quantity = 1;
				$unitPrice = $item->grossUnitPrice;
				$subtotal = $item->price;
				$vatRate = $model->vat;
				$vatValue = 0;
				$currency = $model->currency;
				$unitCode = 'H87';
				if ($vatRate) {
					$unitPrice = $item->netUnitPrice;
					$subtotal = $item->netPrice;
					$vatValue = $item->vatValue;
				}

				Yii::$app->formatter->decimalSeparator = '.';
				$data['vatRate'][Yii::$app->formatter->asDecimal($vatRate, 2)]['subtotalAmount'] += $subtotal;
				$data['vatRate'][Yii::$app->formatter->asDecimal($vatRate, 2)]['vatAmount'] += $vatValue;
				$data['vatRate'][Yii::$app->formatter->asDecimal($vatRate, 2)]['totalAmount'] += $subtotal + $vatValue;
				$data['subtotalAmount'] += $subtotal;
				$data['vatAmount'] += $vatValue;
				$data['totalAmount'] += $subtotal + $vatValue;

				$data['items'][Yii::$app->formatter->asDecimal($vatRate, 2)][] = [
					'name' => $name,
					'description' => $description,
					'quantity' => $quantity,
					'unit_price' => $unitPrice,
					'subtotal' => $subtotal,
					'vat_value' => $vatValue,
					'vat_rate' => $vatRate,
					'currency' => $currency,
					'unit_code' => $unitCode,
				];
			}
		}

		try {

			if (!empty($errors)) {
				throw new \Exception();
			}

			$doc = new \DOMDocument('1.0', 'UTF-8');
			$doc->xmlStandalone = true;
			$doc->formatOutput = true;

			$invoice = $doc->createElement('Invoice');
			$invoice->setAttribute('xmlns', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
			$invoice->setAttribute('xmlns:qdt', 'urn:oasis:names:specification:ubl:schema:xsd:QualifiedDataTypes-2');
			$invoice->setAttribute('xmlns:ccts', 'urn:un:unece:uncefact:documentation:2');
			$invoice->setAttribute('xmlns:udt', 'urn:oasis:names:specification:ubl:schema:xsd:UnqualifiedDataTypes-2');
			$invoice->setAttribute('xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
			$invoice->setAttribute('xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

			$doc->appendChild($invoice);

			$versionID = $doc->createElement('cbc:UBLVersionID', '2.1');
			$invoice->appendChild($versionID);

			$customizationID = $doc->createElement('cbc:CustomizationID', 'urn:cen.eu:en16931:2017#compliant#urn:efactura.mfinante.ro:CIUS-RO:1.0.1');
			$invoice->appendChild($customizationID);

			$id = $doc->createElement('cbc:ID', implode(' nr. ', [$data['document_series'], $data['document_number']]));
			$invoice->appendChild($id);

			$comment = $doc->createComment('BT-1');
			$invoice->appendChild($comment);

			$issueDate = $doc->createElement('cbc:IssueDate', date('Y-m-d', strtotime($data['issue_date'])));
			$invoice->appendChild($issueDate);

			$comment = $doc->createComment('BT-2');
			$invoice->appendChild($comment);

			if (!empty($data['due_date'])) {
				$dueDate = $doc->createElement('cbc:DueDate', date('Y-m-d', strtotime($data['due_date'])));
				$invoice->appendChild($dueDate);

				$comment = $doc->createComment('BT-9');
				$invoice->appendChild($comment);
			}

			$invoiceTypeCode = $doc->createElement('cbc:InvoiceTypeCode', '380');
			$invoice->appendChild($invoiceTypeCode);

			$comment = $doc->createComment('BT-3');
			$invoice->appendChild($comment);

			$note = $doc->createElement('cbc:Note',  htmlspecialchars(Inflector::transliterate('*** Prezenta factură circulă fără semnătură si stampilă, conform art. 319 (29) din Legea 227/2015. *** ')));
			$invoice->appendChild($note);

			$comment = $doc->createComment('BT-22');
			$invoice->appendChild($comment);

			$documentCurrencyCode = $doc->createElement('cbc:DocumentCurrencyCode');
			$documentCurrencyCode->appendChild($doc->createTextNode(htmlspecialchars($data['currency'])));
			$invoice->appendChild($documentCurrencyCode);

			$comment = $doc->createComment('BT-5');
			$invoice->appendChild($comment);

			$accountingSupplierParty = $doc->createElement('cac:AccountingSupplierParty');
			$invoice->appendChild($accountingSupplierParty);

			$comment = $doc->createComment('BG-4');
			$accountingSupplierParty->appendChild($comment);

			$party = $doc->createElement('cac:Party');
			$accountingSupplierParty->appendChild($party);

			$comment = $doc->createComment('BT-34');
			$party->appendChild($comment);

			$postalAddress = $doc->createElement('cac:PostalAddress');
			$party->appendChild($postalAddress);

			$comment = $doc->createComment('BG-5');
			$postalAddress->appendChild($comment);

			$streetName = $doc->createElement('cbc:StreetName', htmlspecialchars(Inflector::transliterate($data['merchant']['address'])));
			$postalAddress->appendChild($streetName);

			$comment = $doc->createComment('BT-35');
			$postalAddress->appendChild($comment);

			$cityName = $doc->createElement('cbc:CityName', htmlspecialchars(Inflector::transliterate($data['merchant']['locality'])));
			$postalAddress->appendChild($cityName);

			$comment = $doc->createComment('BT-37');
			$postalAddress->appendChild($comment);

			$countrySubentity = $doc->createElement('cbc:CountrySubentity', htmlspecialchars(implode('-', [$data['merchant']['country_code'], $data['merchant']['county_code']])));
			$postalAddress->appendChild($countrySubentity);

			$comment = $doc->createComment('BT-39');
			$postalAddress->appendChild($comment);

			$country = $doc->createElement('cac:Country');
			$country->appendChild($doc->createElement('cbc:IdentificationCode', htmlspecialchars($data['merchant']['country_code'])));
			$postalAddress->appendChild($country);

			$comment = $doc->createComment('BT-40');
			$country->appendChild($comment);

			$partyTaxScheme = $doc->createElement('cac:PartyTaxScheme');
			$party->appendChild($partyTaxScheme);

			$companyID = $doc->createElement('cbc:CompanyID', htmlspecialchars($data['merchant']['tin']));
			$partyTaxScheme->appendChild($companyID);

			$comment = $doc->createComment('BT-31');
			$partyTaxScheme->appendChild($comment);

			$taxScheme = $doc->createElement('cac:TaxScheme');
			$partyTaxScheme->appendChild($taxScheme);

			if (strstr($data['merchant']['tin'], 'RO')) {
				$ID = $doc->createElement('cbc:ID', 'VAT');
				$taxScheme->appendChild($ID);
			}

			$partyLegalEntity = $doc->createElement('cac:PartyLegalEntity');
			$party->appendChild($partyLegalEntity);

			$registrationName = $doc->createElement('cbc:RegistrationName', htmlspecialchars(Inflector::transliterate($data['merchant']['name'])));
			$partyLegalEntity->appendChild($registrationName);

			$comment = $doc->createComment('BT-27');
			$partyLegalEntity->appendChild($comment);

			if (!empty($data['merchant']['registration_number'])) {
				$companyID = $doc->createElement('cbc:CompanyID', htmlspecialchars($data['merchant']['registration_number']));
				$partyLegalEntity->appendChild($companyID);

				$comment = $doc->createComment('BT-30');
				$partyLegalEntity->appendChild($comment);
			}

			$accountingCustomerParty = $doc->createElement('cac:AccountingCustomerParty');
			$invoice->appendChild($accountingCustomerParty);

			$comment = $doc->createComment('BG-7');
			$accountingCustomerParty->appendChild($comment);

			$party = $doc->createElement('cac:Party');
			$accountingCustomerParty->appendChild($party);

			$comment = $doc->createComment('BT-49');
			$party->appendChild($comment);

			$postalAddress = $doc->createElement('cac:PostalAddress');
			$party->appendChild($postalAddress);

			$comment = $doc->createComment('BG-8');
			$postalAddress->appendChild($comment);

			$streetName = $doc->createElement('cbc:StreetName', htmlspecialchars(Inflector::transliterate($data['client']['address'])));
			$postalAddress->appendChild($streetName);

			$comment = $doc->createComment('BT-50');
			$postalAddress->appendChild($comment);

			$cityName = $doc->createElement('cbc:CityName', htmlspecialchars(Inflector::transliterate($data['client']['locality'])));
			$postalAddress->appendChild($cityName);

			$comment = $doc->createComment('BT-52');
			$postalAddress->appendChild($comment);

			$countrySubentity = $doc->createElement('cbc:CountrySubentity', htmlspecialchars(implode('-', [$data['client']['country_code'], $data['client']['county_code']])));
			$postalAddress->appendChild($countrySubentity);

			$comment = $doc->createComment('BT-54');
			$postalAddress->appendChild($comment);

			$country = $doc->createElement('cac:Country');
			$postalAddress->appendChild($country);

			$identificationCode = $doc->createElement('cbc:IdentificationCode', htmlspecialchars($data['client']['country_code']));
			$country->appendChild($identificationCode);

			$comment = $doc->createComment('BT-55');
			$country->appendChild($comment);

			$partyTaxScheme = $doc->createElement('cac:PartyTaxScheme');
			$party->appendChild($partyTaxScheme);

			$companyID = $doc->createElement('cbc:CompanyID', htmlspecialchars($data['client']['tin']));
			$partyTaxScheme->appendChild($companyID);

			$comment = $doc->createComment('BT-48');
			$partyTaxScheme->appendChild($comment);

			$taxScheme = $doc->createElement('cac:TaxScheme');
			$partyTaxScheme->appendChild($taxScheme);

			if (strstr($data['client']['tin'], 'RO') && !empty($vatRate)) {
				$ID = $doc->createElement('cbc:ID', 'VAT');
				$taxScheme->appendChild($ID);
			}

			$partyLegalEntity = $doc->createElement('cac:PartyLegalEntity');
			$party->appendChild($partyLegalEntity);

			$registrationName = $doc->createElement('cbc:RegistrationName', htmlspecialchars(Inflector::transliterate($data['client']['name'])));
			$partyLegalEntity->appendChild($registrationName);

			$comment = $doc->createComment('BT-44');
			$partyLegalEntity->appendChild($comment);

			if (!empty($data['client']['registration_number'])) {
				$companyID = $doc->createElement('cbc:CompanyID', htmlspecialchars($data['client']['registration_number']));
				$partyLegalEntity->appendChild($companyID);

				$comment = $doc->createComment('BT-47');
				$partyLegalEntity->appendChild($comment);
			}

			$taxTotal = $doc->createElement('cac:TaxTotal');
			$invoice->appendChild($taxTotal);

			$taxAmount = $doc->createElement('cbc:TaxAmount', NumberHelper::formatNumber($data['vatAmount']));
			$taxAmount->setAttribute('currencyID', htmlspecialchars($data['currency']));
			$taxTotal->appendChild($taxAmount);

			$comment = $doc->createComment('BT-110');
			$taxTotal->appendChild($comment);

			$taxSubtotal = $doc->createElement('cac:TaxSubtotal');
			$taxTotal->appendChild($taxSubtotal);

			$comment = $doc->createComment('BG-23');
			$taxSubtotal->appendChild($comment);

			$taxableAmount = $doc->createElement('cbc:TaxableAmount', NumberHelper::formatNumber($data['subtotalAmount']));
			$taxableAmount->setAttribute('currencyID', htmlspecialchars($data['currency']));
			$taxSubtotal->appendChild($taxableAmount);

			$comment = $doc->createComment('BT-116');
			$taxSubtotal->appendChild($comment);

			$taxAmount = $doc->createElement('cbc:TaxAmount', NumberHelper::formatNumber($data['vatAmount']));
			$taxAmount->setAttribute('currencyID', htmlspecialchars($data['currency']));
			$taxSubtotal->appendChild($taxAmount);

			$comment = $doc->createComment('BT-117');
			$taxSubtotal->appendChild($comment);

			$taxCategory = $doc->createElement('cac:TaxCategory');
			$taxSubtotal->appendChild($taxCategory);

			if (!empty($data['vatRate'])) {
				foreach ($data['vatRate'] as $vatRate => $items) {
					if (!empty((float)$vatRate)) {
						$taxCategoryID = $doc->createElement('cbc:ID', 'S');
						$taxCategory->appendChild($taxCategoryID);

						$comment = $doc->createComment('BT-118');
						$taxCategory->appendChild($comment);

						$taxPercent = $doc->createElement('cbc:Percent', NumberHelper::formatNumber($vatRate));
						$taxCategory->appendChild($taxPercent);

						$comment = $doc->createComment('BT-119');
						$taxCategory->appendChild($comment);
					} else {
						$taxCategoryID = $doc->createElement('cbc:ID', 'O');
						$taxCategory->appendChild($taxCategoryID);

						$comment = $doc->createComment('BT-118');
						$taxCategory->appendChild($comment);

						$taxExemptionReasonCode = $doc->createElement('cbc:TaxExemptionReasonCode', 'VATEX-EU-O');
						$taxCategory->appendChild($taxExemptionReasonCode);

						$comment = $doc->createComment('BT-121');
						$taxCategory->appendChild($comment);

						$taxExemptionReason = $doc->createElement('cbc:TaxExemptionReason', htmlspecialchars(Inflector::transliterate('Regim special de scutire pentru intreprinderile mici')));
						$taxCategory->appendChild($taxExemptionReason);

						$comment = $doc->createComment('BT-120');
						$taxCategory->appendChild($comment);
					}
				}
			}

			$taxScheme = $doc->createElement('cac:TaxScheme');
			$taxCategory->appendChild($taxScheme);

			$taxSchemeID = $doc->createElement('cbc:ID', 'VAT');
			$taxScheme->appendChild($taxSchemeID);

			$legalMonetaryTotal = $doc->createElement('cac:LegalMonetaryTotal');
			$invoice->appendChild($legalMonetaryTotal);

			$comment = $doc->createComment('BG-22');
			$legalMonetaryTotal->appendChild($comment);

			$lineExtensionAmount = $doc->createElement('cbc:LineExtensionAmount', NumberHelper::formatNumber($data['subtotalAmount']));
			$lineExtensionAmount->setAttribute('currencyID', htmlspecialchars($data['currency']));
			$legalMonetaryTotal->appendChild($lineExtensionAmount);

			$comment = $doc->createComment('BT-106');
			$legalMonetaryTotal->appendChild($comment);

			$taxExclusiveAmount = $doc->createElement('cbc:TaxExclusiveAmount', NumberHelper::formatNumber($data['subtotalAmount']));
			$taxExclusiveAmount->setAttribute('currencyID', htmlspecialchars($data['currency']));
			$legalMonetaryTotal->appendChild($taxExclusiveAmount);

			$comment = $doc->createComment('BT-109');
			$legalMonetaryTotal->appendChild($comment);

			$taxInclusiveAmount = $doc->createElement('cbc:TaxInclusiveAmount', NumberHelper::formatNumber($data['totalAmount']));
			$taxInclusiveAmount->setAttribute('currencyID', htmlspecialchars($data['currency']));
			$legalMonetaryTotal->appendChild($taxInclusiveAmount);

			$comment = $doc->createComment('BT-112');
			$legalMonetaryTotal->appendChild($comment);

			$prepaidAmount = $doc->createElement('cbc:PrepaidAmount', (empty($data['due_date']) ? NumberHelper::formatNumber(($data['totalAmount'] + $data['rounding_amount'])) : '0.00'));
			$prepaidAmount->setAttribute('currencyID', htmlspecialchars($data['currency']));
			$legalMonetaryTotal->appendChild($prepaidAmount);

			$comment = $doc->createComment('BT-113');
			$legalMonetaryTotal->appendChild($comment);

			if (!empty($data['rounding_amount'])) {
				$payableRoundingAmount = $doc->createElement('cbc:PayableRoundingAmount', NumberHelper::formatNumber($data['rounding_amount']));
				$payableRoundingAmount->setAttribute('currencyID', htmlspecialchars($data['currency']));
				$legalMonetaryTotal->appendChild($payableRoundingAmount);

				$comment = $doc->createComment('BT-114');
				$legalMonetaryTotal->appendChild($comment);
			}

			$payableAmount = $doc->createElement('cbc:PayableAmount', (!empty($data['due_date']) ? NumberHelper::formatNumber(($data['totalAmount'] + $data['rounding_amount'])) : '0.00'));
			$payableAmount->setAttribute('currencyID', htmlspecialchars($data['currency']));
			$legalMonetaryTotal->appendChild($payableAmount);

			$comment = $doc->createComment('BT-115');
			$legalMonetaryTotal->appendChild($comment);

			if (!empty($data['items'])) {
				$i = 1;
				foreach ($data['items'] as $vatRate => $items) {
					if (!empty($items)) {
						foreach ($items as $item) {
							$cacInvoiceLine = $doc->createElement('cac:InvoiceLine');
							$invoice->appendChild($cacInvoiceLine);

							$cbcInvoicedID = $doc->createElement('cbc:ID', htmlspecialchars($i));
							$cacInvoiceLine->appendChild($cbcInvoicedID);

							$comment = $doc->createComment('BT-126');
							$cacInvoiceLine->appendChild($comment);

							$cbcInvoicedQuantity = $doc->createElement('cbc:InvoicedQuantity', NumberHelper::formatNumber($item['quantity'], 4));
							$cbcInvoicedQuantity->setAttribute('unitCode', $item['unit_code']);
							$cacInvoiceLine->appendChild($cbcInvoicedQuantity);

							$comment = $doc->createComment('BT-129 BT-130');
							$cacInvoiceLine->appendChild($comment);

							$cbcLineExtensionAmount = $doc->createElement('cbc:LineExtensionAmount', NumberHelper::formatNumber($item['subtotal']));
							$cbcLineExtensionAmount->setAttribute('currencyID', htmlspecialchars($data['currency']));
							$cacInvoiceLine->appendChild($cbcLineExtensionAmount);

							$comment = $doc->createComment('BT-131');
							$cacInvoiceLine->appendChild($comment);

							$cacItem = $doc->createElement('cac:Item');
							$cacInvoiceLine->appendChild($cacItem);

							$comment = $doc->createComment('BG-31');
							$cacItem->appendChild($comment);

							if (!empty($item['description'])) {
								$cbcItemDescription = $doc->createElement('cbc:Description', htmlspecialchars(Inflector::transliterate($item['description'])));
								$cacItem->appendChild($cbcItemDescription);
							}

							$cbcItemName = $doc->createElement('cbc:Name', htmlspecialchars(Inflector::transliterate($item['name'])));
							$cacItem->appendChild($cbcItemName);

							$comment = $doc->createComment('BT-153');
							$cacItem->appendChild($comment);

							$cacClassifiedTaxCategory = $doc->createElement('cac:ClassifiedTaxCategory');
							$cacItem->appendChild($cacClassifiedTaxCategory);

							if (!empty((float)$vatRate)) {
								$cbcTaxCategoryID = $doc->createElement('cbc:ID', 'S');
								$cacClassifiedTaxCategory->appendChild($cbcTaxCategoryID);

								$comment = $doc->createComment('BT-151');
								$cacClassifiedTaxCategory->appendChild($comment);

								$cbcTaxCategoryPercent = $doc->createElement('cbc:Percent', NumberHelper::formatNumber($vatRate));
								$cacClassifiedTaxCategory->appendChild($cbcTaxCategoryPercent);

								$comment = $doc->createComment('BT-152');
								$cacClassifiedTaxCategory->appendChild($comment);

							} else {
								$cbcTaxCategoryID = $doc->createElement('cbc:ID', 'O');
								$cacClassifiedTaxCategory->appendChild($cbcTaxCategoryID);

								$comment = $doc->createComment('BT-151');
								$cacClassifiedTaxCategory->appendChild($comment);
							}

							$cacTaxScheme = $doc->createElement('cac:TaxScheme');
							$cacClassifiedTaxCategory->appendChild($cacTaxScheme);

							$cbcTaxSchemeID = $doc->createElement('cbc:ID', 'VAT');
							$cacTaxScheme->appendChild($cbcTaxSchemeID);

							$cacPrice = $doc->createElement('cac:Price');
							$cacInvoiceLine->appendChild($cacPrice);

							$comment = $doc->createComment('BG-29');
							$cacPrice->appendChild($comment);

							$cbcPriceAmount = $doc->createElement('cbc:PriceAmount', NumberHelper::formatNumber($item['unit_price']));
							$cbcPriceAmount->setAttribute('currencyID', htmlspecialchars($data['currency']));
							$cacPrice->appendChild($cbcPriceAmount);

							$comment = $doc->createComment('BT-146');
							$cacPrice->appendChild($comment);

							$cbcBaseQuantity = $doc->createElement('cbc:BaseQuantity', NumberHelper::formatNumber($item['quantity'], 4));
							$cbcBaseQuantity->setAttribute('unitCode', $item['unit_code']);
							$cacPrice->appendChild($cbcBaseQuantity);

							$comment = $doc->createComment('BT-149 BT-150');
							$cacPrice->appendChild($comment);
							$i++;
						}
					}
				}
			}

			// Save the XML document
			$doc->normalizeDocument();
//			echo $doc->saveXML();

			$dirPath = Yii::getAlias("@uploads/e-invoice");
			$fileName = "{$model->getDocumentSeriesNumber()}.xml";
			$filePath = "{$dirPath}/{$fileName}";
			FileHelper::createDirectory($dirPath);
			file_put_contents($filePath, $doc->saveXML());

			$url = "https://webservicesp.anaf.ro/prod/FCTEL/rest/validare/FACT1";
			$xmlContent = file_get_contents($filePath);
			$curl = curl_init();
			curl_setopt_array($curl, [
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $xmlContent,
				CURLOPT_HTTPHEADER => [
					'Content-Type: text/plain',
				],
			]);
			$response = curl_exec($curl);
			if (curl_errno($curl)) {
				$errors[] = curl_error($curl);
			}
			curl_close($curl);
			$result = (json_decode($response, true));

			if ($result['stare'] == 'nok') {
				$errors[] = $result['Messages'][0]['message'];
			} else {
				$this->e_invoice_error = null;
				$this->save(false);
			}

			if (!empty($errors)) {
				throw new \Exception();
			}

			return [
				'tin' => $data['merchant']['tin'],
				'fileName' => $fileName,
				'filePath' => $filePath,
			];
		} catch(\Exception $e) {
			if (!empty($errors)) {
				$this->e_invoice_error = Yii::t('label', 'e-Invoice XML is not valid') . ": \n" . implode("\n", $errors);
				$this->save(false);
				foreach ($errors as $error) {
					if (!empty($error)) {
						$this->addError('', $error);
					}
				}
			}
			return [];
		}
	}

	protected function upload($filePath = null, $tin = null)
	{
		try {
			$errors = [];
			$currentDate = date('Y-m-d H:i:s');
			$integration = Integration::find()
				->where([
					'type' => Integration::TYPE_SPV,
					'status' => Integration::STATUS_ACTIVE,
					'deleted' => Integration::NO,
				])
				->andWhere(new Expression("TIMESTAMPDIFF(SECOND, '{$currentDate}', [[expire_at]]) > 0"))
				->one();
			if (!empty($integration)) {
				$tin = preg_replace('/\D/', '', $tin);
				$token = json_decode($integration->data, true)['access_token'];
				$environment = ($integration->sandbox ? 'test' : 'prod');
				$url = "https://api.anaf.ro/{$environment}/FCTEL/rest/upload?standard=UBL&cif=" . $tin;

				$xmlContent = file_get_contents($filePath);

				$curl = curl_init();
				curl_setopt_array($curl, [
					CURLOPT_URL => $url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_TIMEOUT => 0,
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => $xmlContent,
					CURLOPT_HTTPHEADER => [
						'Content-Type: application/xml',
						'Authorization: Bearer '. $token
					],
				]);

				$response = curl_exec($curl);

				if (curl_errno($curl)) {
					$errors[] = curl_error($curl);
				}

				curl_close($curl);

				$xml = new \SimpleXMLElement($response);
				$result = ((array)$xml);

				if (!empty($result['@attributes']['index_incarcare'])) {
					$date = \DateTime::createFromFormat('YmdHi', $result['@attributes']['dateResponse']);
					$this->e_invoice_status = Invoice::E_STATUS_SENT;
					$this->e_invoice_upload_id = $result['@attributes']['index_incarcare'];
					$this->e_invoice_sent_at = $date->format('Y-m-d H:i');
					$this->save(false);
				} else {
					if (!empty($result['Errors']['@attributes']['errorMessage'])) {
						$errors[] = $result['Errors']['@attributes']['errorMessage'];
					}
				}
			}
			if (!empty($errors)) {
				throw new \Exception();
			}
			return true;
		} catch(\Exception $e) {
			if (!empty($errors)) {
				foreach ($errors as $error) {
					if (!empty($error)) {
						$this->addError('', $error);
					}
				}
			}
			return false;
		}
	}

	protected function download($downloadID = null)
	{
		try {
			$errors = [];
			$currentDate = date('Y-m-d H:i:s');
			$integration = Integration::find()
				->where([
					'type' => Integration::TYPE_SPV,
					'status' => Integration::STATUS_ACTIVE,
					'deleted' => Integration::NO,
				])
				->andWhere(new Expression("TIMESTAMPDIFF(SECOND, '{$currentDate}', [[expire_at]]) > 0"))
				->one();
			if (!empty($integration)) {
				$token = json_decode($integration->data, true)['access_token'];
				$environment = ($integration->sandbox ? 'test' : 'prod');
				$url = "https://api.anaf.ro/{$environment}/FCTEL/rest/descarcare?id=" . $downloadID;

				$dirPath = Yii::getAlias("@uploads/e-invoice");
				$fileName = "{$this->getDocumentSeriesNumber()}-{$downloadID}.zip";
				$filePath = "{$dirPath}/{$fileName}";

				$fp = fopen($filePath, 'w+');
				$curl = curl_init();
				curl_setopt_array($curl, [
					CURLOPT_URL => $url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_BINARYTRANSFER => true,
					CURLOPT_ENCODING => '',
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 0,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => 'GET',
					CURLOPT_FILE => $fp,
					CURLOPT_HTTPHEADER => [
						'Authorization: Bearer '.$token
					],
				]);

				$response = curl_exec($curl);
				curl_close($curl);
				fclose($fp);

				if (filesize($filePath) > 0) {
					Yii::$app->response->sendFile($filePath, $fileName);
				} else {
					$errors[] = Yii::t('backend', 'File not found');
				}
			}

			if (!empty($errors)) {
				throw new \Exception();
			}
			return true;
		} catch(\Exception $e) {
			if (!empty($errors)) {
				foreach ($errors as $error) {
					if (!empty($error)) {
						$this->addError('', $error);
					}
				}
			}
			return false;
		}
	}

	protected function verify($uploadID = null)
	{
		try {
			$errors = [];
			$currentDate = date('Y-m-d H:i:s');
			$integration = Integration::find()
				->where([
					'type' => Integration::TYPE_SPV,
					'status' => Integration::STATUS_ACTIVE,
					'deleted' => Integration::NO,
				])
				->andWhere(new Expression("TIMESTAMPDIFF(SECOND, '{$currentDate}', [[expire_at]]) > 0"))
				->one();
			if (!empty($integration)) {
				$token = json_decode($integration->data, true)['access_token'];
				$environment = ($integration->sandbox ? 'test' : 'prod');
				$url = "https://api.anaf.ro/{$environment}/FCTEL/rest/stareMesaj?id_incarcare=" . $uploadID;

				$curl = curl_init();
				curl_setopt_array($curl, [
					CURLOPT_URL => $url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => '',
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 0,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => 'GET',
					CURLOPT_HTTPHEADER => [
						'Authorization: Bearer '.$token
					],
				]);

				$response = curl_exec($curl);

				if (curl_errno($curl)) {
					$errors[] = curl_error($curl);
				}

				curl_close($curl);

				$xml = new \SimpleXMLElement($response);
				$result = ((array)$xml);

				if ($result['@attributes']['stare'] == 'in prelucrare') {
					$this->e_invoice_status = Invoice::E_STATUS_PENDING;
					$this->save(false);
				} else if ($result['@attributes']['stare'] == 'ok') {
					$this->e_invoice_status = Invoice::E_STATUS_PROCESSED;
					$this->e_invoice_download_id = $result['@attributes']['id_descarcare'];
					$this->e_invoice_error = null;
					$this->save(false);
				} else if ($result['@attributes']['stare'] == 'nok') {
					$xml = ((array)$xml)['Errors'];
					$error = ((array)$xml);
					$errors[] = $error['@attributes']['errorMessage'];
					$this->e_invoice_status = Invoice::E_STATUS_UNPROCESSED;
					$this->e_invoice_error = $error['@attributes']['errorMessage'];
					$this->save(false);
				} else if ($result['@attributes']['stare'] == 'XML cu erori nepreluat de sistem') {
					$xml = ((array)$xml)['Errors'];
					$error = ((array)$xml);
					$errors[] = $error['@attributes']['errorMessage'];
					$this->e_invoice_status = Invoice::E_STATUS_REJECTED;
					$this->e_invoice_error = $error['@attributes']['errorMessage'];
					$this->save(false);
				} else {
					if (!empty($result['Errors'])) {
						$xml = ((array)$xml)['Errors'];
						$error = ((array)$xml);
						$errors[] = $error['@attributes']['errorMessage'];
						$this->e_invoice_status = Invoice::E_STATUS_REJECTED;
						$this->e_invoice_error = $error['@attributes']['errorMessage'];
						$this->save(false);
					}
				}
			}
			if (!empty($errors)) {
				throw new \Exception();
			}
			return true;
		} catch(\Exception $e) {
			if (!empty($errors)) {
				foreach ($errors as $error) {
					if (!empty($error)) {
						$this->addError('', $error);
					}
				}
			}
			return false;
		}
	}

	/**
	 * Exports data to a specific file format.
	 *
	 * @return bool
	 * @throws \yii\base\InvalidConfigException
	 * @throws \yii\db\Exception
	 */
	protected function export()
	{
		return $this->exportToXML();
	}

	/**
	 * Saves the model.
	 *
	 * @return bool|\yii\db\ActiveRecord
	 */
	public function saveModel($action = null)
	{
		try {
			switch ($action) {
				case 'generate':
					if (!$this->validate()) {
						throw new \Exception();
					}
					if (!($result = $this->export())) {
						throw new \Exception();
					}
					Yii::$app->response->sendFile($result['filePath'], $result['fileName']);
					break;
				case 'upload':
					if (!$this->validate()) {
						throw new \Exception();
					}
					if (!($data = $this->export())) {
						throw new \Exception();
					}
					if (!$result = $this->upload($data['filePath'], $data['tin'])) {
						throw new \Exception();
					}
					break;
				case 'download':
					if (!$result = $this->download($this->e_invoice_download_id)) {
						throw new \Exception();
					}
					break;
				case 'verify':
					if (!$result = $this->verify($this->e_invoice_upload_id)) {
						throw new \Exception();
					}
					break;
				default:break;
			}
			return $result;
		} catch(\Exception $e) {
			return false;
		}
	}
}
