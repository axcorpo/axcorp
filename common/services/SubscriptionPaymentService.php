<?php

namespace common\services;

use common\models\Invoice;
use common\models\InvoiceHasTemplate;
use common\models\Item;
use common\models\Notification;
use common\models\OblioInvoice;
use common\models\PaymentMetadata;
use common\models\Subscription;
use common\models\Template;
use kartik\mpdf\Pdf;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;

/**
 * The subscription billing pipeline, extracted from the payment/run console controller
 * so the Stripe webhook can drive the same steps: due-subscription selection, proforma
 * issuance, recurring-payment verification, renewal settlement and expiry suspension.
 *
 * Ported from the masteranunturi workspace app and adapted to this app's models: the
 * document series and number are assigned by Invoice's DocumentSeriesAndNumberBehavior,
 * the invoice types are TYPE_PROFORMA / TYPE_INVOICE. Every issued document (proforma
 * and fiscal invoice) is pushed to Oblio under its local series and number, before its
 * e-mail goes out, so the mail carries Oblio's render; a failing push never blocks the
 * pipeline and the document can be re-sent from the backend list.
 */
class SubscriptionPaymentService
{
	/**
	 * @var \DateTime The current date and time instance used for this process operations.
	 */
	public static $currentDate;

	/**
	 * Pins the clock the whole pipeline computes against (now by default).
	 *
	 * @param \DateTime|null $currentDate
	 */
	public static function setCurrentDate($currentDate = null)
	{
		self::$currentDate = $currentDate ?: new \DateTime();
	}

	/**
	 * Gets the subscriptions that need the proforma of their upcoming renewal: active
	 * ones within the issuance window of their end_at, with no proforma for that cycle
	 * yet. Once the proforma is issued the subscription leaves this list on its own.
	 *
	 * The cycle's proforma is the one saveInvoice() issues with due_at = the
	 * subscription's end_at; a renewed subscription (end_at moved on) has none again.
	 * Suspended and cancelled subscriptions are in neither this list nor the expired one.
	 *
	 * @return array|\yii\db\ActiveRecord[]|Subscription[]
	 */
	public static function getDueSubscriptions()
	{
		$currentDate = self::$currentDate->format('Y-m-d H:i:s');
		$invoiceIssuanceBeforePeriod = (int) Yii::$app->settings->get('invoiceIssuanceBeforePeriod', 'payment');

		$proformaInvoicesQuery = Invoice::find()
			->alias('i')
			->joinWith([
				'items itm' => function (ActiveQuery $query) {
					$query->andWhere([
						'itm.type' => Item::TYPE_SUBSCRIPTION,
						'itm.status' => Item::STATUS_ACTIVE,
						'itm.deleted' => Item::NO,
					]);
				},
			], false)
			->andWhere([
				'i.type' => Invoice::TYPE_PROFORMA,
				'i.deleted' => Invoice::NO,
			])
			->andWhere('[[itm.item_id]] = [[sub.id]]')
			->andWhere('[[i.due_at]] = [[sub.end_at]]');

		$subscriptionsQuery = Subscription::find()
			->alias('sub')
			->andWhere([
				'AND',
				['IS', 'sub.parent_id', null],
				['!=', 'sub.type', Subscription::TYPE_FREE],
				['sub.status' => Subscription::STATUS_ACTIVE],
			])
			->andWhere(new Expression("TIMESTAMPDIFF(DAY, '{$currentDate}', [[sub.end_at]]) <= '{$invoiceIssuanceBeforePeriod}'"))
			->andWhere(['NOT EXISTS', $proformaInvoicesQuery])
			->andWhere([
				'sub.deleted' => Subscription::NO,
			])
			->orderBy(['sub.end_at' => SORT_ASC])
			->limit(3);

		return $subscriptionsQuery->all();
	}

