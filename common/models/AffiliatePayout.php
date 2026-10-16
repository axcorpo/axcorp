<?php

namespace common\models;

use common\helpers\PrivateFileHelper;
use DateTime;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%affiliate_payout}}": one payment to an affiliate,
 * grouping PAYABLE commissions of a single currency. One affiliate invoice per payout.
 *
 * Lifecycle (all transitions go through common\services\AffiliatePayoutService):
 *
 *     OPEN ──attachInvoice──▶ INVOICE_RECEIVED ──acceptInvoice + markPaid──▶ PAID
 *       ▲                          │
 *       └────────rejectInvoice─────┘          (OPEN / INVOICE_RECEIVED ──cancel──▶ CANCELLED)
 *
 * `invoice_status` tracks the review of the affiliate's invoice separately (NULL none,
 * PENDING, ACCEPTED, REJECTED + `invoice_rejection_note`). `invoice_file` and
 * `proof_files` hold stored names inside the PRIVATE uploads (see [[getFileSubdir()]]);
 * they are streamed by a controller after an ownership/permission check, never linked.
 *
 * @property string $id
 * @property string $affiliate_id
 * @property string $amount
 * @property string $currency
 * @property int $status
 * @property string|null $invoice_number
 * @property string|null $invoice_date
 * @property string|null $invoice_file
 * @property int|null $invoice_status
 * @property string|null $invoice_rejection_note
 * @property string|null $proof_files serialized list of stored names
 * @property string|null $notes
 * @property string|null $paid_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property int $deleted
 *
 * @property Affiliate $affiliate
 * @property AffiliateCommission[] $commissions
 * @property User $creator
 * @property User $updater
 * @property string[] $proofFileList
 */
class AffiliatePayout extends UuidActiveRecord
{
	const STATUS_CANCELLED = 0;
	/** Created, awaiting the affiliate's invoice (also after a rejected invoice). */
	const STATUS_OPEN = 1;
	const STATUS_INVOICE_RECEIVED = 2;
	const STATUS_PAID = 3;

	const INVOICE_STATUS_PENDING = 1;
	const INVOICE_STATUS_ACCEPTED = 2;
	const INVOICE_STATUS_REJECTED = 3;

