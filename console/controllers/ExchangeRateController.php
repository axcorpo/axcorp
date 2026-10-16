<?php
namespace console\controllers;

use common\models\ExchangeRate;
use DateTime;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\httpclient\Client;

ini_set('memory_limit', '-1');

class ExchangeRateController extends Controller
{
	/**
	 * Sync exchange rates.
	 */
	public function actionSync()
	{
		$dbTransaction = Yii::$app->db->beginTransaction();
		try {
			$currentYear = (new DateTime)->format('Y');
			$startYear = 2005;
			if ($lastExchangeDate = ExchangeRate::find()->max('date')) {
				$lastExchangeDate = new DateTime($lastExchangeDate);
				$startYear = $lastExchangeDate->format('Y');
			}
			$count = 0;

			for ($year = $startYear; $year <= $currentYear; $year++) {
				$exchangeRates = $this->getExchangeRates($year, $lastExchangeDate);
				$count += count($exchangeRates);

				// batchInsert() bypasses the models — the BINARY(16) UUID v7 ids
				// must be generated here.
				$exchangeRates = array_map(function ($row) {
					array_unshift($row, \common\helpers\UuidHelper::uuid7Bytes());
					return $row;
				}, $exchangeRates);

				Yii::$app->db->createCommand()->batchInsert(
					ExchangeRate::tableName(),
					['id', 'date', 'currency', 'rate_value', 'multiplier', 'country_code'],
					$exchangeRates
				)->execute();
			}

			$dbTransaction->commit();
			$this->stdout("[" . date("Y-m-d H:i:s") . "] Processed {$count} exchange rate(s).");
			return ExitCode::OK;
		} catch (\Exception $e) {
			$dbTransaction->rollBack();
			$this->stderr($e->getMessage());
			return ExitCode::UNSPECIFIED_ERROR;
		}
	}

	/**
	 * Gets the exchange rates from a third party API.
	 *
	 * @param string|null $year
	 * @param string|DateTime|null $minDate
	 * @return array
	 */
	protected function getExchangeRates($year = null, $minDate = null)
	{
		try {
			if (empty($year)) {
				$year = (new DateTime)->format('Y');
			}
			if (!empty($minDate)) {
				$minDate = $minDate instanceof DateTime ? $minDate : new DateTime($minDate);
			}
			$client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
			$response = $client->createRequest()
				->setMethod('GET')
				->setUrl("https://www.bnr.ro/files/xml/years/nbrfxrates{$year}.xml")
				->send();

			$xml = new \SimpleXMLElement($response->getContent());
			$exchangeRates = [];

			foreach ($xml->Body->Cube as $i => $cube) {
				if (!empty($minDate) && (new DateTime((string) $cube['date']) <= $minDate)) {
					continue;
				}
				foreach ($cube->Rate as $j => $rate) {
					$exchangeRates[] = [
						'date' => (string) $cube['date'],
						'currency' => (string) $rate['currency'],
						'rate' => (double) $rate,
						'multiplier' => (int) $rate['multiplier'] ?: 1,
						'country_code' => 'RO',
					];
				}
			}

			return $exchangeRates;
		} catch (\Exception $e) {
			return [];
		}
	}
}
