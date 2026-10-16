<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%exchange_rate}}".
 *
 * @property int $id
 * @property string $date
 * @property string $currency
 * @property string $rate_value
 * @property int $multiplier
 * @property string $country_code
 * @property int $deleted
 */
class ExchangeRate extends UuidActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%exchange_rate}}';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['date'], 'safe'],
			[['date'], 'default'],
			[['rate_value'], 'number'],
			[['multiplier', 'deleted'], 'integer'],
			[['currency'], 'string', 'max' => 3],
			[['country_code'], 'string', 'max' => 2],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'date' => Yii::t('label', 'Date'),
			'currency' => Yii::t('label', 'Currency'),
			'rate_value' => Yii::t('label', 'Rate Value'),
			'multiplier' => Yii::t('label', 'Multiplier'),
			'country_code' => Yii::t('label', 'Country Code'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * Finds exchange rate by date and currency.
	 *
	 * @param string $currency
	 * @return yii\db\ActiveRecord|ExchangeRate
	 */
	public static function findLastByCurrency($currency)
	{
		return static::find()
			->where([
				'currency' => $currency,
				'deleted' => self::NO,
			])
			->orderBy(['id' => SORT_DESC])
			->limit(1)
			->one();
	}

	/**
	 * Finds exchange rate by date and currency.
	 * Falls back to last exchange rate found by currency.
	 *
	 * @param string $date
	 * @param string $currency
	 * @param bool $fallbackToLast
	 * @return yii\db\ActiveRecord|ExchangeRate
	 */
	public static function findOneByDateAndCurrency($date, $currency, $fallbackToLast = true)
	{
		$model = static::find()
			->where([
				'date' => $date,
				'currency' => $currency,
				'deleted' => self::NO,
			])
			->limit(1)
			->one();

		if ($fallbackToLast === true && $model === null) {
			$model = static::findLastByCurrency($currency);
		}

		return $model;
	}

	/**
	 * Finds exchange rate by date and currency.
	 * Falls back to last exchange rate found by currency.
	 *
	 * @param $amount
	 * @param string $date
	 * @param null $sourceCurrency
	 * @param null $targetCurrency
	 * @return yii\db\ActiveRecord|ExchangeRate
	 */
	public static function convert($amount, $date = null, $sourceCurrency = null, $targetCurrency = null)
	{
		$sourceCurrency = $sourceCurrency ?: Yii::$app->settings->get('currencyCode');
		$targetCurrency = $targetCurrency ?: Yii::$app->settings->get('currencyCode');
		if($sourceCurrency == $targetCurrency) {
			return $amount;
		} else {
			if ($sourceCurrency == 'RON' || $sourceCurrency == 'LEI') {
				$sourceExchangeRate = 1;
				$sourceExchangeMultiplier = 1;
			} elseif ($date) {
				$sourceExchange = ExchangeRate::findOne([
					'date' => $date,
					'currency' => $sourceCurrency
				]);
				if (!$sourceExchange) {
					$sourceExchange = ExchangeRate::find()
						->where(['currency' =>  $sourceCurrency])
						->orderBy(['date' => SORT_DESC])
						->limit(1)
						->one();
				}
				$sourceExchangeRate = (double)$sourceExchange['rate_value'];
				$sourceExchangeMultiplier = (double)$sourceExchange['multiplier'];
			} else {
				$sourceExchange = ExchangeRate::find()
					->where(['currency' =>  $sourceCurrency])
					->orderBy(['date' => SORT_DESC])
					->limit(1)
					->one();
				$sourceExchangeRate = (double)$sourceExchange['rate_value'];
				$sourceExchangeMultiplier = (double)$sourceExchange['multiplier'];
			}
			if ($targetCurrency == 'RON' || $targetCurrency == 'LEI') {
				$targetExchangeRate = 1;
				$targetExchangeMultiplier = 1;
			} elseif ($date) {
				$targetExchange = ExchangeRate::findOne([
					'date' => $date,
					'currency' => $targetCurrency
				]);
				if (!$targetExchange) {
					$targetExchange = ExchangeRate::find()
						->where(['currency' =>  $targetCurrency])
						->orderBy(['date' => SORT_DESC])
						->limit(1)
						->one();
				}
				$targetExchangeRate = (double)$targetExchange['rate_value'];
				$targetExchangeMultiplier = (double)$targetExchange['multiplier'];
			} else {
				$targetExchange = ExchangeRate::find()
					->where(['currency' =>  $targetCurrency])
					->orderBy(['date' => SORT_DESC])
					->limit(1)
					->one();
				$targetExchangeRate = (double)$targetExchange['rate_value'];
				$targetExchangeMultiplier = (double)$targetExchange['multiplier'];
			}
			$amount = round($amount * (($sourceExchangeRate / $sourceExchangeMultiplier) / ($targetExchangeRate / $targetExchangeMultiplier)), 2);
		}
		return $amount;
	}
}
