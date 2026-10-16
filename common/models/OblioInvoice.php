<?php

namespace common\models;

use Yii;
use yii\helpers\Json;
use yii\caching\CacheInterface;

/**
 * OblioInvoice - single facade for Oblio API + local Invoice mapping.
 *
 * Requirements:
 * - Yii::$app->settings->get('oblioClientId', 'general')
 * - Yii::$app->settings->get('oblioClientSecret', 'general')
 *
 * Notes:
 * - Uses invoice->getUnserializedValue('details') which must contain:
 *   - merchant: ['tin' => 'RO...']  (seller CIF in Oblio)
 *   - client:   (buyer info; B2B if client['tin'] exists)
 *   - transaction_id: for collect.documentNumber
 *
 * - Uses local scopes already present in the project:
 *   $invoice->getItems()->active(true)->deleted(false)
 *
 * - Does NOT require changes in Invoice/Item models.
 */
class OblioInvoice
{
    const API_BASE_URL = 'https://www.oblio.eu/api/';

    // Auth
    const TOKEN_ENDPOINT = 'authorize/token';
    const TOKEN_CACHE_KEY_PREFIX = 'oblio_access_token:';

    // Rate limits (doc)
    const RATE_LIMIT_DOCUMENT_GENERATION = 30; // /100 sec
    const RATE_LIMIT_OTHER_REQUESTS = 30;      // /10 sec

    /**
     * ===========================
     *  LOCAL INVOICE -> OBLIO
     * ===========================
     */

    /**
     * Create Oblio invoice from local Invoice model (and store external_id).
     *
     * Options:
     * - sellerCif: override seller CIF (defaults to details.merchant.tin)
     * - seriesName: override series (defaults to $invoice->document_series)
     * - idempotencyKey: override idempotency key (defaults local_invoice_{id})
     * - mentions: optional mentions
     * - sendToSpv: bool (default false) - if true, call sendInvoiceToSpv after create
     */
    public static function createInvoiceFromLocalInvoice(Invoice $invoice, array $options = []): array
    {
        // If invoice already sent to Oblio, return the existing invoice URL
        if (!empty($invoice->external_id)) {
            $details = $invoice->getUnserializedValue('details') ?: [];

            // Get series and number from stored details (saved when invoice was created)
            $seriesName = $details['oblio_series'] ?? null;
            $number = $details['oblio_number'] ?? null;

            if (empty($seriesName) || empty($number)) {
                // Fallback: try to use invoice's own document series and number
                $seriesName = $invoice->document_series;
                $number = $invoice->document_number;
            }

            if (empty($seriesName) || empty($number)) {
                throw new \Exception('Cannot retrieve Oblio invoice: missing series or number in invoice details.');
            }

            // Get invoice details from Oblio to match exact response structure
            $merchant = $details['merchant'] ?? [];
            $sellerCif = $options['sellerCif'] ?? ($merchant['tin'] ?? null);

            if (empty($sellerCif)) {
                throw new \Exception('Cannot retrieve Oblio invoice URL: missing seller CIF.');
            }

            try {
                // Get full invoice data from Oblio to match exact response structure
                $invoiceData = self::viewInvoice($sellerCif, $seriesName, $number);

                // Use stored URL if available, otherwise use from API response
                $invoiceUrl = $details['oblio_url'] ?? ($invoiceData['data']['link'] ?? null);

                // Store the URL, series, and number in invoice details for future use if not already stored
                if ($invoiceUrl && empty($details['oblio_url'])) {
                    $details['oblio_url'] = $invoiceUrl;
                    $details['oblio_series'] = $seriesName;
                    $details['oblio_number'] = $number;
                    $invoice->setSerializedValue('details', $details);
                    $invoice->save(false);
                }

                // Return response in exact same structure as successful createInvoice
                return $invoiceData;
            } catch (\Exception $e) {
                throw new \Exception('Invoice already sent to Oblio but could not retrieve URL: ' . $e->getMessage());
            }
        }

        $payload = self::buildInvoicePayloadFromLocalInvoice($invoice, $options);

        // Oblio expects /docs/invoice for create
        try {
            $resp = self::request('/docs/invoice', 'POST', $payload, true);
        } catch (\Exception $e) {
            // If error is related to series, fetch and display available series
            $errorMessage = $e->getMessage();
            if (stripos($errorMessage, 'seria') !== false ||
                stripos($errorMessage, 'series') !== false ||
                stripos($errorMessage, 'Selecteaza') !== false) {
                $availableSeries = self::getAvailableSeriesForError($payload['cif']);
                if (!empty($availableSeries)) {
                    throw new \Exception($errorMessage . "\n\nAvailable series in Oblio for CIF {$payload['cif']}:\n" . $availableSeries);
                }
            }
            throw $e;
        }

        // Save external reference and URL
        if (!empty($resp['data']['seriesName']) && !empty($resp['data']['number'])) {
            // Store Oblio ID (could be id field if available, otherwise use series+number as identifier)
            $oblioId = $resp['data']['id'] ?? ($resp['data']['seriesName'] . ' ' . $resp['data']['number']);
            $invoice->external_id = $oblioId;

            // Store the URL, series, and number in details for future retrieval
            $details = $invoice->getUnserializedValue('details') ?: [];
            if (isset($resp['data']['link'])) {
                $details['oblio_url'] = $resp['data']['link'];
            }
            $details['oblio_series'] = $resp['data']['seriesName'];
            $details['oblio_number'] = $resp['data']['number'];
            $invoice->setSerializedValue('details', $details);
            $invoice->save(false);
        }

        // Optionally send to SPV
        if (!empty($options['sendToSpv']) && !empty($resp['data']['seriesName']) && !empty($resp['data']['number'])) {
            self::sendInvoiceToSpv(
                $payload['cif'],
                $resp['data']['seriesName'],
                (int)$resp['data']['number']
            );
        }

        return $resp;
    }

