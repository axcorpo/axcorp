<?php

namespace backend\modules\setting\models;

use common\models\AffiliateSettings;
use common\models\Setting;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * The `affiliate` settings group (read by common\models\AffiliateSettings): hold period
 * of new commissions, default percentage of new affiliates, the minimum payout and how
 * long the referral cookie lasts.
 * Saves exactly the keys of AffiliateSettings::DEFAULTS.
 */
class AffiliateSettingForm extends Setting
{
	/**
	 * @var int Days a commission stays pending before it becomes payable.
	 */
	public $holdDays;

	/**
	 * @var float Commission percentage proposed for new affiliates.
	 */
	public $defaultPercentage;

	/**
	 * @var float Smallest payable total (per currency) a payout can be created for.
	 */
	public $minimumPayout;

	/**
	 * @var int Days the referral cookie remembers an affiliate code before the signup.
	 */
	public $cookieDays;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->holdDays = AffiliateSettings::DEFAULTS[AffiliateSettings::HOLD_DAYS];
		$this->defaultPercentage = AffiliateSettings::DEFAULTS[AffiliateSettings::DEFAULT_PERCENTAGE];
		$this->minimumPayout = AffiliateSettings::DEFAULTS[AffiliateSettings::MINIMUM_PAYOUT];
		$this->cookieDays = AffiliateSettings::DEFAULTS[AffiliateSettings::COOKIE_DAYS];
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['holdDays', 'defaultPercentage', 'minimumPayout', 'cookieDays'], 'required'],
			[['holdDays'], 'integer', 'min' => 0, 'max' => 365],
			[['cookieDays'], 'integer', 'min' => 1, 'max' => 365],
			[['defaultPercentage'], 'number', 'min' => 0, 'max' => 100],
			[['minimumPayout'], 'number', 'min' => 0],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'holdDays' => Yii::t('label', 'Hold Period'),
			'defaultPercentage' => Yii::t('label', 'Default Commission Percentage'),
			'minimumPayout' => Yii::t('label', 'Minimum Payout'),
			'cookieDays' => Yii::t('label', 'Referral Cookie Lifetime'),
		]);
	}

	/**
	 * Only the affiliate settings are assignable.
	 *
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return [
			static::SCENARIO_DEFAULT => ['holdDays', 'defaultPercentage', 'minimumPayout', 'cookieDays'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function afterFind()
	{
		parent::afterFind();

		$values = (array) $this->getUnserializedValue('setting', []);
		foreach (array_keys(AffiliateSettings::DEFAULTS) as $key) {
			if (isset($values[$key]) && $values[$key] !== '') {
				$this->$key = $values[$key];
			}
		}
	}

	/**
	 * Saves the setting.
	 *
	 * @return bool|\yii\db\ActiveRecord
	 */
	public function saveModel()
	{
		if (!$this->validate()) {
			return null;
		}
		$this->name = AffiliateSettings::GROUP;
		$this->type = static::TYPE_APP;
		$this->status = static::STATUS_ACTIVE;
		$this->setSerializedValue('setting', [
			AffiliateSettings::HOLD_DAYS => (int) $this->holdDays,
			AffiliateSettings::DEFAULT_PERCENTAGE => (float) $this->defaultPercentage,
			AffiliateSettings::MINIMUM_PAYOUT => (float) $this->minimumPayout,
			AffiliateSettings::COOKIE_DAYS => (int) $this->cookieDays,
		], false);

		return $this->save() ? $this : null;
	}
}