	/**
	 * Gets the subscriptions that reached their end_at and are still active: the ones
	 * whose payment has to be checked (settled when collected, suspended past the
	 * allowed overdue period). Settling moves end_at on and suspending changes the
	 * status, so a subscription leaves this list on its own either way.
	 *
	 * @return array|\yii\db\ActiveRecord[]|Subscription[]
	 */
	public static function getExpiredSubscriptions()
	{
		$currentDate = self::$currentDate->format('Y-m-d H:i:s');

		$subscriptionsQuery = Subscription::find()
			->alias('sub')
			->andWhere([
				'AND',
				['IS', 'sub.parent_id', null],
				// A free subscription has an end only when its package carries a billing
				// cycle (a term, expired below without renewal); the lifetime ones (end_at
				// NULL) never reach here.
				['OR', ['!=', 'sub.type', Subscription::TYPE_FREE], ['IS NOT', 'sub.end_at', null]],
				['sub.status' => Subscription::STATUS_ACTIVE],
			])
			->andWhere(['<=', 'sub.end_at', $currentDate])
			->andWhere([
				'sub.deleted' => Subscription::NO,
			])
			->orderBy(['sub.end_at' => SORT_ASC]);

		return $subscriptionsQuery->all();
	}

	/**
	 * Formats a payment processor's UTC timestamp as a stored date, in the app time zone.
	 *
	 * @param int|string|null $timestamp
	 * @return string|null `Y-m-d H:i:s`, or null when there is no timestamp.
	 */
	public static function formatProcessorTimestamp($timestamp)
	{
		if (empty($timestamp)) {
			return null;
		}

		return (new \DateTime('@' . (int) $timestamp))
			->setTimezone(new \DateTimeZone(Yii::$app->timeZone))
			->format('Y-m-d H:i:s');
	}

	/**
	 * The period of the subscription's upcoming cycle as the processor will bill it,
	 * in the app time zone, for the proforma's line. Only for subscriptions linked to
	 * a processor subscription (external_id) whose processor can preview it; nulls
	 * otherwise, and the caller computes the period locally. A preview that does not
	 * end past the current end_at is not the next cycle and is ignored.
	 *
	 * @param Subscription $subscription
	 * @return array{0: string|null, 1: string|null} [start_at, end_at]
	 */
	public static function findProcessorUpcomingPeriod($subscription)
	{
		$paymentMetadata = $subscription->paymentMetadata;
		if (empty($subscription->external_id) || !$paymentMetadata || empty($paymentMetadata->payment_processor)) {
			return [null, null];
		}
		try {
			$paymentProcessor = Yii::$app->payment->via($paymentMetadata->payment_processor);
		} catch (\Throwable $e) {
			return [null, null];
		}
		if (!is_object($paymentProcessor) || !method_exists($paymentProcessor, 'findSubscriptionUpcomingPeriod')) {
			return [null, null];
		}

		$period = $paymentProcessor->findSubscriptionUpcomingPeriod($subscription->external_id);
		$startAt = self::formatProcessorTimestamp($period['start'] ?? null);
		$endAt = self::formatProcessorTimestamp($period['end'] ?? null);
		if ($endAt === null || $endAt <= $subscription->end_at) {
			return [null, null];
		}

		return [$startAt ?: $subscription->end_at, $endAt];
	}

	/**
	 * Finds the proforma of the subscription's current cycle (due_at = end_at).
	 *
	 * @param Subscription $subscription
	 * @return array|\yii\db\ActiveRecord|Invoice|null
	 */
	public static function findCycleProforma($subscription)
	{
		return Invoice::find()
			->alias('i')
			->joinWith([
				'items itm' => function (ActiveQuery $query) use ($subscription) {
					$query->andWhere([
						'itm.item_id' => $subscription->id,
						'itm.type' => Item::TYPE_SUBSCRIPTION,
						'itm.status' => Item::STATUS_ACTIVE,
						'itm.deleted' => Item::NO,
					]);
				},
			], false)
			->andWhere([
				'i.type' => Invoice::TYPE_PROFORMA,
				'i.due_at' => $subscription->end_at,
				'i.deleted' => Invoice::NO,
			])
			->orderBy(['i.id' => SORT_DESC])
			->one();
	}