    /**
     * Create Oblio proforma from local Invoice model (and store external_id).
     *
     * Options:
     * - sellerCif: override seller CIF (defaults to details.merchant.tin)
     * - seriesName: override series (defaults to $invoice->document_series)
     * - idempotencyKey: override idempotency key (defaults local_invoice_{id})
     * - mentions: optional mentions
     */
    public static function createProformaFromLocalInvoice(Invoice $invoice, array $options = []): array
    {
        if (!empty($invoice->external_id)) {
            return ['data' => ['id' => $invoice->external_id]];
        }

        if (!array_key_exists('disableAutoSeries', $options)) {
            $options['disableAutoSeries'] = 1;
        }
        if (!array_key_exists('number', $options)) {
            $options['number'] = $invoice->document_number;
        }

        $payload = self::buildInvoicePayloadFromLocalInvoice($invoice, $options);

        $resp = self::createProforma($payload);

        if (!empty($resp['data']['seriesName']) && !empty($resp['data']['number'])) {
            $oblioId = $resp['data']['id'] ?? ($resp['data']['seriesName'] . ' ' . $resp['data']['number']);
            $invoice->external_id = $oblioId;

            $details = $invoice->getUnserializedValue('details') ?: [];
            if (isset($resp['data']['link'])) {
                $details['oblio_url'] = $resp['data']['link'];
            }
            $details['oblio_series'] = $resp['data']['seriesName'];
            $details['oblio_number'] = $resp['data']['number'];
            $invoice->setSerializedValue('details', $details);
            $invoice->save(false);
        }

        return $resp;
    }

