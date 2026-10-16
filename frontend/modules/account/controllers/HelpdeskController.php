<?php

namespace frontend\modules\account\controllers;

use frontend\controllers\MainController;
use Yii;
use yii\filters\AccessControl;

class HelpdeskController extends MainController
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
						'actions' => ['index'],
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
		return $this->render('index');
	}
}
