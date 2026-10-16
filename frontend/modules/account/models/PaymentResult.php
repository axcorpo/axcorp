<?php

namespace frontend\modules\account\models;

use common\models\Invoice;
use common\models\InvoiceHasTemplate;
use common\models\Item;
use common\models\OblioInvoice;
use common\models\PaymentMetadata;
use common\models\Subscription;
use common\models\SubscriptionFeature;
use common\models\Template;
use common\models\WorkspaceHasSubscriptionFeature;
use kartik\mpdf\Pdf;
use Yii;
use yii\base\Model;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;

class PaymentResult extends Model
{
	/**
	 * @var string|int The Subscription model ID.
	 */
	public $subscription_id;

	/**
	 * @var string|int The payment processor payer ID.
	 */
	public $payer_id;

	/**
	 * @var string|int The payment processor card ID.
	 */
	public $card_id;

	/**
	 * @var string|int The payment processor payment ID.
	 */
	public $payment_id;

	/**
	 * @var string|int The payment processor transaction ID.
	 */
	public $transaction_id;

	/**
	 * @var Subscription The Subscription model.
	 */
	private $_subscription;

	/**
	 * @var Invoice The Invoice model.
	 */
	private $_invoice;

	/**
	 * @var \DateTime The processing date and time instance.
	 */
	private $_processingDate;


