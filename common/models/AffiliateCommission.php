<?php

namespace common\models;

use DateTime;
use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "{{%affiliate_commission}}": the affiliate's share of
 * one master invoice of a subscriber they referred. Created only by
 * AffiliateCommissionService (the `affiliate/compute-commissions` cron), one row per
 * invoice (unique invoice_id).
 *
 * Lifecycle: PENDING (hold period, `payable_at` in the future) → PAYABLE → PAID (through
 * an AffiliatePayout), or CANCELLED (the invoice was deleted/voided before being paid
 * out). A rectify (storno) invoice produces its own commission with a negative
 * `base_amount`/`amount`, which offsets the original inside the next payout.
 *
 * `base_amount` is the invoice's NET amount (VAT excluded); `percentage` is the
 * affiliate's at computation time. `invoice_id` and `subscriber_id` are kept as plain
 * data (no FK), so the commission outlives the rows it was computed from.
 *
 * Not soft-deletable: a commission is never removed, only cancelled.
 *
 * @property string $id
 * @property string $affiliate_id
 * @property string $invoice_id
 * @property string|null $subscriber_id
 * @property string|null $affiliate_payout_id
 * @property int|null $type
 * @property string $base_amount
 * @property string|null $currency
 * @property string $percentage
 * @property string $amount
 * @property int $status
 * @property string|null $earned_at
 * @property string|null $payable_at
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property Affiliate $affiliate
 * @property Invoice|null $invoice
 * @property Subscriber|null $subscriber
 * @property AffiliatePayout|null $payout
 */
class AffiliateCommission extends UuidActiveRecord
{
	const STATUS_CANCELLED = 0;
	const STATUS_PENDING = 1;
	const STATUS_PAYABLE = 2;
	const STATUS_PAID = 3;

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%affiliate_commission}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function behaviors()
	{
		return [
			'TimestampBehavior' => [
				'class' => TimestampBehavior::class,
				'value' => function () {
					return (new DateTime)->format('Y-m-d H:i:s');
				},
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['affiliate_id', 'invoice_id', 'status'], 'required'],
			[['affiliate_id', 'invoice_id', 'subscriber_id', 'affiliate_payout_id', 'type', 'status'], 'integer'],
			[['base_amount', 'percentage', 'amount'], 'number'],
			[['currency'], 'string', 'max' => 3],
			[['status'], 'in', 'range' => array_keys(static::getStatusLabels())],
			[['earned_at', 'payable_at', 'created_at', 'updated_at'], 'safe'],
			[['invoice_id'], 'unique'],
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
			'invoice_id' => Yii::t('label', 'Invoice'),
			'subscriber_id' => Yii::t('label', 'Subscriber'),
			'affiliate_payout_id' => Yii::t('label', 'Payout'),
			'type' => Yii::t('label', 'Type'),
			'base_amount' => Yii::t('label', 'Base Amount'),
			'currency' => Yii::t('label', 'Currency'),
			'percentage' => Yii::t('label', 'Percentage'),
			'amount' => Yii::t('label', 'Commission'),
			'status' => Yii::t('label', 'Status'),
			'earned_at' => Yii::t('label', 'Earned At'),
			'payable_at' => Yii::t('label', 'Payable At'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
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
	public function getInvoice()
	{
		return $this->hasOne(Invoice::class, ['id' => 'invoice_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getSubscriber()
	{
		return $this->hasOne(Subscriber::class, ['id' => 'subscriber_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getPayout()
	{
		return $this->hasOne(AffiliatePayout::class, ['id' => 'affiliate_payout_id']);
	}

	/**
	 * Whether the commission is a storno offset (negative).
	 *
	 * @return bool
	 */
	public function getIsRectify()
	{
		return (int) $this->type === Invoice::TYPE_RECTIFY;
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
			self::STATUS_PENDING => [
				'label' => Yii::t('label', 'Pending'),
				'color' => 'warning',
			],
			self::STATUS_PAYABLE => [
				'label' => Yii::t('label', 'Payable'),
				'color' => 'info',
			],
			self::STATUS_PAID => [
				'label' => Yii::t('label', 'Paid'),
				'color' => 'success',
			],
		];
	}
}