    /**
     * Build complete Oblio payload using local invoice details + items.
     */
    public static function buildInvoicePayloadFromLocalInvoice(Invoice $invoice, array $options = []): array
    {
        $details = $invoice->getUnserializedValue('details') ?: [];
        $merchant = $details['merchant'] ?? [];

        $sellerCif = $options['sellerCif'] ?? ($merchant['tin'] ?? null);
        if (empty($sellerCif)) {
            throw new \Exception('Missing seller CIF for Oblio (details.merchant.tin or options[sellerCif]).');
        }

        // Validate document series and number are available
        if (empty($invoice->document_series)) {
            throw new \Exception('Invoice document series is required for Oblio.');
        }
        if (empty($invoice->document_number)) {
            throw new \Exception('Invoice document number is required for Oblio.');
        }

        $seriesName = $options['seriesName'] ?? $invoice->document_series;
        if (empty($seriesName)) {
            throw new \Exception('Document series name is required for Oblio. Please ensure the invoice has a valid document series or provide it in options.');
        }

        $products = self::buildProductsFromLocalInvoice($invoice);

        $disableAutoSeries = (int)($options['disableAutoSeries'] ?? 0);
        $payload = [
            'cif' => $sellerCif,
            'client' => self::buildClientFromLocalInvoiceDetails($invoice),
            'issueDate' => date('Y-m-d', strtotime($invoice->issued_at ?: 'now')),
            'dueDate' => date('Y-m-d', strtotime($invoice->due_at ?: ($invoice->issued_at ?: 'now'))),
            'seriesName' => $seriesName,
            'disableAutoSeries' => $disableAutoSeries,
            'currency' => $invoice->currency ?: 'RON',
            'exchangeRate' => (float)($invoice->exchange_rate ?: 1),
            'products' => $products,
            // The canonical UUID string — $invoice->id is the raw BINARY(16) value, and
            // its bytes are not valid UTF-8, so json_encode fails on the whole payload.
            'idempotencyKey' => $options['idempotencyKey'] ?? ('local_invoice_' . $invoice->uuid),
            'mentions' => $options['mentions'] ?? '',
        ];

        if ($disableAutoSeries === 1) {
            $number = $options['number'] ?? $invoice->document_number;
            if (empty($number)) {
                throw new \Exception('Invoice number is required when disableAutoSeries is enabled for Oblio.');
            }
            $payload['number'] = (int)$number;
        }

        // If paid -> add collect
        if ((int)$invoice->status === Invoice::STATUS_PAID) {
            $payload['collect'] = self::buildCollectFromLocalInvoice($invoice);
        }

        return $payload;
    }

    /**
     * Build Oblio client from local Invoice serialized details.
     * B2B if client.tin exists, else B2C.
     */
    public static function buildClientFromLocalInvoiceDetails(Invoice $invoice): array
    {
        $details = $invoice->getUnserializedValue('details') ?: [];
        $client = $details['client'] ?? [];

        // B2B
        if (!empty($client['tin'])) {
            return [
                'cif' => $client['tin'],
                'name' => $client['name'] ?? 'Client',
                'rc' => $client['registration_number'] ?? '',
                'address' => $client['address'] ?? '',
                'state' => $client['county'] ?? ($client['state'] ?? ''),
                'city' => $client['locality'] ?? ($client['city'] ?? ''),
                'country' => $client['country'] ?? '',
                'iban' => $client['iban'] ?? '',
                'bank' => $client['bank'] ?? '',
                'email' => $client['email'] ?? '',
                'phone' => $client['phone'] ?? '',
                'contact' => $client['contact'] ?? '',
                // If you have a reliable flag, set it; otherwise keep 0
                'vatPayer' => isset($client['vatPayer']) ? (int)$client['vatPayer'] : 0,
                'save' => 0,
                'autocomplete' => 0,
            ];
        }

        // B2C
        return [
            'name' => $client['name'] ?? 'Client',
            'address' => $client['address'] ?? '',
            'state' => $client['county'] ?? ($client['state'] ?? ''),
            'city' => $client['locality'] ?? ($client['city'] ?? ''),
            'country' => $client['country'] ?? '',
            'email' => $client['email'] ?? '',
            'phone' => $client['phone'] ?? '',
            'vatPayer' => 0,
            'save' => 0,
            'autocomplete' => 0,
        ];
    }