	/**
	 * The invoice layout for a document: the variant that matches its type when one is
	 * defined, otherwise the default invoice template.
	 *
	 * @param Invoice $invoice
	 * @return Template|null
	 */
	protected static function findInvoiceTemplate($invoice)
	{
		$variant = $invoice->type == Invoice::TYPE_PROFORMA ? Template::INVOICE_VARIANT_PROFORM : Template::INVOICE_VARIANT_FISCAL;

		return Template::findDefaultByTypeAndVariant(Template::TYPE_INVOICE, $variant) ?: Template::findDefaultByType(Template::TYPE_INVOICE);
	}

	/**
	 * Creates a PDF file for an Invoice model and sends it to email.
	 *
	 * @param Subscription $subscription
	 * @param Invoice $invoice
	 * @return bool
	 */
	public static function sendInvoiceToEmail($subscription, $invoice)
	{
		try {
			$invoiceEmailTemplateVariant = $invoice->status == Invoice::STATUS_PAID ? Template::EMAIL_VARIANT_INVOICE_PAYMENT_CONFIRMATION : Template::EMAIL_VARIANT_INVOICE_ISSUANCE;
			$invoiceEmailTemplate = Template::findDefaultByTypeAndVariant(Template::TYPE_EMAIL, $invoiceEmailTemplateVariant);
			if (!$invoiceEmailTemplate || !($invoiceEmailTemplateTranslation = $invoiceEmailTemplate->getTranslation())) {
				throw new \Exception('Missing invoice email template.');
			}
			$shortCodeValues = $invoice->getShortCodeValues();

			$email = Yii::$app->mailer->compose()
				->setTo([$subscription->subscriber->user->email => $subscription->subscriber->user->fullName])
				->setSubject(strtr($invoiceEmailTemplateTranslation->subject, $shortCodeValues))
				->setHtmlBody(strtr($invoiceEmailTemplateTranslation->content, $shortCodeValues));

			$attachPath = null;
			if (Yii::$app->settings->get('attachInvoiceToEmail', 'payment')) {
				$fileName = Yii::$app->name . " #{$invoice->getDocumentSeriesNumber()}.pdf";
				$filePath = Yii::getAlias("@runtime/" . Yii::$app->security->generateRandomString(8) . "-{$fileName}");

				// If the document was pushed to Oblio, attach the Oblio render instead
				// of the local mPDF one — proformas and fiscal invoices alike.
				if (!empty($invoice->external_id)) {
					try {
						$details = $invoice->getUnserializedValue('details') ?: [];
						$oblioUrl = $details['oblio_url'] ?? null;
						if (empty($oblioUrl)) {
							$merchant = $details['merchant'] ?? [];
							$sellerCif = $merchant['tin'] ?? null;
							$seriesName = $details['oblio_series'] ?? $invoice->document_series;
							$number = $details['oblio_number'] ?? $invoice->document_number;
							if (!empty($sellerCif) && !empty($seriesName) && !empty($number)) {
								$response = $invoice->type == Invoice::TYPE_PROFORMA
									? OblioInvoice::viewProforma($sellerCif, $seriesName, (int) $number)
									: OblioInvoice::viewInvoice($sellerCif, $seriesName, (int) $number);
								$oblioUrl = $response['data']['link'] ?? null;
							}
						}
						if (!empty($oblioUrl) && ($pdfContent = OblioInvoice::downloadInvoicePdf($oblioUrl))) {
							file_put_contents($filePath, $pdfContent);
							$attachPath = $filePath;
						}
					} catch (\Exception $e) {
						// Fall through to the local render
					}
				}
			}

			if ($attachPath === null && Yii::$app->settings->get('attachInvoiceToEmail', 'payment')) {
				$invoiceTemplate = self::findInvoiceTemplate($invoice);
				if (!$invoiceTemplate || !($invoiceTemplateTranslation = $invoiceTemplate->getTranslation())) {
					throw new \Exception('Missing invoice template.');
				}

				$pdf = new Pdf([
					'mode' => Pdf::MODE_UTF8,
					'format' => Pdf::FORMAT_A4,
					'orientation' => Pdf::ORIENT_PORTRAIT,
					'destination' => Pdf::DEST_FILE,
					'content' => strtr($invoiceTemplateTranslation->content, $invoice->getShortCodeValues(true)),
					'filename' => $filePath,
					'marginTop' => 5,
					'marginBottom' => 5,
				]);
				$pdfApi = $pdf->getApi();
				$pdfApi->setAutoTopMargin = true;
				$pdfApi->setAutoBottomMargin = true;
				$pdfApi->defaultheaderline = 0;
				$pdfApi->defaultfooterline = 0;
				if ($invoice->status == Invoice::STATUS_UNPAID) {
					$pdfApi->SetWatermarkText(mb_strtoupper(Invoice::getStatusLabels()[$invoice->status]['label']), 0.1);
					$pdfApi->showWatermarkText = true;
				}
				$pdf->render();
				$attachPath = $pdf->filename;
			}

			if ($attachPath !== null) {
				$email->attach($attachPath, ['fileName' => $fileName]);
			}

			$email->send();

			if ($attachPath !== null) {
				@unlink($attachPath);
			}
			return true;
		} catch (\Exception $e) {
			Yii::warning(['message' => 'Invoice email not sent.', 'invoice' => $invoice->getDocumentSeriesNumber(), 'reason' => $e->getMessage()], __METHOD__);
			return false;
		}
	}

