<?php

namespace backend\modules\affiliate\controllers;

use backend\controllers\MainController;
use backend\modules\affiliate\models\AffiliateReportForm;
use Yii;
use yii\filters\AccessControl;

/**
 * Affiliate reports: the overview dashboard, statistics per month / affiliate
 * and the leaderboard.
 */
class ReportController extends MainController
{
	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'access' => [
				'class' => AccessControl::class,
				'rules' => [
					[
						'allow' => true,
						'actions' => ['index', 'dashboard', 'statistics', 'leaderboard'],
						'roles' => ['viewAffiliate'],
					],
				],
			],
		];
	}

	/**
	 * @return mixed
	 */
	public function actionIndex()
	{
		return $this->redirect(['statistics'], 301);
	}

	/**
	 * The overview: KPI tiles and charts for a period, affiliate and currency.
	 *
	 * @return mixed
	 */
	public function actionDashboard()
	{
		$model = new AffiliateReportForm();
		$model->load(Yii::$app->request->get());
		$stats = $model->createStats();
		$currencies = $stats ? $stats->getCurrencies() : [];
		// One currency per chart: the chosen one, else the one with the largest revenue.
		if (!$model->currency || !in_array($model->currency, $currencies, true)) {
			$model->currency = $currencies ? $currencies[0] : null;
		}

		return $this->render('dashboard', [
			'model' => $model,
			'stats' => $stats,
			'currencies' => $currencies,
		]);
	}

	/**
	 * Signups, conversions, active subscriptions, revenue and commissions, grouped.
	 *
	 * @return mixed
	 */
	public function actionStatistics()
	{
		$model = new AffiliateReportForm();
		$model->load(Yii::$app->request->get());

		return $this->render('statistics', [
			'model' => $model,
		]);
	}

	/**
	 * The affiliates ranked by the revenue they generated.
	 *
	 * @return mixed
	 */
	public function actionLeaderboard()
	{
		$model = new AffiliateReportForm();
		$model->load(Yii::$app->request->get());

		return $this->render('leaderboard', [
			'model' => $model,
			'rows' => $model->getLeaderboard(),
		]);
	}
}