	/**
	 * The private-uploads directory of all payout files.
	 */
	const FILE_DIRECTORY = 'affiliate-payout';

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%affiliate_payout}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function behaviors()
	{
		return [
			'BlameableBehavior' => [
				'class' => BlameableBehavior::class,
			],
			'TimestampBehavior' => [
				'class' => TimestampBehavior::class,
				'value' => function () {
					return (new DateTime)->format('Y-m-d H:i:s');
				},
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
			[['affiliate_id', 'currency', 'status'], 'required'],
			[['affiliate_id', 'created_by', 'updated_by', 'status', 'invoice_status', 'deleted'], 'integer'],
			[['amount'], 'number'],
			[['currency'], 'string', 'max' => 3],
			[['invoice_number', 'invoice_file'], 'string', 'max' => 255],
			[['invoice_rejection_note', 'proof_files', 'notes'], 'string'],
			[['invoice_date'], 'date', 'format' => 'php:Y-m-d H:i:s'],
			[['status'], 'in', 'range' => array_keys(static::getStatusLabels())],
			[['invoice_status'], 'in', 'range' => array_keys(static::getInvoiceStatusLabels())],
			[['paid_at', 'created_at', 'updated_at'], 'safe'],
			[['affiliate_id'], 'exist', 'skipOnError' => true, 'targetClass' => Affiliate::class, 'targetAttribute' => ['affiliate_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'affiliate_id' => Yii::t('label', 'Affiliate'),
			'amount' => Yii::t('label', 'Amount'),
			'currency' => Yii::t('label', 'Currency'),
			'status' => Yii::t('label', 'Status'),
			'invoice_number' => Yii::t('label', 'Invoice Number'),
			'invoice_date' => Yii::t('label', 'Invoice Date'),
			'invoice_file' => Yii::t('label', 'Invoice'),
			'invoice_status' => Yii::t('label', 'Invoice Status'),
			'invoice_rejection_note' => Yii::t('label', 'Rejection Reason'),
			'proof_files' => Yii::t('label', 'Payment Proofs'),
			'notes' => Yii::t('label', 'Notes'),
			'paid_at' => Yii::t('label', 'Paid At'),
			'created_by' => Yii::t('label', 'Created By'),
			'updated_by' => Yii::t('label', 'Updated By'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getAffiliate()
	{
		return $this->hasOne(Affiliate::class, ['id' => 'affiliate_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getCommissions()
	{
		return $this->hasMany(AffiliateCommission::class, ['affiliate_payout_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getCreator()
	{
		return $this->hasOne(User::class, ['id' => 'created_by']);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getUpdater()
	{
		return $this->hasOne(User::class, ['id' => 'updated_by']);
	}

	/**
	 * The stored names of the payment proofs (bank statements) the admin attached.
	 *
	 * @return string[]
	 */
	public function getProofFileList()
	{
		return array_values(array_filter((array) $this->getUnserializedValue('proof_files', []), 'is_string'));
	}

	/**
	 * Replaces the proof list (stored names).
	 *
	 * @param string[] $names
	 * @return $this
	 */
	public function setProofFileList(array $names)
	{
		$this->proof_files = $names ? serialize(array_values(array_unique($names))) : null;
		return $this;
	}

	/**
	 * Whether `$name` is one of this payout's files (the invoice or a proof) — the check a
	 * download action makes before resolving the path, so a request can only reach the
	 * files recorded on the payout it is authorized for.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function hasFile($name)
	{
		if (!is_string($name) || $name === '') {
			return false;
		}
		return $name === $this->invoice_file || in_array($name, $this->getProofFileList(), true);
	}

	/**
	 * The private-uploads subdirectory of this payout's files.
	 *
	 * @return string
	 */
	public function getFileSubdir()
	{
		return static::FILE_DIRECTORY . '/' . $this->uuid;
	}

	/**
	 * The absolute path of one of this payout's files, null when it is not one of them or
	 * is missing on disk. Pair it with Yii::$app->response->sendFile() after checking that
	 * the viewer owns the payout or holds `manageAffiliatePayout`.
	 *
	 * @param string $name
	 * @return string|null
	 */
	public function getFilePath($name)
	{
		return $this->hasFile($name) ? PrivateFileHelper::resolve($this->getFileSubdir(), $name) : null;
	}

	/**
	 * Whether the payout can still change (invoice, proofs, cancel).
	 *
	 * @return bool
	 */
	public function getIsOpen()
	{
		return in_array((int) $this->status, [static::STATUS_OPEN, static::STATUS_INVOICE_RECEIVED], true)
			&& (int) $this->deleted === static::NO;
	}

	/**
	 * Model status labels.
	 *
	 * @return array
	 */
	public static function getStatusLabels()
	{
		return [
			self::STATUS_CANCELLED => [
				'label' => Yii::t('label', 'Cancelled'),
				'color' => 'default',
			],
			self::STATUS_OPEN => [
				'label' => Yii::t('label', 'Awaiting invoice'),
				'color' => 'warning',
			],
			self::STATUS_INVOICE_RECEIVED => [
				'label' => Yii::t('label', 'Invoice received'),
				'color' => 'info',
			],
			self::STATUS_PAID => [
				'label' => Yii::t('label', 'Paid'),
				'color' => 'success',
			],
		];
	}

	/**
	 * Labels of the affiliate invoice review.
	 *
	 * @return array
	 */
	public static function getInvoiceStatusLabels()
	{
		return [
			self::INVOICE_STATUS_PENDING => [
				'label' => Yii::t('label', 'Pending review'),
				'color' => 'warning',
			],
			self::INVOICE_STATUS_ACCEPTED => [
				'label' => Yii::t('label', 'Accepted'),
				'color' => 'success',
			],
			self::INVOICE_STATUS_REJECTED => [
				'label' => Yii::t('label', 'Rejected'),
				'color' => 'danger',
			],
		];
	}
}