	/**
	 * Gets the proforma of the subscription's current cycle, issuing it when there is
	 * none: the payment request for the next period, sent to the customer ahead of the
	 * due date.
	 *
	 * @param Subscription $subscription
	 * @return array|\yii\db\ActiveRecord|Invoice|null
	 */
	public static function saveInvoice($subscription)
	{
		if (self::$currentDate === null) {
			self::setCurrentDate();
		}

		$dbTransaction = Yii::$app->db->beginTransaction();
		try {
			$currentDate = self::$currentDate->format('Y-m-d H:i:s');

			// One proforma per subscription and cycle: the lookup is by this
			// subscription's own current cycle (due_at = its end_at), never by the
			// subscriber's other subscriptions and their invoices.
			if ($invoice = self::findCycleProforma($subscription)) {
				$dbTransaction->commit();
				return $invoice;
			}

			$vatRate = Yii::$app->settings->get('vatRate', 'general') ?: 0;
			$invoice = new Invoice();
			$invoice->subscriber_id = $subscription->subscriber_id;
			$invoice->company_id = $subscription->company_id;
			$invoice->issued_at = $currentDate;
			$invoice->due_at = $subscription->end_at;
			// Not every subscription carries payment metadata (e.g. ones activated from the
			// backend): bill those by bank transfer, the backend activation's own fallback.
			$invoice->payment_method = $subscription->paymentMetadata?->payment_method ?: PaymentMetadata::PAYMENT_METHOD_BANK;
			$invoice->payment_processor = $subscription->paymentMetadata?->payment_processor;
			$invoice->amount = $subscription->price;
			$invoice->currency = $subscription->currency;
			$invoice->vat = $vatRate;
			$invoice->setSerializedValue('details', [
				'merchant' => $invoice->getMerchantDetails(),
				'client' => $invoice->getClientDetails(),
				'vatRate' => $vatRate,
			]);
			$invoice->type = Invoice::TYPE_PROFORMA;
			$invoice->status = Invoice::STATUS_UNPAID;
			if (!$invoice->save()) {
				throw new \Exception('Invoice cannot be saved.');
			}

			if (!($invoiceTemplate = self::findInvoiceTemplate($invoice))) {
				throw new \Exception('Missing invoice template.');
			}
			$invoiceHasTemplate = new InvoiceHasTemplate();
			$invoiceHasTemplate->invoice_id = $invoice->id;
			$invoiceHasTemplate->template_id = $invoiceTemplate->id;
			if (!$invoiceHasTemplate->save()) {
				throw new \Exception('Invoice template cannot be saved.');
			}

			$item = new Item();
			$item->invoice_id = $invoice->id;
			$item->item_id = $subscription->id;
			$item->type = Item::TYPE_SUBSCRIPTION;
			$item->quantity = 1;
			$item->price = $subscription->price;
			$item->currency = $subscription->currency;
			// The billed period: the processor's own upcoming cycle when it can preview
			// it, else the local "+1 period" from the current end_at.
			[$cycleStartAt, $cycleEndAt] = self::findProcessorUpcomingPeriod($subscription);
			$item->setSerializedValue('details', [
				'start_at' => $cycleStartAt ?: $subscription->end_at,
				'end_at' => $cycleEndAt ?: $subscription->getEndAtDate($subscription->end_at)->format('Y-m-d H:i:s'),
			]);
			$item->status = Item::STATUS_ACTIVE;
			if (!$item->save()) {
				throw new \Exception('Invoice item cannot be saved.');
			}

			// Push the proforma to Oblio BEFORE the issuance e-mail, so the mail can
			// carry Oblio's render; non-fatal — a failing Oblio never blocks issuance.
			try {
				OblioInvoice::createProformaFromLocalInvoice($invoice, [
					'seriesName' => $invoice->document_series,
					'disableAutoSeries' => 1,
					'number' => $invoice->document_number,
				]);
			} catch (\Throwable $e) {
				Yii::error('Oblio proforma for subscription renewal failed: ' . $e->getMessage(), __METHOD__);
			}

			if (Yii::$app->settings->get('enableInvoiceEmailNotification', 'payment')) {
				self::sendInvoiceToEmail($subscription, $invoice);
			}

			// In-app notification for the subscriber, only when the proforma is actually
			// created (an existing open one returned early above).
			try {
				Notification::create([
					'title' => Yii::t('notification', 'Subscription due'),
					'message' => Yii::t('notification', 'The proforma invoice has been issued for the {0} subscription.', [
						\yii\helpers\Html::a(
							$subscription->package->translation->name . ' (' . $subscription->code . ')',
							\tws\helpers\Url::to(['/account/invoice/index'], false, '@frontend')
						),
					]),
					'icon' => 'fa fa-file-text-o',
					'color' => '#f1bb15',
				], [$subscription->subscriber->user_id]);
			} catch (\Throwable $e) {
				Yii::warning('Subscription due notification failed: ' . $e->getMessage(), __METHOD__);
			}

			$dbTransaction->commit();
			return $invoice;
		} catch (\Exception $e) {
			$dbTransaction->rollBack();
			// A failed issuance also skips the expiry check for this subscription in the
			// runner, so it must be visible in the log, not silently retried forever.
			Yii::error(['message' => 'Cannot issue the invoice.', 'subscription' => $subscription->code, 'error' => $e->getMessage()], __METHOD__);
			return null;
		}
	}

