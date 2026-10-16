<?php

namespace frontend\modules\account\models;

use common\helpers\DateHelper;
use common\helpers\PrivateFileHelper;
use common\models\AffiliatePayout;
use common\services\AffiliatePayoutService;
use Yii;
use yii\base\Model;
use yii\base\UserException;
use yii\web\UploadedFile;

/**
 * The affiliate's invoice for one of their payouts: number, date and the document
 * (PDF/JPG/PNG). Storage and the state change are AffiliatePayoutService::attachInvoice();
 * this form only validates the three fields it renders.
 */
class AffiliateInvoiceForm extends Model
{
	/**
	 * @var string
	 */
	public $invoice_number;

	/**
	 * @var string the `datetimeFormat` setting as the picker sends it, "Y-m-d H:i:s" once validated
	 */
	public $invoice_date;

	/**
	 * @var UploadedFile|null
	 */
	public $invoiceFile;

	/**
	 * @var bool false copies the temp file instead of moving it (tests, non-HTTP uploads)
	 */
	public $deleteTempFile = true;

	/**
	 * @var AffiliatePayout the payout, already checked to belong to the signed-in affiliate
	 */
	private $_payout;

	/**
	 * @param AffiliatePayout $payout
	 * @param array $config
	 */
	public function __construct(AffiliatePayout $payout, $config = [])
	{
		$this->_payout = $payout;
		parent::__construct($config);
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['invoice_number', 'invoice_date'], 'trim'],
			[['invoice_number', 'invoice_date'], 'required'],
			[['invoice_number'], 'string', 'max' => 255],
			// Normalize the picker's format; garbage is left for the datetime check to reject.
			[['invoice_date'], 'filter', 'filter' => function ($value) {
				return DateHelper::parseDateTime($value) ?: $value;
			}],
			[['invoice_date'], 'datetime', 'format' => 'php:Y-m-d H:i:s'],
			// An invoice is issued before it is uploaded (today counts whole, as the picker's maxDate).
			[['invoice_date'], 'compare', 'compareValue' => date('Y-m-d 23:59:59'), 'operator' => '<=', 'type' => 'string'],
			PrivateFileHelper::fileRule('invoiceFile', 1, false),
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'invoice_number' => Yii::t('label', 'Invoice Number'),
			'invoice_date' => Yii::t('label', 'Invoice Date'),
			'invoiceFile' => Yii::t('label', 'Invoice'),
		];
	}

	/**
	 * {@inheritdoc}
	 *
	 * Populates the file before validation, so the file rule applies to what is stored.
	 */
	public function load($data, $formName = null)
	{
		$result = parent::load($data, $formName);
		if (!empty($data)) {
			$this->invoiceFile = UploadedFile::getInstance($this, 'invoiceFile');
		}
		return $result;
	}

	/**
	 * Whether the payout is waiting for the affiliate's invoice (new, or after a rejection).
	 *
	 * @return bool
	 */
	public function getCanUpload()
	{
		return (int) $this->_payout->status === AffiliatePayout::STATUS_OPEN
			&& (int) $this->_payout->deleted === AffiliatePayout::NO;
	}

	/**
	 * Validates and attaches the invoice to the payout.
	 *
	 * @return bool
	 */
	public function save()
	{
		if (!$this->getCanUpload()) {
			$this->addError('invoiceFile', Yii::t('error', 'This payout is not awaiting an invoice.'));
			return false;
		}
		if (!$this->validate()) {
			return false;
		}
		try {
			(new AffiliatePayoutService())->attachInvoice($this->_payout, $this->invoiceFile, $this->invoice_number, $this->invoice_date, $this->deleteTempFile);
		} catch (UserException $e) {
			$this->addError('invoiceFile', $e->getMessage());
			return false;
		}
		return true;
	}
}
