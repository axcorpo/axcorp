<?php

namespace backend\modules\affiliate\models;

use common\helpers\DateHelper;
use Yii;
use yii\base\Model;

/**
 * Marks a payout as paid at a moment (DateTimePicker in the `datetimeFormat` setting). By default the affiliate's invoice must have been
 * accepted; `withoutInvoice` lets the admin settle anyway (e.g. an individual who issues
 * another kind of document) — AffiliatePayoutService::markPaid($requireAcceptedInvoice).
 */
class PayoutPaidForm extends Model
{
	/**
	 * @var string the `datetimeFormat` setting as the picker sends it, "Y-m-d H:i:s" once validated
	 */
	public $paid_at;

	/**
	 * @var bool
	 */
	public $withoutInvoice = false;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->paid_at = date('Y-m-d H:i:s');
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['paid_at'], 'required'],
			// Normalize the picker's format; garbage is left for the datetime check to reject.
			[['paid_at'], 'filter', 'filter' => function ($value) {
				return DateHelper::parseDateTime($value) ?: $value;
			}],
			[['paid_at'], 'datetime', 'format' => 'php:Y-m-d H:i:s'],
			// A payment is recorded after it happened (today counts whole, as the picker's maxDate).
			[['paid_at'], 'compare', 'compareValue' => date('Y-m-d 23:59:59'), 'operator' => '<=', 'type' => 'string'],
			[['withoutInvoice'], 'boolean'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return [
			static::SCENARIO_DEFAULT => ['paid_at', 'withoutInvoice'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'paid_at' => Yii::t('label', 'Paid At'),
			'withoutInvoice' => Yii::t('label', 'Mark as paid without an accepted invoice'),
		];
	}

	/**
	 * The payment moment for AffiliatePayoutService::markPaid() (after validation).
	 *
	 * @return string Y-m-d H:i:s
	 */
	public function getPaidAt()
	{
		return DateHelper::parseDateTime($this->paid_at);
	}
}
