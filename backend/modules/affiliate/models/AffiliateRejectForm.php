<?php

namespace backend\modules\affiliate\models;

use Yii;
use yii\base\Model;

/**
 * The reason an affiliate application is rejected (shown to the applicant and e-mailed).
 */
class AffiliateRejectForm extends Model
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
