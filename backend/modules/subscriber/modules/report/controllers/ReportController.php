<?php

namespace backend\modules\subscriber\modules\report\controllers;

use backend\controllers\MainController;
use backend\modules\subscriber\modules\report\models\SubscriberReportForm;
use backend\modules\subscriber\modules\report\models\SubscriptionMonthlyReportForm;
use backend\modules\subscriber\modules\report\models\SubscriptionReportForm;
use backend\modules\subscriber\modules\report\models\SubscriptionStatusReportForm;
use Yii;
use yii\filters\AccessControl;

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
						'actions' => ['subscription'],
						'roles' => ['viewSubscriber'],
					],
					[
						'allow' => true,
						'actions' => ['subscription-monthly'],
						'roles' => ['viewSubscriber'],
					],
					[
						'allow' => true,
						'actions' => ['subscription-status'],
						'roles' => ['viewSubscriber'],
					],
					[
						'allow' => true,
						'actions' => ['subscriber'],
						'roles' => ['viewSubscriber'],
					],
				],
			],
		];
	}

	/**
	 * Lists all SubscriptionReportForm models.
	 *
	 * @return mixed
	 */
	public function actionSubscription()
	{
		$model = new SubscriptionReportForm();
		$model->load(Yii::$app->request->get(), '');

		return $this->render('subscription', [
			'model' => $model,
		]);
	}

	/**
	 * Lists all SubscriptionMonthlyReportForm models.
	 *
	 * @return mixed
	 */
	public function actionSubscriptionMonthly()
	{
		$model = new SubscriptionMonthlyReportForm();
		$model->load(Yii::$app->request->get(), '');

		return $this->render('subscription-monthly', [
			'model' => $model,
		]);
	}

	/**
	 * Lists all SubscriptionStatusReportForm models.
	 *
	 * @return mixed
	 */
	public function actionSubscriptionStatus()
	{
		$model = new SubscriptionStatusReportForm();
		$model->load(Yii::$app->request->get(), '');

		return $this->render('subscription-status', [
			'model' => $model,
		]);
	}

	/**
	 * Lists all SubscriberReportForm models.
	 *
	 * @return mixed
	 */
	public function actionSubscriber()
	{
		$model = new SubscriberReportForm();
		$model->load(Yii::$app->request->get(), '');

		return $this->render('subscriber', [
			'model' => $model,
		]);
	}
}