	/**
	 * The charge behind a paid Checkout session (subscription mode:
	 * session → invoice → payment intent → latest_charge).
	 *
	 * Newer Stripe API versions dropped `invoice.payment_intent` — the payment intent now
	 * sits on the invoice's payments list, so the invoice is retrieved with that list
	 * expanded (older versions reject the unknown expand and fall back to the plain
	 * retrieve, where `payment_intent` still exists). Without the charge id, payment_id
	 * stays empty and payment/run treats the subscription as not configured for recurring
	 * — it suspends it while Stripe keeps charging.
	 *
	 * @param \Stripe\StripeClient $stripe
	 * @param \Stripe\Checkout\Session|array $session
	 * @return string|null
	 */
	public static function findSessionChargeId($stripe, $session)
	{
		$charge = null;

		if (!empty($session['invoice'])) {
			try {
				$invoice = $stripe->invoices->retrieve($session['invoice'], [
					'expand' => ['payments.data.payment.payment_intent'],
				]);
			} catch (\Exception $e) {
				$invoice = $stripe->invoices->retrieve($session['invoice'], []);
			}

			// Old API versions carry the payment intent (id) on the invoice itself; new ones
			// carry it (expanded above) on the invoice's payments list.
			$paymentIntent = $invoice['payment_intent']
				?? ($invoice['payments']['data'][0]['payment']['payment_intent'] ?? null);
			if (is_string($paymentIntent) && $paymentIntent !== '') {
				$paymentIntent = $stripe->paymentIntents->retrieve($paymentIntent, []);
			}

			if (!empty($paymentIntent)) {
				// Stripe API 2022-11-15+ dropped `charges` from the PaymentIntent — the charge
				// id lives in `latest_charge` now. Legacy path kept for old API versions.
				$charge = $paymentIntent['latest_charge'] ?? null;
				if (!is_string($charge)) {
					$charge = $charge['id'] ?? null;
				}
				if (empty($charge)) {
					$charge = $paymentIntent['charges']['data'][0]['id'] ?? null;
				}
			}
		}

		// Last resort — some API versions return the paid invoice with an empty payments
		// list. The newest charge of the session's customer is the one just collected.
		if (empty($charge) && !empty($session['customer'])) {
			$charges = $stripe->charges->all(['customer' => $session['customer'], 'limit' => 1]);
			$charge = $charges['data'][0]['id'] ?? null;
		}

		return $charge ?: null;
	}

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->_processingDate = new \DateTime();
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['subscription_id'], 'required'],
			[['subscription_id'], 'exist', 'targetClass' => Subscription::class, 'targetAttribute' => ['subscription_id' => 'id'], 'skipOnError' => true],
			[['payer_id', 'card_id', 'payment_id',  'transaction_id'], 'safe'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'subscription_id' => Yii::t('label', 'Subscription ID'),
			'payer_id' => Yii::t('label', 'Payer ID'),
			'card_id' => Yii::t('label', 'Card ID'),
			'payment_id' => Yii::t('label', 'Payment ID'),
			'transaction_id' => Yii::t('label', 'Transaction ID'),
		]);
	}

	/**
	 * Gets the Subscription model.
	 *
	 * @return Subscription|null
	 */
	public function getSubscription()
	{
		if (!$this->_subscription) {
			$this->_subscription = Subscription::findOne([
				'id' => $this->subscription_id,
				'deleted' => Subscription::NO,
			]);
		}
		return $this->_subscription;
	}

	/**
	 * Gets the Invoice model.
	 *
	 * @return Invoice|null
	 */
	public function getInvoice()
	{
		return $this->_invoice;
	}

	/**
	 * Saves the Subscription model.
	 *
	 * @return bool
	 */
	protected function saveSubscription()
	{
		try {
			$subscription = $this->getSubscription();

			// The current billing period as Stripe reports it (on the subscription item on
			// the API version the SDK pins). Empty when the subscription is not on Stripe or
			// Stripe is unreachable: the local package computation is the fallback then.
			$stripeItem = [];
			if (!empty($subscription->external_id) && $subscription->paymentMetadata && $subscription->paymentMetadata->payment_processor == PaymentMetadata::PAYMENT_PROCESSOR_STRIPE) {
				try {
					$payment = Yii::$app->payment->via(PaymentMetadata::PAYMENT_PROCESSOR_STRIPE);
					$stripe = new \Stripe\StripeClient($payment->privateKey);
					$stripeSubscription = $stripe->subscriptions->retrieve($subscription->external_id, []);
					$stripeItem = $stripeSubscription['items']['data'][0] ?? [];
				} catch (\Exception $e) {
					Yii::error('Stripe subscription retrieve failed: ' . $e->getMessage(), __METHOD__);
				}
			}

			// Stripe's timestamps are UTC; the stored dates follow the app time zone.
			$timeZone = new \DateTimeZone(Yii::$app->timeZone);
			$stripeStartAt = !empty($stripeItem['current_period_start'])
				? (new \DateTime('@' . $stripeItem['current_period_start']))->setTimezone($timeZone)->format('Y-m-d H:i:s')
				: null;
			$stripeEndAt = !empty($stripeItem['current_period_end'])
				? (new \DateTime('@' . $stripeItem['current_period_end']))->setTimezone($timeZone)->format('Y-m-d H:i:s')
				: null;

			// (Re)Activate the subscription
			if ($subscription->status == Subscription::STATUS_SUSPENDED) {
				$subscription->start_at = $stripeStartAt ?: $this->_processingDate->format('Y-m-d H:i:s');
				$subscription->end_at = $stripeEndAt ?: $subscription->getEndAtDate()->format('Y-m-d H:i:s');
				if (!$subscription->activate($subscription->start_at, false, $subscription->end_at)) {
					throw new \Exception('Subscription cannot be reactivated.');
				}
			} else {
				$startAt = $this->_processingDate->format('Y-m-d H:i:s');
				if (!$subscription->getIsOnTrialPeriod()) {
					$startAt = $subscription->end_at ?: $this->_processingDate->format('Y-m-d H:i:s');
				}
				if (!$subscription->activate($stripeStartAt ?: $startAt, false, $stripeEndAt)) {
					throw new \Exception('Subscription cannot be activated.');
				}
			}

			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	protected function renewFeaturesQuota()
	{
		try {
			$subscription = $this->getSubscription();
			$currentDate = (new \DateTime())->format('Y-m-d H:i:s');
			$renewableSubscriptionFeatures = SubscriptionFeature::find()
				->alias('sf')
				->select(['sf.id'])
				->joinWith(['subscription sub'])
				->andWhere(['!=', 'sub.type', Subscription::TYPE_FREE])
				->andWhere([
					'sf.renewable' => SubscriptionFeature::YES,
					'sub.status' => Subscription::STATUS_ACTIVE,
					'sub.deleted' => Subscription::NO,
					'sub.id' => $subscription->id,
				])
				->indexBy('id')
				->all();
			if ($renewableSubscriptionFeatures) {
				WorkspaceHasSubscriptionFeature::updateAll([
					'quota' => 0,
					'renewed_at' => $currentDate,
				], [
					'subscription_feature_id' => array_keys($renewableSubscriptionFeatures),
				]);
			}
			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * Saves the PaymentMetadata model.
	 *
	 * @return bool
	 */
	protected function savePaymentMetadata()
	{
		try {
			$paymentMetadata = $this->getSubscription()->paymentMetadata;
			$paymentMetadata->payer_id = (string) $this->payer_id;
			$paymentMetadata->payment_id = (string) $this->payment_id;
			if (!$paymentMetadata->save()) {
				throw new \Exception($paymentMetadata->getErrorSummary(false)[0]);
			}
			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * Saves the Invoice model.
	 *
	 * @return bool
	 */
	protected function saveInvoice()
	{
		try {
			$subscription = $this->getSubscription();

			/** @var Invoice $invoice */
			$proform = Invoice::find()
				->alias('i')
				->joinWith([
					'items itm' => function (ActiveQuery $query) use ($subscription) {
						$query->andWhere([
							'itm.item_id' => $subscription->id,
							'itm.type' => Item::TYPE_SUBSCRIPTION,
						]);
					}
				], false)
				->andWhere([
					'i.status' => Invoice::STATUS_UNPAID,
				])
				->orderBy(['i.issued_at' => SORT_DESC])
				->limit(1)
				->one();

			$invoice = new Invoice();
			$invoice->parent_id = $proform ? $proform->id : null;
			$invoice->subscriber_id = $subscription->subscriber_id;
			$invoice->issued_at = $this->_processingDate->format('Y-m-d H:i:s');
			$invoice->company_id = $subscription->company_id;
			$invoice->paid_at = $this->_processingDate->format('Y-m-d H:i:s');
			$invoice->payment_method = $subscription->paymentMetadata->payment_method;
			$invoice->payment_processor = $subscription->paymentMetadata->payment_processor;
			$invoice->amount = $subscription->price;
			$invoice->currency = $subscription->currency;
            $invoice->vat = Yii::$app->settings->get('vatRate', 'general') ?: 0;
			$invoice->setSerializedValue('details', [
				'transaction_id' => $subscription->paymentMetadata->payment_id,
				'merchant' => $invoice->getMerchantDetails(),
				'client' => $invoice->getClientDetails(),
			]);
			$invoice->type = Invoice::TYPE_INVOICE;
			$invoice->status = Invoice::STATUS_PAID;
			if (!$invoice->save()) {
				throw new \Exception('Invoice cannot be saved.');
			}
			$this->_invoice = $invoice;


			$invoiceTemplate = Template::findDefaultByType(Template::TYPE_INVOICE);
			$invoiceHasTemplate = InvoiceHasTemplate::findOne([
				'invoice_id' => $invoice->id,
				'template_id' => $invoiceTemplate->id,
			]);
			if (!$invoiceHasTemplate) {
				$invoiceHasTemplate = new InvoiceHasTemplate();
				$invoiceHasTemplate->invoice_id = $invoice->id;
				$invoiceHasTemplate->template_id = Template::findDefaultByType(Template::TYPE_INVOICE)->id;
				if (!$invoiceHasTemplate->save()) {
					throw new \Exception('Invoice template cannot be saved.');
				}
			}

			$item = $invoice->getItems()
				->andWhere([
					'item_id' => $subscription->id,
					'type' => Item::TYPE_SUBSCRIPTION,
				])
				->limit(1)
				->one();

			if (!$item) {
				$item = new Item();
				$item->invoice_id = $invoice->id;
				$item->item_id = $subscription->id;
				$item->type = Item::TYPE_SUBSCRIPTION;
				$item->quantity = 1;
			}
			$item->price = $subscription->price;
			$item->currency = $subscription->currency;
			$item->setSerializedValue('details', [
				'start_at' => $subscription->start_at,
				'end_at' => $subscription->end_at,
			]);
			$item->status = Item::STATUS_ACTIVE;
			if (!$item->save()) {
				throw new \Exception('Invoice item cannot be saved.');
			}

			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * Pushes the freshly created invoice to Oblio. Failure is non-fatal — the payment flow
	 * continues and the invoice can be re-sent later from the backend (send-to-oblio).
	 *
	 * @return bool
	 */
	protected function sendInvoiceToOblio()
	{
		$invoice = $this->getInvoice();
		if ($invoice === null) {
			return false;
		}

		try {
			OblioInvoice::createInvoiceFromLocalInvoice($invoice, [
				'seriesName' => $invoice->document_series,
				'disableAutoSeries' => 1,
				'number' => $invoice->document_number,
			]);
		} catch (\Throwable $e) {
			Yii::error('Oblio invoice for subscription payment failed: ' . $e->getMessage(), __METHOD__);
			return false;
		}

		return true;
	}

	/**
	 * Sends the payment confirmation with the invoice PDF attached: Oblio's render when the
	 * invoice was pushed there, the local mPDF one otherwise.
	 *
	 * @return bool
	 */
	protected function sendInvoiceToEmail()
	{
		try {
			$subscription = $this->getSubscription();
			$subscriber = $subscription->subscriber;
			$user = $subscriber->user;
			$invoice = $this->getInvoice();

			$invoiceEmailTemplate = Template::findDefaultByTypeAndVariant(Template::TYPE_EMAIL, Template::EMAIL_VARIANT_INVOICE_PAYMENT_CONFIRMATION);
			if (!$invoiceEmailTemplate || !($invoiceEmailTemplateTranslation = $invoiceEmailTemplate->getTranslation())) {
				throw new \Exception('Missing invoice email template.');
			}
			$fileName = Yii::$app->name . " #{$invoice->getDocumentSeriesNumber()}.pdf";
			$filePath = Yii::getAlias("@runtime/" . Yii::$app->security->generateRandomString(8) . "-{$fileName}");
			$attachPath = null;

			// If the invoice was pushed to Oblio, attach the Oblio PDF instead of the
			// local mPDF render.
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
							$response = OblioInvoice::viewInvoice($sellerCif, $seriesName, (int) $number);
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

			if ($attachPath === null) {
				$invoiceTemplate = Template::findDefaultByTypeAndVariant(Template::TYPE_INVOICE, Template::INVOICE_VARIANT_FISCAL) ?: Template::findDefaultByType(Template::TYPE_INVOICE);
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
			$shortCodeValues = $invoice->getShortCodeValues();

			Yii::$app->mailer->compose()
				->setTo([$user->email => $user->fullName])
				->setSubject(strtr($invoiceEmailTemplateTranslation->subject, $shortCodeValues))
				->setHtmlBody(strtr($invoiceEmailTemplateTranslation->content, $shortCodeValues))
				->attach($attachPath, ['fileName' => $fileName])
				->send();

			@unlink($attachPath);

			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * Saves the models.
	 *
	 * @return bool
	 */
	public function save()
	{
		$dbTransaction = Yii::$app->db->beginTransaction();
		try {
			if (!$this->validate()) {
				throw new \Exception();
			}
			if (!($subscription = $this->getSubscription())) {
				$this->addError('', Yii::t('frontend', 'Payment response is not valid.'));
				throw new \Exception();
			}
			// Do nothing if the subscription is already active or is cancelled
			if ($subscription->getIsOnTrialPeriod() || !in_array($subscription->status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_CANCELLED])) {
				if (!$this->saveSubscription()) {
					throw new \Exception();
				}
				if (!$this->renewFeaturesQuota()) {
					throw new \Exception();
				}
				if (!$this->savePaymentMetadata()) {
					throw new \Exception();
				}
				if (!$this->saveInvoice()) {
					throw new \Exception();
				}
				$this->sendInvoiceToOblio();
				$this->sendInvoiceToEmail();
			}
			$dbTransaction->commit();
			return true;
		} catch(\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}
}