    /**
     * Build products array from local invoice items.
     * The purchasable unit in this project is a subscription — the product name is built from the
     * subscription's package plus the billed period stored in the item details.
     */
    public static function buildProductsFromLocalInvoice(Invoice $invoice): array
    {
        $items = $invoice->getItems()->active(true)->deleted(false)->all();
        if (!$items) {
            throw new \Exception('Cannot create Oblio invoice: local invoice has no items.');
        }

        $products = [];

        foreach ($items as $item) {
            $name = 'Abonament';
            // Append the package name and the billed period when available.
            $suffix = [];
            if ((int)$item->type === Item::TYPE_SUBSCRIPTION && $item->subscription !== null) {
                $subscription = $item->subscription;
                if ($subscription->package !== null && ($packageTranslation = $subscription->package->getTranslation()) !== null
                    && trim((string)$packageTranslation->name) !== '') {
                    $suffix[] = trim((string)$packageTranslation->name);
                }
            }
            $itemDetails = $item->getUnserializedValue('details') ?: [];
            if (!empty($itemDetails['start_at']) || !empty($itemDetails['end_at'])) {
                $start = !empty($itemDetails['start_at']) ? date('Y-m-d', strtotime($itemDetails['start_at'])) : '';
                $end = !empty($itemDetails['end_at']) ? date('Y-m-d', strtotime($itemDetails['end_at'])) : '';
                $range = trim($start . ' - ' . $end, ' -');
                if ($range !== '') {
                    $suffix[] = $range;
                }
            }
            if ($suffix) {
                $name .= ' (' . implode(', ', $suffix) . ')';
            }

            $products[] = [
                'name' => $name,
                'code' => '',
                'description' => '',
                'price' => $invoice->type == Invoice::TYPE_RECTIFY ? (-1)*(float)$item->price : (float)$item->price,
                'measuringUnit' => 'buc',
                'currency' => $item->currency ?: ($invoice->currency ?: 'RON'),
                'exchangeRate' => (float)($item->exchange_rate ?: ($invoice->exchange_rate ?: 1)),
                'vatName' => 'Normala',
                'vatPercentage' => (float)($invoice->vat ?: 0),
                'vatIncluded' => 1,
                'quantity' => max(1, (int)$item->quantity),
                'productType' => 'Serviciu',
            ];
        }

        return $products;
    }

    /**
     * Build collect payload if invoice is paid.
     * Uses invoice document series and number only.
     */
    public static function buildCollectFromLocalInvoice(Invoice $invoice): array
    {
        // Map your payment processor/method if you want; default Card is safe
        $type = 'Card';

        // Use invoice document series and number only
        try {
            $docNo = $invoice->document_number;
            if (empty($docNo)) {
                throw new \Exception('Invoice document number is not available.');
            }
        } catch (\Exception $e) {
            throw new \Exception('Cannot build collect: invoice document number id not eligible. ' . $e->getMessage());
        }

        return [
            'type' => $type,
            'documentNumber' => $docNo,
            'value' => (float)$invoice->amount,
            'issueDate' => date('Y-m-d', strtotime($invoice->paid_at ?: ($invoice->issued_at ?: 'now'))),
            'mentions' => '',
        ];
    }

    /**
     * ===========================
     *  NOMENCLATURE
     * ===========================
     */

    public static function getCompanies(): array
    {
        return self::request('/nomenclature/companies', 'GET');
    }

    public static function getVatRates(string $cif): array
    {
        return self::request('/nomenclature/vat_rates', 'GET', ['cif' => $cif], false);
    }

    public static function getClients(string $cif, array $filters = []): array
    {
        return self::request('/nomenclature/clients', 'GET', array_merge(['cif' => $cif], $filters), false);
    }

    public static function getProducts(string $cif, array $filters = []): array
    {
        return self::request('/nomenclature/products', 'GET', array_merge(['cif' => $cif], $filters), false);
    }

    public static function getSeries(string $cif): array
    {
        return self::request('/nomenclature/series', 'GET', ['cif' => $cif], false);
    }

