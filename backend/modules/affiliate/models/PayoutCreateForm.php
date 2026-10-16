<?php

namespace backend\modules\affiliate\models;

use common\models\Affiliate;
use common\models\AffiliatePayout;
use common\services\AffiliatePayoutService;
use Yii;
use yii\base\Model;
use yii\base\UserException;

/**
 * Creates a payout from an affiliate's PAYABLE commissions in one currency
 * (AffiliatePayoutService::createPayout() does the grouping and the checks).
 */
class PayoutCreateForm extends Model
{
	/**
	 * @var string affiliate uuid
	 */
	public $affiliate_id;

	/**
	 * @var string
	 */
	public $currency;

	/**
	 * @var string
	 */
	public $notes;

	/**
	 * @var AffiliatePayout|null the payout created by [[create()]]
	 */
	public $payout;

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['affiliate_id', 'currency'], 'required'],
			[['affiliate_id'], \common\validators\UuidValidator::class, 'allowBinary' => false],
			[['currency'], 'match', 'pattern' => '/^[A-Z]{3}$/'],
			[['notes'], 'string', 'max' => 2000],
			[['notes'], 'trim'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return [
			static::SCENARIO_DEFAULT => ['affiliate_id', 'currency', 'notes'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'affiliate_id' => Yii::t('label', 'Affiliate'),
			'currency' => Yii::t('label', 'Currency'),
			'notes' => Yii::t('label', 'Notes'),
		];
	}

	/**
	 * The (non-deleted) affiliate selected.
	 *
	 * @return Affiliate|null
	 */
	public function getAffiliate()
	{
		if (!\common\helpers\UuidHelper::isValid($this->affiliate_id)) {
			return null;
		}
		return Affiliate::find()->andWhere(['id' => $this->affiliate_id, 'deleted' => Affiliate::NO])->one();
	}

	/**
	 * @return bool
	 */
	public function create()
	{
		if (!$this->validate()) {
			return false;
		}
		if (!($affiliate = $this->getAffiliate())) {
			$this->addError('affiliate_id', Yii::t('yii', '{attribute} is invalid.', ['attribute' => $this->getAttributeLabel('affiliate_id')]));
			return false;
		}
		try {
			$this->payout = (new AffiliatePayoutService())->createPayout($affiliate, $this->currency, $this->notes ?: null);
			return true;
		} catch (UserException $e) {
			$this->addError('currency', $e->getMessage());
			return false;
		}
	}
}
