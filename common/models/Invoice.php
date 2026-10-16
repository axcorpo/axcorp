<?php

namespace common\models;

use common\helpers\NumberHelper;
use tws\behaviors\DateTimeBehavior;
use common\behaviors\DocumentSeriesAndNumberBehavior;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use tws\helpers\Url;
use yii\db\ActiveQuery;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%invoice}}".
 *
 * @property int $id
 * @property int $parent_id
 * @property int $subscriber_id
 * @property int $company_id
 * @property string $external_id
 * @property string $document_series
 * @property int $document_number
 * @property string $issued_at
 * @property string $due_at
 * @property string $paid_at
 * @property int $payment_method
 * @property int $payment_processor
 * @property string $amount
 * @property string $currency
 * @property string $vat
 * @property string $exchange_rate
 * @property int $multiplier
 * @property string $e_invoice_upload_id
 * @property string $e_invoice_download_id
 * @property int $e_invoice_status
 * @property string $e_invoice_error
 * @property string $e_invoice_sent_at
 * @property string $details
 * @property int $type
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property Invoice $parent
 * @property Subscriber $subscriber
 * @property Company $company
 * @property InvoiceHasTemplate[] $invoiceHasTemplates
 * @property Template[] $templates
 * @property Item[] $items
 * @property User $creator
 * @property User $updater
 *
 * @method string getDefaultDocumentSeries()
 * @method int getNextDocumentNumber($documentSeries = null, $documentStatus = null)
 */
class Invoice extends UuidActiveRecord
{
	const STATUS_UNPAID = 1;
	const STATUS_PAID = 2;

    const TYPE_INVOICE = 10;
    const TYPE_PROFORMA = 11;
    const TYPE_RECTIFY = 12;

	const E_STATUS_NOT_SENT = 0;
	const E_STATUS_SENT = 1;
	const E_STATUS_PENDING = 2;
	const E_STATUS_PROCESSED = 3;
	const E_STATUS_UNPROCESSED = 4;
	const E_STATUS_REJECTED = 5;


