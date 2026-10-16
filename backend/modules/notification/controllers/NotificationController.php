<?php

namespace backend\modules\notification\controllers;

use backend\controllers\MainController;
use backend\modules\notification\models\NotificationForm;
use backend\modules\notification\models\NotificationSearch;
use common\helpers\DateHelper;
use common\models\Notification;
use common\models\UserHasNotification;
use Yii;
use yii\filters\AccessControl;
use yii\helpers\FileHelper;
use yii\web\NotFoundHttpException;

class NotificationController extends MainController
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
						'actions' => ['index', 'view', 'dt-notifications', 'list', 'bulk-seen', 'mark-as-seen'],
						'roles' => ['viewNotification'],
					],
					[
						'allow' => true,
						'actions' => ['create'],
						'roles' => ['createNotification'],
					],
					[
						'allow' => true,
						'actions' => ['update'],
						'roles' => ['updateNotification'],
					],
					[
						'allow' => true,
						'actions' => ['delete'],
						'roles' => ['deleteNotification'],
						'verbs' => ['POST'],
					],
					[
						'allow' => true,
						'actions' => ['restore'],
						'roles' => ['restoreNotification'],
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
			'dt-notifications' => NotificationSearch::class,
		];
	}

	/**
	 * Lists all Notification models.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		if (Yii::$app->request->get('deleted') == Notification::YES) {
			if (!Yii::$app->settings->get('enableSoftDelete') || !Yii::$app->user->can('restoreNotification')) {
				return $this->redirect(['index']);
			}
		}
		return $this->render('index');
	}

	/**
	 * Displays a list of notifications.
	 *
	 * @return mixed
	 * @throws \yii\web\NotFoundHttpException
	 */
	public function actionList()
	{
		if (!Yii::$app->request->isAjax) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}

		$models = \common\models\Notification::findAllUnseen();

		return $this->asJson([
			'success' => true,
			'unseenNotifications' => count($models),
			'data' => $this->renderAjax('list', [
				'models' => $models,
			]),
		]);
	}

	/**
	 * Displays a single Notification model.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws \yii\web\NotFoundHttpException if the model cannot be found
	 */
	public function actionView($id)
	{
		$model = $this->findModel($id);
		$model->markAsSeen();

		return $this->render('view', [
			'model' => $model,
		]);
	}

	/**
	 * Creates a new Notification model.
	 * If creation is successful, the browser will be redirected to the 'view' page.
	 *
	 * @return mixed
	 */
	public function actionCreate()
	{
		$model = new NotificationForm();
		$model->created_by = Yii::$app->user->id;

		$result = true;

		Yii::$app->eventLog->beginRecord($model);
		if ($model->load(Yii::$app->request->post()) && ($result = $model->saveModel())) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllNotifications']));
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
	 * Updates an existing Notification model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionUpdate($id)
	{
		$model = $this->findModel($id, NotificationForm::class);
		$model->updated_by = Yii::$app->user->id;

		$result = true;

		if ($model->created_by != Yii::$app->user->id) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}

		Yii::$app->eventLog->beginRecord($model);
		if ($model->load(Yii::$app->request->post()) && ($result = $model->saveModel())) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllNotifications']));
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
	 * Deletes existing Notification models.
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
			/** @var Notification $model */
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
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllNotifications']));
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
	 * Restores Notification models that are marked as deleted.
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
			/** @var Notification $model */
			foreach ($models->each() as $model) {
				Yii::$app->eventLog->beginRecord($model);
				if ($model->restore()) {
					Yii::$app->eventLog->endRecord();
				} else {
					throw new \Exception();
				}
			}
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllNotifications']));
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
	 * Marks as seen one ore more existing Notification models for the current user.
	 * If marking is successful, JSON is returned or the browser will be redirected to the 'index' page.
	 *
	 * @return mixed
	 */
	public function actionMarkAsSeen()
	{
		$queryConditions = [
			'user_id' => Yii::$app->user->id,
		];
		if ($notifications = Yii::$app->request->post('selection')) {
			$queryConditions['notification_id'] = $notifications;
		}

		$result = UserHasNotification::updateAll([
			'seen' => Notification::YES,
			'updated_at' => DateHelper::formatAsDateTime('now'),
		], $queryConditions);

		$responseData = [
			'success' => (bool) $result,
			'message' => $result ?
				Yii::t('common', 'Records have been updated.') :
				Yii::t('common', 'Cannot update the records.'),
		];

		if (Yii::$app->request->isAjax) {
			return $this->asJson($responseData);
		}

		Yii::$app->session->setFlash($responseData['success'] ? 'success' : 'error', $responseData['message']);

		return $this->redirect(['index']);
	}

	/**
	 * Finds the Notification model(s) based on its primary key value.
	 * A 404 HTTP exception will be thrown if no record was found.
	 *
	 * @param int|array $id
	 * @param \yii\db\ActiveRecord|null $modelName
	 * @param bool $asActiveQuery
	 * @return \yii\db\ActiveQuery|\yii\db\ActiveRecord|Notification|NotificationForm
	 * @throws NotFoundHttpException if no model was found
	 */
	protected function findModel($id, $modelName = null, $asActiveQuery = false)
	{
		$modelName = class_exists($modelName) ? $modelName : Notification::class;
		$query = $modelName::find()->andWhere([
			'id' => $id,
		]);

		if ($asActiveQuery) {
			if (!$query->count()) {
				throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
			}
			return $query;
		}

		$query->andWhere(['deleted' => Notification::NO]);
		if (($model = $query->one()) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
