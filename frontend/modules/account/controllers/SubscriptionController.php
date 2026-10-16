<?php

namespace frontend\modules\account\controllers;

use common\models\Subscription;
use frontend\controllers\MainController;
use frontend\modules\account\models\FeatureSubscriptionSearch;
use frontend\modules\account\models\SubscriptionCancelForm;
use frontend\modules\account\models\SubscriptionForm;
use frontend\modules\account\models\PackageSubscriptionSearch;
use frontend\modules\account\models\SubscriptionReactivateForm;
use Yii;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;

class SubscriptionController extends MainController
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
						'actions' => ['index', 'dt-package-subscriptions', 'dt-feature-subscriptions', 'view', 'update', 'activate'],
						'roles' => ['@'],
					],
					[
						'allow' => true,
						'actions' => ['cancel', 'reactivate'],
						'roles' => ['@'],
						'verbs' => ['POST'],
					],
				],
			],
		];
	}

	/**
	 * @inheritdoc
	 * @throws NotFoundHttpException
	 */
	public function beforeAction($action)
	{
		if (!Yii::$app->request->isAjax && !in_array($action->id, ['index', 'cancel', 'reactivate'])) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}
		return parent::beforeAction($action);
	}

	/**
	 * @inheritdoc
	 */
	public function actions()
	{
		return [
			'dt-package-subscriptions' => PackageSubscriptionSearch::class,
			'dt-feature-subscriptions' => FeatureSubscriptionSearch::class,
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

	/**
	 * Displays a single Subscription model.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionView($id)
	{
		$model = $this->findModel($id);

		if (Yii::$app->request->isAjax) {
			return $this->asJson([
				'success' => true,
				'data' => $this->renderAjax('view', [
					'model' => $model,
				]),
			]);
		}

		return $this->render('view', [
			'model' => $model,
		]);
	}

	/**
	 * Updates an existing Subscription model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionUpdate($id)
	{
		$model = $this->findModel($id, SubscriptionForm::class);
		$result = true;

		if ($model->load(Yii::$app->request->post()) && ($result = $model->save())) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllSubscriptions']));

			$message = Yii::t('common', 'Record has been updated.');
			if (Yii::$app->request->isAjax) {
				return $this->asJson([
					'success' => true,
					'message' => $message,
				]);
			}
			Yii::$app->session->setFlash('success', $message);

			return $this->redirect(['view', 'id' => $model->id]);
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson([
				'success' => (bool) $result,
				'data' => $this->renderAjax('update', [
					'model' => $model,
				]),
			]);
		}

		return $this->render('update', [
			'model' => $model,
		]);
	}

	/**
	 * Activates a subscription.
	 *
	 * @param int
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionActivate($id)
	{
		$model = $this->findModel($id);

		if ($model->activate()) {
			Yii::$app->session->setFlash('success', Yii::t('frontend', 'Subscription activated successfully.'));
		} else {
			Yii::$app->session->setFlash('error', Yii::t('frontend', 'Cannot activate the subscription.'));
		}

		return $this->redirect(['index']);
	}

	/**
	 * Cancels a subscription.
	 *
	 * @param int $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionCancel($id)
	{
		$model = $this->findModel($id, SubscriptionCancelForm::class);

		if ($model->cancel()) {
			Yii::$app->session->setFlash('success', Yii::t('frontend', 'Subscription cancelled successfully.'));
		} else {
			Yii::$app->session->setFlash('error', Yii::t('frontend', 'Cannot cancel the selected subscription.'));
		}

		return $this->redirect(['index']);
	}

	/**
	 * Reactivates a subscription.
	 *
	 * @param int $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionReactivate($id)
	{
		$model = $this->findModel($id, SubscriptionReactivateForm::class);

		if ($model->reactivate()) {
			Yii::$app->session->setFlash('success', Yii::t('frontend', 'Subscription reactivated successfully.'));
		} else {
			Yii::$app->session->setFlash('error', Yii::t('frontend', 'Cannot reactivate the selected subscription.'));
		}

		return $this->redirect(['index']);
	}

	/**
	 * Finds the Subscription model(s) based on its primary key value.
	 * A 404 HTTP exception will be thrown if no record was found.
	 *
	 * @param int|array $id
	 * @param \yii\db\ActiveRecord|null $modelName
	 * @param bool $asActiveQuery
	 * @return \yii\db\ActiveQuery|\yii\db\ActiveRecord|Subscription
	 * @throws NotFoundHttpException if no model was found
	 */
	protected function findModel($id, $modelName = null, $asActiveQuery = false)
	{
		$modelName = class_exists($modelName) ? $modelName : Subscription::class;
		$query = $modelName::find()
			->alias('sub')
			->joinWith([
				'subscriber s',
			])
			->andWhere([
				's.user_id' => Yii::$app->user->id,
				'sub.id' => $id,
			]);

		if ($asActiveQuery) {
			if (!$query->count()) {
				throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
			}
			return $query;
		}

		$query->andWhere(['sub.deleted' => Subscription::NO]);
		if (($model = $query->one()) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
