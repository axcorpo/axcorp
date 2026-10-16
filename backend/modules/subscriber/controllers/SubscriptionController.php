<?php

namespace backend\modules\subscriber\controllers;

use backend\modules\subscriber\models\SubscriptionActivateForm;
use backend\modules\subscriber\models\SubscriptionCancelForm;
use backend\modules\subscriber\models\SubscriptionForm;
use backend\modules\subscriber\models\SubscriptionSearch;
use common\models\Subscription;
use backend\controllers\MainController;
use common\models\Workspace;
use kartik\depdrop\DepDropAction;
use Yii;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
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
						'actions' => ['index', 'view', 'dt-subscriptions', 'subscriber-subscriptions'],
						'roles' => ['viewSubscriber'],
					],
					[
						'allow' => true,
						'actions' => ['create'],
						'roles' => ['createSubscriber'],
					],
					[
						'allow' => true,
						'actions' => ['update', 'activate'],
						'roles' => ['updateSubscriber'],
					],
					[
						// Cancels at Stripe as well, so a link alone must not trigger it.
						'allow' => true,
						'actions' => ['cancel'],
						'roles' => ['updateSubscriber'],
						'verbs' => ['POST'],
					],
					[
						'allow' => true,
						'actions' => ['delete'],
						'roles' => ['deleteSubscriber'],
						'verbs' => ['POST'],
					],
					[
						'allow' => true,
						'actions' => ['restore'],
						'roles' => ['restoreSubscriber'],
						'verbs' => ['POST'],
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
			'dt-subscriptions' => SubscriptionSearch::class,
			'subscriber-subscriptions' => [
				'class' => DepDropAction::class,
				'outputCallback' => function ($selectedId, $params) {
					return array_map(function (Subscription $subscription) {
						return [
							// The UUID string, never the raw BINARY(16) bytes (breaks JSON encoding)
							'id' => $subscription->uuid,
							'name' => $subscription->formattedName,
						];
					}, Subscription::findSubscriptionsBySubscriber($selectedId));
				},
				'selectedCallback' => function ($selectedId, $params) {
					return $params;
				},
			],
		];
	}

	/**
	 * Lists all Subscription models.
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
	 * Creates a new Subscription model.
	 * If creation is successful, the browser will be redirected to the 'view' page.
	 *
	 * @return mixed
	 */
	public function actionCreate()
	{
		$model = new SubscriptionForm();
		$model->subscriber_id = Yii::$app->request->get('subscriber_id');
		$result = true;

		Yii::$app->eventLog->beginRecord($model);
		if ($model->load(Yii::$app->request->post()) && ($result = $model->saveModel())) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllSubscriptions']));
			Yii::$app->eventLog->endRecord();

			$message = Yii::t('common', 'Record has been created.');
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
				'data' => $this->renderAjax('create', [
					'model' => $model,
				]),
			]);
		}

		return $this->render('create', [
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

		Yii::$app->eventLog->beginRecord($model);
		if ($model->load(Yii::$app->request->post()) && ($result = $model->saveModel())) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllSubscriptions']));
			Yii::$app->eventLog->endRecord();

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
	 * Deletes existing Subscription models.
	 * If deletion is successful, JSON is returned or the browser will be redirected to the 'index' page.
	 *
	 * @param null|int $id
	 * @return mixed
	 * @throws \Throwable
	 * @throws NotFoundHttpException if no model was found
	 */
	public function actionDelete($id = null)
	{
		$bodyParams = Yii::$app->request->post();
		$isPermanent = !Yii::$app->settings->get('enableSoftDelete') || ($bodyParams['dt_operation'] == 'delete-permanently' || $bodyParams['dt_bulk_operation'] == 'delete-permanently');
		if ($id === null) {
			$id = Yii::$app->request->post('selection');
		}
		$models = $this->findModel($id, null, true);
		$response = [
			'success' => true,
			'message' => [
				'title' => Yii::t('common', 'The delete operation was successful.'),
				'body' => [],
			],
		];
		$dbTransaction = Yii::$app->db->beginTransaction();
		try {
			$deletedModels = [];
			/** @var Subscription $model */
			foreach ($models->each() as $model) {
				Yii::$app->eventLog
					->setData([
						'operation' => $isPermanent ? (Yii::$app->eventLog)::ACTION_DELETE : (Yii::$app->eventLog)::ACTION_SOFT_DELETE,
					])
					->beginRecord($model);
				if ($model->delete($isPermanent)) {
					$deletedModels[] = $model->uuid;
					Yii::$app->eventLog->endRecord();
				} else {
					throw new \Exception();
				}
			}
			if ($isPermanent) {
				foreach ($deletedModels as $deletedModel) {
					FileHelper::removeDirectory(Yii::getAlias("@uploads/{$this->id}/{$deletedModel}"));
				}
			}
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllSubscriptions']));
			$dbTransaction->commit();
		} catch (\Exception $e) {
			$dbTransaction->rollBack();
			$response['success'] = false;
			$response['message']['title'] = Yii::t('common', 'The delete operation was unsuccessful.');
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson($response);
		}
		Yii::$app->session->setFlash($response['success'] ? 'success' : 'error', [$response['message']]);

		return $this->redirect(['index']);
	}

	/**
	 * Restores Subscription models that are marked as deleted.
	 * If restoration is successful, JSON is returned or the browser will be redirected to the 'index' page.
	 *
	 * @param null|int $id
	 * @return mixed
	 * @throws NotFoundHttpException if no model was found
	 */
	public function actionRestore($id = null)
	{
		if ($id === null) {
			$id = Yii::$app->request->post('selection');
		}
		$models = $this->findModel($id, null, true);
		$response = [
			'success' => true,
			'message' => [
				'title' => Yii::t('common', 'The restore operation was successful.'),
				'body' => [],
			],
		];
		$dbTransaction = Yii::$app->db->beginTransaction();
		try {
			/** @var Subscription $model */
			foreach ($models->each() as $model) {
				Yii::$app->eventLog->beginRecord($model);
				if ($model->restore()) {
					Yii::$app->eventLog->endRecord();
				} else {
					throw new \Exception();
				}
			}
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllSubscriptions']));
			$dbTransaction->commit();
		} catch (\Exception $e) {
			$dbTransaction->rollBack();
			$response['success'] = false;
			$response['message']['title'] = Yii::t('common', 'The restore operation was unsuccessful.');
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson($response);
		}
		Yii::$app->session->setFlash($response['success'] ? 'success' : 'error', [$response['message']]);

		return $this->redirect(['index']);
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
		$model = $this->findModel($id, SubscriptionActivateForm::class);
		$response = [];
		$result = true;

        if ($model->load(Yii::$app->request->post())) {
            if ($model->activate()) {
                $response['success'] = true;
                $response['message'] = Yii::t('frontend', 'Subscription activated successfully.');
            } else {
                $response['success'] = false;
                $response['message'] = Yii::t('frontend', 'Cannot activate the subscription.');
            }

            Yii::$app->session->setFlash($response['success'] === true ? 'success' : 'error', $response['message']);

            return $this->redirect(['subscriber/update', 'id' => $model->subscriber_id]);
        }

        if (Yii::$app->request->isAjax) {
            return $this->asJson([
                'success' => (bool) $result,
                'data' => $this->renderAjax('_activate', [
                    'model' => $model,
                ]),
            ]);
        }

        return $this->render('_activate', [
            'model' => $model,
        ]);
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
			$response['success'] = true;
			$response['message'] = Yii::t('frontend', 'Subscription cancelled successfully.');
		} else {
			$response['success'] = false;
			$response['message'] = Yii::t('frontend', 'Cannot cancel the selected subscription.');
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson($response);
		}

		Yii::$app->session->setFlash($response['success'] === true ? 'success' : 'error', $response['message']);

		return $this->redirect(['subscriber/update', 'id' => $model->subscriber_id]);
	}

	/**
	 * Finds the Subscription model(s) based on its primary key value.
	 * A 404 HTTP exception will be thrown if no record was found.
	 *
	 * @param int|array $id
	 * @param \yii\db\ActiveRecord|null $modelName
	 * @param bool $asActiveQuery
	 * @return \yii\db\ActiveQuery|\yii\db\ActiveRecord|Subscription|SubscriptionForm
	 * @throws NotFoundHttpException if no model was found
	 */
	protected function findModel($id, $modelName = null, $asActiveQuery = false)
	{
		$modelName = class_exists($modelName) ? $modelName : Subscription::class;
		$query = $modelName::find()->andWhere([
			'id' => $id,
		]);

		if ($asActiveQuery) {
			if (!$query->count()) {
				throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
			}
			return $query;
		}

		$query->andWhere(['deleted' => Subscription::NO]);
		if (($model = $query->one()) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
