<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%payment_metadata}}".
 *
 * @property int $subscription_id
 * @property string $payer_id
 * @property string $card_id
 * @property string $payment_id
 * @property int $payment_method
 * @property int $payment_processor
 * @property int $recurring_payment
 * @property string $ip_address
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property Subscription $subscription
 * @property User $creator
 * @property User $updater
 */
class PaymentMetadata extends UuidActiveRecord
{
	const PAYMENT_METHOD_CASH = 1;
	const PAYMENT_METHOD_CARD = 2;
	const PAYMENT_METHOD_BANK = 3;

	const PAYMENT_PROCESSOR_STRIPE = 1;
	const PAYMENT_PROCESSOR_PAYPAL = 2;
	const PAYMENT_PROCESSOR_TWISPAY = 3;

	const PAYMENT_TYPE_PACKAGE = 1;
	const PAYMENT_TYPE_SUBSCRIPTION = 2;
	const PAYMENT_TYPE_INVOICE = 3;
	const PAYMENT_TYPE_FEATURE = 4;


	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%payment_metadata}}';
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
			[['subscription_id', 'status'], 'required'],
			[['subscription_id', 'payment_method', 'payment_processor', 'recurring_payment', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['payer_id', 'card_id', 'payment_id', 'ip_address'], 'string', 'max' => 255],
			[['subscription_id'], 'unique'],
			[['subscription_id'], 'exist', 'skipOnError' => true, 'targetClass' => Subscription::class, 'targetAttribute' => ['subscription_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'subscription_id' => Yii::t('label', 'Subscription ID'),
			'payer_id' => Yii::t('label', 'Payer ID'),
			'card_id' => Yii::t('label', 'Card ID'),
			'payment_id' => Yii::t('label', 'Payment ID'),
			'payment_method' => Yii::t('label', 'Payment Method'),
			'payment_processor' => Yii::t('label', 'Payment Processor'),
			'recurring_payment' => Yii::t('label', 'Recurring Payment'),
			'ip_address' => Yii::t('label', 'Ip Address'),
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
	public function getSubscription()
	{
		return $this->hasOne(Subscription::class, ['id' => 'subscription_id']);
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
	 * Model payment method labels.
	 *
	 * @return array
	 */
	public static function getPaymentMethodLabels()
	{
		return [
			self::PAYMENT_METHOD_CASH => Yii::t('label', 'Cash'),
			self::PAYMENT_METHOD_CARD => Yii::t('label', 'Card'),
			self::PAYMENT_METHOD_BANK => Yii::t('label', 'Bank'),
		];
	}

	/**
	 * Model payment processor labels.
	 *
	 * @return array
	 */
	public static function getPaymentProcessorLabels()
	{
		return [
			self::PAYMENT_PROCESSOR_STRIPE => 'Stripe',
			self::PAYMENT_PROCESSOR_PAYPAL => 'PayPal',
			self::PAYMENT_PROCESSOR_TWISPAY => 'Twispay',
		];
	}

	/**
	 * Model payment method labels.
	 *
	 * @return array
	 */
	public static function getPaymentTypeLabels()
	{
		return [
			self::PAYMENT_TYPE_PACKAGE => Yii::t('label', 'Package'),
			self::PAYMENT_TYPE_SUBSCRIPTION => Yii::t('label', 'Subscription'),
			self::PAYMENT_TYPE_INVOICE => Yii::t('label', 'Invoice'),
			self::PAYMENT_TYPE_FEATURE => Yii::t('label', 'Features'),
		];
	}
}