    /**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%invoice}}';
	}

	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'BlameableBehavior' => [
				'class' => BlameableBehavior::class,
			],
			'TimestampBehavior' => [
				'class' => TimestampBehavior::class,
				'value' => (new \DateTime)->format('Y-m-d H:i:s'),
			],
			'DateTimeBehavior' => [
				'class' => DateTimeBehavior::class,
				'attributes' => ['issued_at', 'due_at', 'paid_at', 'e_invoice_sent_at'],
			],
			'DocumentSeriesAndNumberBehavior' => [
				'class' => DocumentSeriesAndNumberBehavior::class,
				'documentSeriesModel' => DocumentSeries::class,
				'documentSeriesDefaultValue' => function () {
					$documentSeries = DocumentSeries::findDefaultDocumentSeries(true, $this->getDocumentSeriesType());
					return $documentSeries ? $documentSeries->name : null;
				},
				'documentSeriesAttribute' => 'document_series',
				'documentNumberAttribute' => 'document_number',
				'documentStatusAttribute' => 'status',
			],
			'SoftDeleteBehavior' => [
				'class' => SoftDeleteBehavior::class,
				'softDeleteAttributeValues' => [
					'deleted' => static::YES,
				],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['parent_id', 'subscriber_id', 'company_id', 'document_number', 'payment_method', 'payment_processor', 'multiplier', 'e_invoice_status', 'type', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['issued_at', 'due_at', 'paid_at', 'created_at', 'updated_at', 'e_invoice_sent_at'], 'safe'],
			[['issued_at', 'due_at', 'paid_at', 'created_at', 'updated_at', 'e_invoice_sent_at'], 'default'],
			[['amount', 'vat', 'exchange_rate'], 'number'],
			[['details'], 'string'],
			[['status'], 'required'],
			[['external_id', 'document_series', 'e_invoice_upload_id', 'e_invoice_download_id'], 'string', 'max' => 255],
			[['currency'], 'string', 'max' => 3],
			[['company_id'], 'exist', 'skipOnError' => true, 'targetClass' => Company::class, 'targetAttribute' => ['company_id' => 'id']],
			[['subscriber_id'], 'exist', 'skipOnError' => true, 'targetClass' => Subscriber::class, 'targetAttribute' => ['subscriber_id' => 'id']],
		];
	}


	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'parent_id' => Yii::t('label', 'Parent ID'),
			'subscriber_id' => Yii::t('label', 'Subscriber ID'),
			'company_id' => Yii::t('label', 'Company ID'),
			'external_id' => Yii::t('label', 'External ID'),
			'document_series' => Yii::t('label', 'Document Series'),
			'document_number' => Yii::t('label', 'Document Number'),
			'issued_at' => Yii::t('label', 'Issued At'),
			'due_at' => Yii::t('label', 'Due At'),
			'paid_at' => Yii::t('label', 'Paid At'),
			'payment_method' => Yii::t('label', 'Payment Method'),
			'payment_processor' => Yii::t('label', 'Payment Processor'),
			'amount' => Yii::t('label', 'Amount'),
			'currency' => Yii::t('label', 'Currency'),
            'vat' => Yii::t('label', 'VAT'),
			'exchange_rate' => Yii::t('label', 'Exchange Rate'),
			'multiplier' => Yii::t('label', 'Multiplier'),
			'e_invoice_upload_id' => Yii::t('label', 'e-Invoice Upload ID'),
			'e_invoice_download_id' => Yii::t('label', 'e-Invoice Download ID'),
			'e_invoice_status' => Yii::t('label', 'e-Invoice Status'),
			'e_invoice_error' => Yii::t('label', 'e-Invoice Error'),
			'e_invoice_sent_at' => Yii::t('label', 'e-Invoice Sent At'),
			'details' => Yii::t('label', 'Details'),
			'type' => Yii::t('label', 'Type'),
			'created_by' => Yii::t('label', 'Created By'),
			'updated_by' => Yii::t('label', 'Updated By'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
			'status' => Yii::t('label', 'Status'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getParent()
	{
		return $this->hasOne(Invoice::class, ['id' => 'parent_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCompany()
	{
		return $this->hasOne(Company::class, ['id' => 'company_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriber()
	{
		return $this->hasOne(Subscriber::class, ['id' => 'subscriber_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getInvoiceHasTemplates()
	{
		return $this->hasMany(InvoiceHasTemplate::class, ['invoice_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getTemplates()
	{
		return $this->hasMany(Template::class, ['id' => 'template_id'])->viaTable('{{%invoice_has_template}}', ['invoice_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getItems()
	{
		return $this->hasMany(Item::class, ['invoice_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCreator()
	{
		return $this->hasOne(User::class, ['id' => 'created_by']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUpdater()
	{
		return $this->hasOne(User::class, ['id' => 'updated_by']);
	}

	/**
	 * {@inheritdoc}
	 *
	 * A new invoice gets the default document series of its type (fiscal invoice or
	 * proforma) and the next number in that series, so fiscal invoices and proformas
	 * are numbered independently: X 00003 is followed by X 00004 for the same type.
	 */
	public function beforeSave($insert)
	{
		if ($insert && (empty($this->document_series) || empty($this->document_number))) {
			$this->assignDocumentSeriesAndNumber();
		}

		return parent::beforeSave($insert);
	}

	/**
	 * Gets the document series type that matches this invoice type: the fiscal
	 * invoice series for fiscal invoices, the proforma series for proformas.
	 *
	 * @return int
	 */
	public function getDocumentSeriesType()
	{
		return $this->type == self::TYPE_PROFORMA ? DocumentSeries::TYPE_PROFORM : DocumentSeries::TYPE_INVOICE;
	}

	/**
	 * Assigns the default document series of this invoice type and the next document
	 * number in that series. An explicitly set series is kept and only the number is
	 * computed for it.
	 *
	 * @throws \Exception when no active document series exists for the invoice type
	 */
	public function assignDocumentSeriesAndNumber()
	{
		if (empty($this->document_series)) {
			$documentSeries = DocumentSeries::findDefaultDocumentSeries(true, $this->getDocumentSeriesType());
			if (!$documentSeries) {
				throw new \Exception(Yii::t('common', 'There is no document series for {type}.', [
					'type' => static::getTypeLabels()[$this->type] ?? $this->type,
				]));
			}
			$this->document_series = $documentSeries->name;
		}
		if (empty($this->document_number)) {
			$this->document_number = DocumentSeries::getNextDocumentNumber($this->document_series, $this->type);
		}
	}

	/**
	 * Gets the zero filled documentSeriesNumber.
	 *
	 * @return string
	 */
	public function getDocumentSeriesNumber()
	{
		return trim($this->document_series . ' ' . str_pad($this->document_number, 5, 0, STR_PAD_LEFT));
	}

	/**
	 * Gets the totalAmount.
	 *
	 * @return float|int
	 */
	public function getTotalAmount()
	{
		$totalAmount = 0;
		if (!$this->items) {
			return $totalAmount;
		}
		foreach ($this->items as $item) {
			$totalAmount += (double) $item->price;
		}
		return $totalAmount;
	}