	/**
	 * Checks the subscription for expiration and updates the models states: suspends it
	 * past the grace window, or settles the renewal when the processor reports the
	 * recurring payment as collected.
	 *
	 * @param Subscription $subscription
	 * @param Invoice|null $invoice null for a free term subscription, which is not billed
	 * @return array|bool
	 */
	public static function checkSubscription($subscription, $invoice)
	{
		if (self::$currentDate === null) {
			self::setCurrentDate();
		}

		$dbTransaction = Yii::$app->db->beginTransaction();
		try {
			// A free term subscription has nothing to collect: it is suspended the moment
			// its term ends, with no overdue grace and no renewal (and no afterSuspend
			// event, whose notification reads "due to nonpayment").
			if ($subscription->type == Subscription::TYPE_FREE) {
				$subscription->suspend();
				$dbTransaction->commit();
				return true;
			}

			$maxAllowedPaymentOverdueDate = new \DateTime($subscription->end_at);
			if ($allowedPaymentOverduePeriod = Yii::$app->settings->get('allowedPaymentOverduePeriod', 'payment')) {
				$maxAllowedPaymentOverdueDate->modify("+{$allowedPaymentOverduePeriod} days");
			}

			// Conditionally stop this process if the subscription is suspended
			if ($subscription->status == Subscription::STATUS_SUSPENDED) {
				// Nothing to do if the subscription overflows the allowed payment overdue period.
				// Otherwise, check the payment each 30 minutes.
				if (self::$currentDate >= $maxAllowedPaymentOverdueDate || self::$currentDate->format('i') != 30) {
					throw new \Exception('Subscription is expired.');
				}
			} elseif (self::$currentDate >= $maxAllowedPaymentOverdueDate) {
				// Suspend the subscription if overflows the allowed payment overdue period.
				$subscription->suspend();
				$dbTransaction->commit();
				Yii::$app->trigger('subscription.afterSuspend', new \yii\base\Event(['sender' => $subscription]));
				return true;
			}

			// Check if subscription is configured for recurring payment.
			$paymentMetadata = $subscription->paymentMetadata;
			if (
				!$paymentMetadata
				|| $paymentMetadata->payment_method != PaymentMetadata::PAYMENT_METHOD_CARD
				|| empty($paymentMetadata->payment_processor)
				|| empty($paymentMetadata->payer_id)
				|| empty($paymentMetadata->payment_id)
				|| $paymentMetadata->recurring_payment != PaymentMetadata::YES
			) {
				throw new \Exception('This subscription is not configured for recurring payment.');
			}

			// Use a specific payment processor API
			$paymentProcessor = Yii::$app->payment->via($paymentMetadata->payment_processor);

			$startAt = null;
			$endAt = null;
			if (!empty($subscription->external_id) && method_exists($paymentProcessor, 'findSubscriptionRenewal')) {
				// Per subscription: the processor's own subscription and the invoice it
				// last billed on it, the same checks the invoice.paid webhook makes.
				$renewal = $paymentProcessor->findSubscriptionRenewal($subscription->external_id);
				$processorInvoice = $renewal['invoice'] ?? null;
				if (empty($processorInvoice)) {
					throw new \Exception('The renewal invoice is not available at the processor yet.');
				}
				if (($processorInvoice['status'] ?? null) !== 'paid' || ($processorInvoice['billing_reason'] ?? null) !== 'subscription_cycle') {
					throw new \Exception('The recurring payment was unsuccessful.');
				}
				// The invoice must bill a cycle beyond the current end_at: the previous
				// cycle's invoice (paid long ago) ends exactly at it.
				$endAtTimestamp = (new \DateTime($subscription->end_at, new \DateTimeZone(Yii::$app->timeZone)))->getTimestamp();
				if (empty($processorInvoice['period_end']) || $processorInvoice['period_end'] <= $endAtTimestamp) {
					throw new \Exception('The processor invoice does not bill the renewal cycle.');
				}
				// The money collected must match the proforma; on a mismatch leave the
				// invoice open and let a human sort it out.
				if ((int) round($invoice->amount * 100) !== ($processorInvoice['amount_paid'] ?? -1)
					|| strcasecmp((string) $invoice->currency, (string) ($processorInvoice['currency'] ?? '')) !== 0) {
					throw new \Exception('The processor invoice amount/currency does not match the proforma.');
				}
				$transactionId = $processorInvoice['transaction_id'];

				// The new cycle as the processor reports it (already advanced once the
				// renewal invoice is paid). Missing: settleRenewal() computes it locally.
				$startAt = self::formatProcessorTimestamp($renewal['period']['start'] ?? null);
				$endAt = self::formatProcessorTimestamp($renewal['period']['end'] ?? null);
			} else {
				// Subscriptions not linked to a processor subscription (pre-external_id
				// history): the subscriber's last charge since the due date.
				$transactions = $paymentProcessor->findTransactions([
					'payerId' => $paymentMetadata->payer_id,
					'paymentId' => $paymentMetadata->payment_id,
					'startDate' => (new \DateTime($subscription->end_at))->format('Y-m-d H:00:00'),
					'sort' => SORT_DESC,
				]);

				// Check the first transaction in the results since they are descendent sorted
				$transaction = $transactions ? reset($transactions) : null;
				if (!$transaction || $transaction->status != $paymentProcessor::TRANSACTION_STATUS_SUCCEEDED) {
					throw new \Exception('The recurring payment was unsuccessful.');
				}
				$transactionId = $transaction->id;
			}

			if (!self::settleRenewal($subscription, $invoice, $transactionId, $startAt, $endAt)) {
				throw new \Exception('The renewal was settled by another path or could not be settled.');
			}

			$dbTransaction->commit();
			return true;
		} catch (\Throwable $e) {
			$dbTransaction->rollBack();
			// Normal-flow outcomes land here too (no recurring setup, unpaid within the
			// grace window), so log as warning rather than error. Throwable, not Exception:
			// a processor lookup that blows up with a TypeError must not abort the whole
			// polling run and starve the other due subscriptions.
			Yii::warning(['message' => 'Subscription check did not complete.', 'subscription' => $subscription->code, 'reason' => $e->getMessage()], __METHOD__);
			return false;
		}
	}

