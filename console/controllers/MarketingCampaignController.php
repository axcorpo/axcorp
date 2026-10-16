<?php
namespace console\controllers;

use common\models\MarketingCampaign;
use yii\console\Controller;
use yii\console\ExitCode;

class MarketingCampaignController extends Controller
{
	/**
	 * Runs a marketing campaign.
	 *
	 * @param int $id The ID of the MarketingCampaign model.
	 * @param string|\yii\db\ActiveRecord $model The model class that implements the run method.
	 * @return int
	 */
	public function actionRun($id, $model)
	{
		try {
			if (!class_exists($model) || !is_subclass_of($model, \yii\db\ActiveRecord::class)) {
				throw new \Exception('Invalid model provided.');
			}

			$marketingCampaign = $model::findOne([
				'id' => $id,
				'status' => MarketingCampaign::STATUS_ACTIVE,
				'deleted' => MarketingCampaign::NO,
			]);
			if (!$marketingCampaign) {
				throw new \Exception('Marketing Campaign was not found.');
			}

			$marketingCampaign->run();

			$this->stdout("[" . date("Y-m-d H:i:s") . "] Processed 1 marketing campaign.");
			return ExitCode::OK;
		} catch (\Exception $e) {
			$this->stderr($e->getMessage());
			return ExitCode::UNSPECIFIED_ERROR;
		} catch (\Throwable $e) {
			$this->stderr($e->getMessage());
			return ExitCode::UNSPECIFIED_ERROR;
		}
	}
}