	/**
	 * Gets the sortCodeValues.
	 *
	 * @param bool $asHtml Flag that indicates if HTML code should be rendered.
	 * @return array
	 */
	public function getShortCodeValues($asHtml = false)
	{
		try {
			$details = $this->getUnserializedValue('details');
			$company = $details['merchant'];
			$client = $details['client'];

			$shortCodeValues = [
				'{{APP_NAME}}' => Yii::$app->name,
				'{{APP_URL}}' => Url::to(['/site/index'], true, '@frontend'),
				'{{APP_LOGO_URL}}' => Url::to('@uploads/' . Yii::$app->settings->get('appLogo'), true) ?: Url::to('@frontend/web/img/logo.png', true),
				'{{APP_LOGO_ALT_URL}}' => Url::to('@uploads/' . Yii::$app->settings->get('appLogoAlt'), true) ?: Url::to('@frontend/web/img/logo-alt.png', true),
				'{{COMPANY_NAME}}' => $company['name'] ?: '&mdash;',
				'{{COMPANY_TIN}}' => $company['tin'] ?: '&mdash;',
				'{{COMPANY_REG_NO}}' => $company['registration_number'] ?: '&mdash;',
				'{{COMPANY_LEGAL_REPRESENTATIVE}}' => $company['legal_representative'] ?: '&mdash;',
				'{{COMPANY_EMAIL}}' => $company['email'] ?: '&mdash;',
				'{{COMPANY_PHONE}}' => $company['phone'] ?: '&mdash;',
				'{{COMPANY_FAX}}' => $company['fax'] ?: '&mdash;',
				'{{COMPANY_ADDRESS}}' => $company['address'] ?: '&mdash;',
				'{{DOCUMENT_NUMBER}}' => $this->getDocumentSeriesNumber(),
				'{{DOCUMENT_DATE}}' => Yii::$app->formatter->asDatetime($this->issued_at),
				'{{DUE_AT}}' => Yii::$app->formatter->asDatetime($this->due_at),
				'{{PAYMENT_METHOD}}' => PaymentMetadata::getPaymentMethodLabels()[$this->payment_method] ?: '&mdash;',
				'{{PAYMENT_PROCESSOR}}' => PaymentMetadata::getPaymentProcessorLabels()[$this->payment_processor] ?: '&mdash;',
				'{{TOTAL_AMOUNT}}' => Yii::$app->formatter->asCurrency($this->getTotalAmount(), $this->currency),
				'{{CLIENT_NAME}}' => $client['name'] ?: '&mdash;',
				'{{CLIENT_PIN}}' => $client['pin'] ?: '&mdash;',
				'{{CLIENT_TIN}}' => $client['tin'] ?: '&mdash;',
				'{{CLIENT_REG_NO}}' => $client['registration_number'] ?: '&mdash;',
				'{{CLIENT_LEGAL_REPRESENTATIVE}}' => $client['legal_representative'] ?: '&mdash;',
				'{{CLIENT_EMAIL}}' => $client['email'] ?: '&mdash;',
				'{{CLIENT_PHONE}}' => $client['phone'] ?: '&mdash;',
				'{{CLIENT_FAX}}' => $client['fax'] ?: '&mdash;',
				'{{CLIENT_ADDRESS}}' => $client['address'] ?: '&mdash;',
			];

			if ($asHtml === true) {
				$shortCodeValues['{{ITEMS_LIST}}'] = Yii::$app->controller->renderPartial('@backend/modules/subscriber/views/invoice/_items-list', ['model' => $this]);
			} else {
				$shortCodeValues['{{ITEMS_LIST}}'] = implode(', ', array_map(function (Item $item) {
					$itemDetails = $item->getUnserializedValue('details');
					return $item->subscription->formattedName . ' (' . Yii::$app->formatter->asDatetime($itemDetails['start_at']) . ' - ' . Yii::$app->formatter->asDatetime($itemDetails['end_at']) . ')';
				}, $this->getItems()->active()->deleted(false)->all()));
			}

			return $shortCodeValues;
		} catch (\Exception $e) {
			return [];
		}
	}

	public static function getProformPaidInvoices($subscription_id = null)
	{
		$invoices = Invoice::find()
			->select(['i.*'])
			->alias('i')
			->joinWith([
				'items itm' => function (ActiveQuery $query) {
					$query->andWhere([
						'itm.deleted' => Item::NO,
					]);
				},
			])
			->andWhere([
				'i.type' => Invoice::TYPE_INVOICE,
				'i.status' => Invoice::STATUS_PAID,
			])
			->andWhere([
				'IS NOT', 'i.parent_id', null
			])
			->orderBy(['i.issued_at' => SORT_DESC]);
		if ($subscription_id) {
			$invoices->andWhere([
				'itm.item_id' => $subscription_id,
				'itm.type' => Item::TYPE_SUBSCRIPTION,
			]);
		}
		return $invoices->all();
	}