    /**
     * Get available series formatted for error messages.
     *
     * @param string $cif
     * @return string
     */
    protected static function getAvailableSeriesForError(string $cif): string
    {
        try {
            $seriesResponse = self::getSeries($cif);
            $seriesList = [];

            if (isset($seriesResponse['data']) && is_array($seriesResponse['data'])) {
                foreach ($seriesResponse['data'] as $series) {
                    if (isset($series['name'])) {
                        $seriesList[] = '- ' . $series['name'];
                    }
                }
            }

            if (empty($seriesList)) {
                return 'No series found in Oblio for this CIF.';
            }

            return implode("\n", $seriesList);
        } catch (\Exception $e) {
            return 'Could not fetch available series from Oblio: ' . $e->getMessage();
        }
    }

    public static function getLanguages(string $cif): array
    {
        return self::request('/nomenclature/languages', 'GET', ['cif' => $cif], false);
    }

    public static function getManagement(string $cif): array
    {
        return self::request('/nomenclature/management', 'GET', ['cif' => $cif], false);
    }

    /**
     * ===========================
     *  CREATE DOCS (RAW JSON)
     * ===========================
     */

    public static function createProforma(array $payload): array
    {
        return self::request('/docs/proforma', 'POST', $payload, true);
    }

    public static function createNotice(array $payload): array
    {
        return self::request('/docs/notice', 'POST', $payload, true);
    }

    public static function createInvoice(array $payload): array
    {
        return self::request('/docs/invoice', 'POST', $payload, true);
    }

    /**
     * ===========================
     *  VIEW DOCS
     * ===========================
     */

    public static function viewInvoice(string $cif, string $seriesName, int $number): array
    {
        return self::request('/docs/invoice', 'GET', [
            'cif' => $cif,
            'seriesName' => $seriesName,
            'number' => $number,
        ], false);
    }

    public static function viewProforma(string $cif, string $seriesName, int $number): array
    {
        return self::request('/docs/proforma', 'GET', [
            'cif' => $cif,
            'seriesName' => $seriesName,
            'number' => $number,
        ], false);
    }

    public static function viewNotice(string $cif, string $seriesName, int $number): array
    {
        return self::request('/docs/notice', 'GET', [
            'cif' => $cif,
            'seriesName' => $seriesName,
            'number' => $number,
        ], false);
    }

    /**
     * ===========================
     *  COLLECT INVOICE
     * ===========================
     */

    public static function collectInvoice(string $cif, string $seriesName, int $number, array $collectData): array
    {
        // This endpoint expects form-urlencoded, including nested fields.
        // We'll send form payload (not JSON) and flatten collect.
        $payload = [
            'cif' => $cif,
            'seriesName' => $seriesName,
            'number' => $number,
        ];

        // Flatten collect[...] like Oblio examples
        foreach ($collectData as $k => $v) {
            $payload["collect[$k]"] = $v;
        }

        return self::request('/docs/invoice/collect', 'PUT', $payload, false);
    }

    /**
     * ===========================
     *  CANCEL DOCS
     * ===========================
     */

    public static function cancelInvoice(string $cif, string $seriesName, int $number): array
    {
        return self::request('/docs/invoice/cancel', 'PUT', [
            'cif' => $cif,
            'seriesName' => $seriesName,
            'number' => $number,
        ], false);
    }

    public static function cancelProforma(string $cif, string $seriesName, int $number): array
    {
        return self::request('/docs/proforma/cancel', 'PUT', [
            'cif' => $cif,
            'seriesName' => $seriesName,
            'number' => $number,
        ], false);
    }

    public static function cancelNotice(string $cif, string $seriesName, int $number): array
    {
        return self::request('/docs/notice/cancel', 'PUT', [
            'cif' => $cif,
            'seriesName' => $seriesName,
            'number' => $number,
        ], false);
    }

    /**
     * ===========================
     *  RESTORE DOCS
     * ===========================
     */

    public static function restoreInvoice(string $cif, string $seriesName, int $number): array
    {
        return self::request('/docs/invoice/restore', 'PUT', [
            'cif' => $cif,
            'seriesName' => $seriesName,
            'number' => $number,
        ], false);
    }

