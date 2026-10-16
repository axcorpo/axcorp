<?php

namespace backend\modules\setting\modules\currency\controllers;

use backend\controllers\MainController;
use backend\modules\setting\modules\currency\models\CurrencySearch;
use common\models\Currency;
use Yii;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;

class CurrencyController extends MainController
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
						'actions' => ['index', 'view', 'dt-currencies'],
						'roles' => ['viewCurrency'],
					],
					[
						'allow' => true,
						'actions' => ['update'],
						'roles' => ['updateCurrency'],
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
			'dt-currencies' => CurrencySearch::class,
		];
	}

	/**
	 * Lists all Currency models.
	 *
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionIndex()
	{
		if (Yii::$app->request->isAjax && Yii::$app->request->post('dt')) {
			return $this->updateDtColumn();
		}

		return $this->render('index');
	}

	/**
	 * Displays a single Currency model.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionView($id)
	{
		return $this->render('view', [
			'model' => $this->findModel($id),
		]);
	}

	/**
	 * Updates DataTable column.
	 *
	 * @return \yii\web\Response
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	protected function updateDtColumn()
	{
		$params = Yii::$app->request->post();
		$result = false;

		$model = $this->findModel($params['key']);

		Yii::$app->eventLog
			->setData([
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
			])
			->beginRecord($model);

		if ($model->hasAttribute($params['attribute'])) {
			$model->{$params['attribute']} = $params['value'];

			if ($result = $model->save(true, [$params['attribute']])) {
				Yii::$app->eventLog->endRecord();
			}
		}

		Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllCurrencies']));

		return $this->asJson([
			'success' => (bool) $result,
			'message' => $result ?
				Yii::t('common', 'Record has been updated.') :
				Yii::t('common', 'Cannot update the record.'),
		]);
	}

	/**
	 * Finds the Currency model based on its primary key value.
	 * If the model is not found, a 404 HTTP exception will be thrown.
	 *
	 * @param integer $id
	 * @return Currency the loaded model
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	protected function findModel($id)
	{
		if (($model = Currency::findOne($id)) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