	/**
	 * Gets the merchant details.
	 *
	 * @return array
	 */
	public function getMerchantDetails()
	{
		$contactSettings = Yii::$app->settings->getCategory('contact');

		return [
			'name' => $contactSettings['company'],
			'tin' => $contactSettings['tin'],
			'registration_number' => $contactSettings['registrationNumber'],
			'legal_representative' => implode(' ', array_filter([
				$contactSettings['firstName'],
				$contactSettings['middleName'],
				$contactSettings['lastName'],
			])),
			'email' => $contactSettings['email'],
			'phone' => implode(', ', array_filter([
				$contactSettings['mobilePhone'],
				$contactSettings['fixedPhone'],
			])),
			'fax' => $contactSettings['fax'],
			'address' => implode(', ', array_filter([
				$contactSettings['streetName'],
				$contactSettings['streetNumber'],
			])),
			'zip_code' => $contactSettings['zipCode'],
			'locality' => $contactSettings['locality'],
			'county' => $contactSettings['county'],
			'country' => $contactSettings['country'],
		];
	}

	/**
	 * Gets the client details.
	 *
	 * @return array
	 */
	public function getClientDetails()
	{
		if ($company = $this->company) {
			return [
				'name' => $company->name,
				'legal_representative' => $company->representativeFullName,
				'tin' => $company->tin,
				'registration_number' => $company->registration_number,
				'email' => $company->email,
				'phone' => $company->phone,
				'fax' => $company->fax,
				'address' => $company->fullAddress,
			];
		}

		$subscriber = $this->subscriber;
		$user = $subscriber->user;

		return [
			'name' => $user->fullName,
			'pin' => $subscriber->pin,
			'email' => $user->email,
			'phone' => $user->phone,
			'address' => $subscriber->fullAddress,
		];
	}

	/**
	 * Model status labels.
	 *
	 * @return array
	 */
	public static function getStatusLabels()
	{
		return [
			self::STATUS_UNPAID => [
				'label' => Yii::t('label', 'Unpaid'),
				'color' => 'danger',
			],
			self::STATUS_PAID => [
				'label' => Yii::t('label', 'Paid'),
				'color' => 'success',
			],
		];
	}

	/**
	 * Model e-invoice status labels.
	 *
	 * @return array
	 */
	public static function getEStatusLabels()
	{
		return [
			self::E_STATUS_NOT_SENT => [
				'label' => Yii::t('label', 'Not sent'),
				'color' => 'default',
			],
			self::E_STATUS_SENT => [
				'label' => Yii::t('label', 'Sent'),
				'color' => 'warning',
			],
			self::E_STATUS_PENDING => [
				'label' => Yii::t('label', 'Pending'),
				'color' => 'info',
			],
			self::E_STATUS_PROCESSED => [
				'label' => Yii::t('label', 'Processed'),
				'color' => 'success',
			],
			self::E_STATUS_UNPROCESSED => [
				'label' => Yii::t('label', 'Unprocessed'),
				'color' => 'danger',
			],
			self::E_STATUS_REJECTED => [
				'label' => Yii::t('label', 'Rejected'),
				'color' => 'danger',
			],
		];
	}

	/**
	 * Model type labels.
	 *
	 * @return array
	 */
	public static function getTypeLabels()
	{
		return [
			self::TYPE_INVOICE => Yii::t('label', 'Invoice'),
			self::TYPE_PROFORMA => Yii::t('label', 'Proform'),
			self::TYPE_RECTIFY => Yii::t('label', 'Rectify'),
		];
	}

	public static function unitPrice($price, $quantity = 1)
	{
		$value = $price / ($quantity ?: 1);
		return round($value, 2);
	}

	public static function vatValue($price, $vat = 0)
	{
		$value = (1 - 1 / (1 + $vat / 100)) * $price;
		return NumberHelper::toNumber($value);
	}

	public function getSubtotal()
	{
		$vat = $this->vat ?: 0;
		$value = (1 - (1 - 1 / (1 + $vat / 100))) * $this->amount;
		return NumberHelper::toNumber($value);
	}

	public function getVatValue()
	{
		$value = $this->amount - $this->getSubtotal();
		return round($value, 2);
	}
}
