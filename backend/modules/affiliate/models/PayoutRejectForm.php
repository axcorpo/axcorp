<?php

namespace backend\modules\affiliate\models;

use Yii;
use yii\base\Model;

/**
 * The reason an affiliate's invoice is rejected (shown to the affiliate and e-mailed).
 */
class PayoutRejectForm extends Model
{
	/**
	 * @var string
	 */
	public $reason;

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['reason'], 'trim'],
			[['reason'], 'required'],
			[['reason'], 'string', 'max' => 2000],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return [
			static::SCENARIO_DEFAULT => ['reason'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'reason' => Yii::t('label', 'Rejection Reason'),
		];
	}
}
