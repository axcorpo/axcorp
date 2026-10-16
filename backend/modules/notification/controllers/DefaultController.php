<?php

namespace backend\modules\notification\controllers;

use backend\controllers\MainController;
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
						'roles' => ['@'],
					],
				],
			],
		];
	}

	/**
	 * Displays index page.
	 *
	 * @return string
	 */
	public function actionIndex()
	{
		return $this->redirect(['notification/index'], 301);
	}
}