    public static function restoreProforma(string $cif, string $seriesName, int $number): array
    {
        return self::request('/docs/proforma/restore', 'PUT', [
            'cif' => $cif,
            'seriesName' => $seriesName,
            'number' => $number,
        ], false);
    }

    public static function restoreNotice(string $cif, string $seriesName, int $number): array
    {
        return self::request('/docs/notice/restore', 'PUT', [
            'cif' => $cif,
            'seriesName' => $seriesName,
            'number' => $number,
        ], false);
    }

    /**
     * ===========================
     *  DELETE DOCS
     * ===========================
     */

    public static function deleteInvoice(string $cif, string $seriesName, int $number, bool $deleteCollect = false, ?string $idempotencyKey = null): array
    {
        $payload = [
            'cif' => $cif,
            'seriesName' => $seriesName,
            'number' => $number,
            'deleteCollect' => $deleteCollect ? 'true' : 'false',
        ];
        if ($idempotencyKey) {
            $payload['idempotencyKey'] = $idempotencyKey;
        }
        return self::request('/docs/invoice', 'DELETE', $payload, false);
    }

    public static function deleteProforma(string $cif, string $seriesName, int $number, ?string $idempotencyKey = null): array
    {
        $payload = [
            'cif' => $cif,
            'seriesName' => $seriesName,
            'number' => $number,
        ];
        if ($idempotencyKey) {
            $payload['idempotencyKey'] = $idempotencyKey;
        }
        return self::request('/docs/proforma', 'DELETE', $payload, false);
    }

    public static function deleteNotice(string $cif, string $seriesName, int $number, ?string $idempotencyKey = null): array
    {
        $payload = [
            'cif' => $cif,
            'seriesName' => $seriesName,
            'number' => $number,
        ];
        if ($idempotencyKey) {
            $payload['idempotencyKey'] = $idempotencyKey;
        }
        return self::request('/docs/notice', 'DELETE', $payload, false);
    }

    /**
     * ===========================
     *  LIST / REPORTS
     * ===========================
     */

    public static function listInvoices(string $cif, array $filters = []): array
    {
        return self::request('/docs/invoice/list', 'GET', array_merge(['cif' => $cif], $filters), false);
    }

    /**
     * ===========================
     *  SPV / e-INVOICE
     * ===========================
     */

    public static function sendInvoiceToSpv(string $cif, string $seriesName, int $number): array
    {
        return self::request('/docs/einvoice', 'POST', [
            'cif' => $cif,
            'seriesName' => $seriesName,
            'number' => $number,
        ], false);
    }

