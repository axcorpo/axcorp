<?php

namespace common\models;

use common\helpers\NumberHelper;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%item}}".
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $item_id
 * @property int $type
 * @property int $quantity
 * @property string $price
 * @property string $currency
 * @property string $exchange_rate
 * @property int $multiplier
 * @property string $details
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property Invoice $invoice
 * @property Subscription $subscription
 * @property User $creator
 * @property User $updater
 */
class Item extends UuidActiveRecord
{
	const TYPE_SUBSCRIPTION = 1;

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%item}}';
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
			[['invoice_id', 'status'], 'required'],
			[['invoice_id', 'item_id', 'type', 'quantity', 'multiplier', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['price', 'exchange_rate'], 'number'],
			[['details'], 'string'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['currency'], 'string', 'max' => 3],
			[['invoice_id'], 'exist', 'skipOnError' => true, 'targetClass' => Invoice::class, 'targetAttribute' => ['invoice_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'invoice_id' => Yii::t('label', 'Invoice ID'),
			'item_id' => Yii::t('label', 'Item ID'),
			'type' => Yii::t('label', 'Type'),
			'quantity' => Yii::t('label', 'Quantity'),
			'price' => Yii::t('label', 'Price'),
			'currency' => Yii::t('label', 'Currency'),
			'exchange_rate' => Yii::t('label', 'Exchange Rate'),
			'multiplier' => Yii::t('label', 'Multiplier'),
			'details' => Yii::t('label', 'Details'),
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
	public function getInvoice()
	{
		return $this->hasOne(Invoice::class, ['id' => 'invoice_id']);
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
	 * Gets the Subscription model.
	 *
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscription()
	{
		return $this->hasOne(Subscription::class, ['id' => 'item_id']);
	}

	public function getGrossUnitPrice()
	{
		return Invoice::unitPrice($this->price);
	}

	public function getNetUnitPrice()
	{
		$vat = $this->invoice->vat ?: 0;
		$unitPrice = $this->getGrossUnitPrice();
		$value = (1 - (1 - 1 / (1 + $vat / 100))) * $unitPrice;
		return round($value, 2);
	}

	public function getNetPrice()
	{
		$vat = $this->invoice->vat ?: 0;
		$value = (1 - (1 - 1 / (1 + $vat / 100))) * $this->price;
		return NumberHelper::toNumber($value);
	}

	public function getVatValue()
	{
		$value = $this->price - $this->getNetPrice();
		return round($value, 2);
	}
}
