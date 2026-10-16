<?php

namespace backend\modules\affiliate\controllers;

use backend\controllers\MainController;
use backend\modules\affiliate\models\SubscriberSearch;
use yii\filters\AccessControl;

/**
 * The master subscribers referred by affiliates, with their subscriptions.
 * Read-only: the subscribers are managed in the subscriber module.
 */
class SubscriberController extends MainController
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
						'actions' => ['index', 'dt-subscribers'],
						'roles' => ['viewAffiliate'],
					],
				],
			],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function actions()
	{
		return [
			'dt-subscribers' => SubscriberSearch::class,
		];
	}

	/**
	 * Lists the referred subscribers.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		return $this->render('index');
	}
}