    /**
     * Download SPV archive (binary).
     */
    public static function downloadSpvArchive(string $cif, string $seriesName, int $number): string
    {
        $token = self::getAccessToken();
        $url = self::API_BASE_URL . 'docs/einvoice?' . http_build_query([
                'cif' => $cif,
                'seriesName' => $seriesName,
                'number' => $number,
            ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
        ]);

        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \Exception('Failed to download SPV archive: ' . $err);
        }

        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http === 401) {
            self::authorize(true);
            return self::downloadSpvArchive($cif, $seriesName, $number);
        }
        if ($http !== 200) {
            throw new \Exception('Failed to download SPV archive. HTTP ' . $http);
        }

        return $resp;
    }

    /**
     * ===========================
     *  WEBHOOKS
     * ===========================
     */

    public static function createWebhook(string $cif, string $topic, string $endpoint): array
    {
        return self::request('/webhooks', 'POST', [
            'cif' => $cif,
            'topic' => $topic,
            'endpoint' => $endpoint,
        ], true);
    }

    public static function listWebhooks(): array
    {
        return self::request('/webhooks', 'GET');
    }

    public static function deleteWebhook(int $id): array
    {
        return self::request('/webhooks/' . $id, 'DELETE');
    }

    /**
     * ===========================
     *  CORE REQUEST + AUTH
     * ===========================
     */

    /**
     * Make API request to Oblio.
     *
     * @param string $path path starting with "/"
     * @param string $method GET|POST|PUT|DELETE
     * @param array|null $data
     * @param bool $isJson true => send JSON body (for create docs), false => form or query
     */
    protected static function request(string $path, string $method = 'GET', array $data = null, bool $isJson = true, bool $retry = true): array
    {
        $token = self::getAccessToken();

        $url = rtrim(self::API_BASE_URL, '/') . $path;

        $headers = [
            'Authorization: Bearer ' . $token,
        ];

        $body = null;

        // GET with query
        if ($method === 'GET' && !empty($data)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
        } elseif (!empty($data)) {
            if ($isJson) {
                $headers[] = 'Content-Type: application/json';
                $body = Json::encode($data);
            } else {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                $body = http_build_query($data);
            }
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => ($path === '/docs/einvoice' && $method === 'GET') ? 60 : 30,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \Exception('Oblio cURL error: ' . $err);
        }

        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Retry on 401 once
        if ($http === 401 && $retry) {
            self::authorize(true);
            return self::request($path, $method, $data, $isJson, false);
        }

        $decoded = Json::decode($resp);

        if (!is_array($decoded)) {
            throw new \Exception('Invalid Oblio response (not JSON). HTTP ' . $http);
        }

        if ($http !== 200) {
            $msg = $decoded['statusMessage'] ?? 'Unknown error';
            throw new \Exception('Oblio API error (HTTP ' . $http . '): ' . $msg);
        }

        // Oblio typically includes status=200
        if (isset($decoded['status']) && (int)$decoded['status'] !== 200) {
            $msg = $decoded['statusMessage'] ?? 'Unknown error';
            throw new \Exception('Oblio API error: ' . $msg);
        }

        return $decoded;
    }

    protected static function getAccessToken(): string
    {
        $cache = Yii::$app->cache;
        $key = self::getTokenCacheKey();

        $token = $cache->get($key);
        if ($token !== false && !empty($token)) {
            return (string)$token;
        }

        return self::authorize(false);
    }

    protected static function authorize(bool $forceRefresh = false): string
    {
        $credentials = self::getCredentials();
        if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
            throw new \Exception('Oblio credentials not configured.');
        }

        /** @var CacheInterface $cache */
        $cache = Yii::$app->cache;
        $key = self::getTokenCacheKey();

        if (!$forceRefresh) {
            $cached = $cache->get($key);
            if ($cached !== false && !empty($cached)) {
                return (string)$cached;
            }
        }

        $url = rtrim(self::API_BASE_URL, '/') . '/' . self::TOKEN_ENDPOINT;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                // Oblio docs show only client_id/client_secret; grant_type usually optional.
                // Keep it harmlessly here:
                'grant_type' => 'client_credentials',
            ]),
        ]);

        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \Exception('Oblio auth cURL error: ' . $err);
        }

        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = Json::decode($resp);
        if ($http !== 200 || empty($data['access_token'])) {
            $msg = is_array($data) ? ($data['error_description'] ?? ($data['statusMessage'] ?? 'Invalid auth response')) : 'Invalid auth response';
            throw new \Exception('Oblio auth failed (HTTP ' . $http . '): ' . $msg);
        }

        $expires = isset($data['expires_in']) ? (int)$data['expires_in'] : 3600;
        $ttl = max(60, $expires - 60);

        $cache->set($key, $data['access_token'], $ttl);

        return $data['access_token'];
    }

    protected static function getCredentials(): array
    {
        // Get credentials from general settings
        $clientId = Yii::$app->settings->get('oblioClientId', 'general');
        $clientSecret = Yii::$app->settings->get('oblioClientSecret', 'general');

        return [
            'client_id' => $clientId ?: '',
            'client_secret' => $clientSecret ?: '',
        ];
    }

    protected static function getTokenCacheKey(): string
    {
        $credentials = self::getCredentials();
        $clientId = (string)($credentials['client_id'] ?? 'default');
        return self::TOKEN_CACHE_KEY_PREFIX . sha1($clientId);
    }

    public static function downloadInvoicePdf($oblioUrl = null)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $oblioUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $pdfContent = curl_exec($ch);

        curl_close($ch);

        return $pdfContent;
    }
}
