<?php
namespace console\controllers;

use common\models\ScheduledTask;
use common\models\Subscription;
use common\models\SubscriptionFeature;
use common\models\WorkspaceHasSubscriptionFeature;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Expression;

class SubscriptionController extends Controller
{
	/**
	 * @var \DateTime The current date and time instance used for this process operations.
	 */
	public static $currentDate;


	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		self::$currentDate = new \DateTime();
	}

	/**
	 * Renews the subscription attributes quota.
	 * This operation is performed monthly based on the last subscription attribute renewal date.
	 *
	 * @return int
	 */
	public function actionRenewFeaturesQuota()
	{
		try {
			$currentDate = self::$currentDate->format('Y-m-d H:i:s');
			$whsf = WorkspaceHasSubscriptionFeature::find()
				->select([
					'subscription_feature_id',
					'MAX([[renewed_at]]) AS renewed_at',
				])
				->groupBy(['subscription_feature_id']);
			$renewableSubscriptionFeatures = SubscriptionFeature::find()
				->alias('sf')
				->select(['sf.id'])
				->joinWith(['subscription sub'])
				->leftJoin(['whsf' => $whsf], '[[sf.id]] = [[whsf.subscription_feature_id]]')
				->andWhere([
					'OR',
					['=', 'sub.billing_cycle', ScheduledTask::CYCLE_YEAR],
					[
						'AND',
						['=', 'sub.billing_cycle', ScheduledTask::CYCLE_DAY],
						['>', 'sub.billing_period', 31],
					],
					[
						'AND',
						['=', 'sub.billing_cycle', ScheduledTask::CYCLE_WEEK],
						['>', 'sub.billing_period', 4],
					],
					[
						'AND',
						['=', 'sub.billing_cycle', ScheduledTask::CYCLE_MONTH],
						['>', 'sub.billing_period', 1],
					],
				])
				->andWhere(new Expression("TIMESTAMPDIFF(SECOND, [[sub.start_at]], '{$currentDate}') >= 0"))
				->andWhere(new Expression("TIMESTAMPDIFF(SECOND, [[sub.end_at]], '{$currentDate}') < 0"))
				->andWhere(new Expression("DATE_FORMAT(DATE_ADD([[whsf.renewed_at]], INTERVAL +1 MONTH), '%Y%m%d%H%i') = DATE_FORMAT('{$currentDate}', '%Y%m%d%H%i')"))
				->andWhere(['!=', 'sub.type', Subscription::TYPE_FREE])
				->andWhere([
					'sf.renewable' => SubscriptionFeature::YES,
					'sub.status' => Subscription::STATUS_ACTIVE,
					'sub.deleted' => Subscription::NO,
				])
				->indexBy('id')
				->all();
			$count = count($renewableSubscriptionFeatures);

			if ($renewableSubscriptionFeatures) {
				WorkspaceHasSubscriptionFeature::updateAll([
					'quota' => 0,
					'renewed_at' => self::$currentDate->format('Y-m-d H:i:s'),
				], [
					'subscription_feature_id' => array_keys($renewableSubscriptionFeatures),
				]);
			}

			$this->stdout("[" . date("Y-m-d H:i:s") . "] Processed {$count} renewable subscription attribute(s).");
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
