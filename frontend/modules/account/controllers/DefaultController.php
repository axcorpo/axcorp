<?php

namespace frontend\modules\account\controllers;

use frontend\controllers\MainController;
use Yii;
use yii\filters\AccessControl;

class DefaultController extends MainController
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
						'actions' => ['index', 'help'],
						'roles' => ['@'],
					],
				],
			],
		];
	}

	/**
	 * Displays index view.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		return $this->redirect(['profile/index'], 301);
	}

	/**
	 * Displays help view.
	 *
	 * @return mixed
	 */
	public function actionHelp()
	{
		if (Yii::$app->request->isAjax) {
			return $this->asJson([
				'success' => true,
				'data' => $this->renderAjax('help'),
			]);
		}

		return $this->render('help');
	}
}
