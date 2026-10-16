<?php

namespace backend\modules\setting\modules\language\controllers;

use yii\filters\AccessControl;
use yii\web\Controller;

class DefaultController extends Controller
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
	 * Renders the index view for the module.
	 *
	 * @return string
	 */
	public function actionIndex()
	{
		return $this->redirect(['language/index'], 301);
	}
}
