<?php

namespace console\controllers;

use common\models\Setting;
use common\services\AffiliateCommissionService;
use Yii;
use yii\caching\TagDependency;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Affiliate module cron jobs (docs/afiliere.md).
 *
 * `php yii affiliate/compute-commissions` — scheduled hourly by
 * _database/001_affiliate.sql — turns the paid invoices of affiliate-referred
 * subscribers into commissions, cancels the voided ones and releases those whose hold
 * period is over. Idempotent: rerunning it changes nothing that is already up to date.
 */
class AffiliateController extends Controller
{
	/**
	 * @var string|null Compute as of this moment ("Y-m-d H:i:s"); default now.
	 */
	public $now;

	/**
	 * {@inheritdoc}
	 */
	public function options($actionID)
	{
		return array_merge(parent::options($actionID), ['now']);
	}

	/**
	 * Runs one pass of the commission engine (AffiliateCommissionService::run()).
	 *
	 * @return int
	 */
	public function actionComputeCommissions()
	{
		// Read the settings fresh (holdDays): Setting::findAppSettings() caches forever and
		// saving them in the backend only invalidates the backend's own cache.
		if (Yii::$app->has('settings')) {
			TagDependency::invalidate(Yii::$app->cache, 'findAppSettings');
			Yii::$app->settings->data = (array) Setting::findAppSettings();
		}

		try {
			$result = (new AffiliateCommissionService(['now' => $this->now]))->run();
		} catch (\Exception $e) {
			Yii::error(['message' => 'affiliate/compute-commissions failed.', 'error' => $e->getMessage()], __METHOD__);
			$this->stderr("affiliate/compute-commissions: {$e->getMessage()}\n");

			return ExitCode::UNSPECIFIED_ERROR;
		}

		$this->stdout(sprintf(
			"affiliate/compute-commissions: %d created, %d cancelled, %d made payable.\n",
			$result['created'],
			$result['cancelled'],
			$result['payable']
		));

		return ExitCode::OK;
	}
}