	/**
	 * Settles one renewal: issues a PAID fiscal invoice from the proforma's data, extends
	 * the subscription from its current end_at, sends the payment confirmation and fires
	 * `subscription.afterPayment`.
	 *
	 * The proforma itself stays UNPAID: it is a payment request, not an accounting
	 * document; the fiscal invoice (parent_id → proforma) is what documents the money,
	 * the same convention the first-purchase flow follows.
	 *
	 * Idempotency: contenders — the `invoice.paid` webhook (fast path), the payment/run
	 * polling (reconciliation net), a Stripe event redelivery — serialize on a row lock
	 * of the subscription; whoever runs second re-reads an already-advanced end_at and
	 * backs off, so no double invoice, no double activation, no double e-mail.
	 *
	 * @param Subscription $subscription
	 * @param Invoice $proforma The subscription's open proforma.
	 * @param string $transactionId The processor's payment reference (charge id).
	 * @param string|null $startAt The new cycle's start as the processor reports it (`Y-m-d H:i:s`, app time zone); the old end_at when null.
	 * @param string|null $endAt The new cycle's end as the processor reports it; computed from the package period when null.
	 * @return bool false when the renewal was already settled or the settle failed.
	 */
	public static function settleRenewal($subscription, $proforma, $transactionId, $startAt = null, $endAt = null)
	{
		if (self::$currentDate === null) {
			self::setCurrentDate();
		}

		$dbTransaction = Yii::$app->db->beginTransaction();
		try {
			$lockedEndAt = Yii::$app->db->createCommand(
				'SELECT [[end_at]] FROM ' . Subscription::tableName() . ' WHERE [[id]] = :id FOR UPDATE',
				[':id' => $subscription->getAttribute('id')]
			)->queryScalar();
			if ($lockedEndAt !== $subscription->end_at) {
				// Somebody advanced the cycle while we were getting here — settled.
				$dbTransaction->rollBack();
				return false;
			}

			// A processor end that does not move past the current end_at is not a new
			// cycle (stale read) and is ignored: nothing may shorten the subscription.
			if ($endAt === null || $endAt <= $subscription->end_at) {
				$startAt = null;
				$endAt = null;
			}

			$invoice = new Invoice();
			$invoice->parent_id = $proforma->getAttribute('id');
			$invoice->subscriber_id = $subscription->subscriber_id;
			$invoice->company_id = $subscription->company_id;
			$invoice->issued_at = self::$currentDate->format('Y-m-d H:i:s');
			$invoice->paid_at = self::$currentDate->format('Y-m-d H:i:s');
			$invoice->payment_method = $subscription->paymentMetadata->payment_method;
			$invoice->payment_processor = $subscription->paymentMetadata->payment_processor;
			$invoice->amount = $proforma->amount;
			$invoice->currency = $proforma->currency;
			$invoice->vat = $proforma->vat;
			$invoice->setSerializedValue('details', [
				'transaction_id' => $transactionId,
				'merchant' => $invoice->getMerchantDetails(),
				'client' => $invoice->getClientDetails(),
				'vatRate' => $proforma->vat,
			]);
			$invoice->type = Invoice::TYPE_INVOICE;
			$invoice->status = Invoice::STATUS_PAID;
			if (!$invoice->save()) {
				throw new \Exception('The fiscal invoice cannot be saved.');
			}

			if (!($invoiceTemplate = self::findInvoiceTemplate($invoice))) {
				throw new \Exception('Missing invoice template.');
			}
			$invoiceHasTemplate = new InvoiceHasTemplate();
			$invoiceHasTemplate->invoice_id = $invoice->id;
			$invoiceHasTemplate->template_id = $invoiceTemplate->id;
			if (!$invoiceHasTemplate->save()) {
				throw new \Exception('The invoice template cannot be saved.');
			}

			// The covered period: the processor's own cycle when known (the proforma's
			// line was computed before the processor had it), else verbatim from the
			// proforma's line.
			$item = new Item();
			$item->invoice_id = $invoice->id;
			$item->item_id = $subscription->id;
			$item->type = Item::TYPE_SUBSCRIPTION;
			$item->quantity = 1;
			$item->price = $proforma->amount;
			$item->currency = $proforma->currency;
			if ($endAt !== null) {
				$item->setSerializedValue('details', [
					'start_at' => $startAt ?: $subscription->end_at,
					'end_at' => $endAt,
				]);
			} else {
				$proformaItem = $proforma->getItems()
					->andWhere(['type' => Item::TYPE_SUBSCRIPTION])
					->limit(1)
					->one();
				if ($proformaItem) {
					$item->details = $proformaItem->details;
				}
			}
			$item->status = Item::STATUS_ACTIVE;
			if (!$item->save()) {
				throw new \Exception('The invoice item cannot be saved.');
			}

			// (Re)Activate subscription for the new cycle: the processor's period when
			// known, else anchored at the old end_at + the package period, so the period
			// never drifts; also lifts a grace-window suspension on late payments.
			if (!$subscription->activate($startAt ?: $subscription->end_at, false, $endAt)) {
				throw new \Exception('The subscription cannot be activated.');
			}

			// Push the fiscal invoice to Oblio BEFORE the confirmation e-mail, so the mail
			// carries Oblio's render; non-fatal — a failing Oblio never undoes the settle,
			// and the invoice can be re-sent later from the backend.
			try {
				OblioInvoice::createInvoiceFromLocalInvoice($invoice, [
					'seriesName' => $invoice->document_series,
					'disableAutoSeries' => 1,
					'number' => $invoice->document_number,
				]);
			} catch (\Throwable $e) {
				Yii::error('Oblio invoice for subscription renewal failed: ' . $e->getMessage(), __METHOD__);
			}

			if (Yii::$app->settings->get('enableInvoiceEmailNotification', 'payment')) {
				self::sendInvoiceToEmail($subscription, $invoice);
			}

			Yii::$app->trigger('subscription.afterPayment', new \yii\base\Event(['sender' => $subscription]));

			$dbTransaction->commit();
		} catch (\Exception $e) {
			$dbTransaction->rollBack();
			Yii::warning(['message' => 'Renewal settle failed.', 'subscription' => $subscription->code, 'reason' => $e->getMessage()], __METHOD__);
			return false;
		}

		return true;
	}
}
