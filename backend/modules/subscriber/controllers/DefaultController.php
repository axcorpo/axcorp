<?php

namespace backend\modules\subscriber\controllers;

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
	 * @return mixed
	 */
	public function actionIndex()
	{
		return $this->redirect(['subscriber/index'],301);
	}
}
