<?php

namespace frontend\modules\account\controllers;

use common\models\Workspace;
use frontend\controllers\MainController;
use frontend\modules\account\models\WorkspaceForm;
use frontend\modules\account\models\WorkspaceSearch;
use Yii;
use yii\filters\AccessControl;
use yii\helpers\FileHelper;
use yii\web\NotFoundHttpException;

class WorkspaceController extends MainController
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
						'actions' => ['index', 'dt-workspaces', 'view', 'update', 'reinstall', 'delete', 'delete-permanently'],
						'roles' => ['@'],
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
		if (!Yii::$app->request->isAjax && $action->id !== 'index') {
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
			'dt-workspaces' => WorkspaceSearch::class,
		];
	}

	/**
	 * Lists all Workspace models.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		if (Yii::$app->request->get('deleted') == Workspace::YES) {
			if (!Yii::$app->settings->get('enableSoftDelete')) {
				return $this->redirect(['index']);
			}
		}
		return $this->render('index');
	}

	/**
	 * Displays a single Workspace model.
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
	 * Updates an existing Workspace model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionUpdate($id)
	{
		$model = $this->findModel($id, WorkspaceForm::class);
		$result = true;

		if ($model->load(Yii::$app->request->post()) && ($result = $model->saveModel())) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllWorkspaces']));

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
	 * Reinstalls the Workspace application.
	 * If update is successful or not, the browser will refresh the current page.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws \Exception
	 * @throws \yii\web\NotFoundHttpException if the model cannot be found
	 */
	public function actionReinstall($id)
	{
		$model = $this->findModel($id);
		$response = [
			'success' => true,
			'message' => [
				'title' => Yii::t('common', 'The workspace has been reinstalled.'),
			],
		];

		if ($model->install()) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllWorkspaces']));
			Yii::$app->trigger('workspace.afterReinstall', new \yii\base\Event(['sender' => $model]));
		} else {
			$response['success'] = false;
			$response['message']['title'] = Yii::t('common', 'Cannot reinstall the workspace.');
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson($response);
		}

		Yii::$app->session->setFlash($response['success'] ? 'success' : 'error', [$response['message']]);

		return $this->redirect(['view', 'id' => $model->id]);
	}

	/**
	 * Deletes existing Workspace models.
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
			/** @var Workspace $model */
			foreach ($models->each() as $model) {
				if ($isPermanent && !$model->uninstall()) {
					throw new \Exception();
				}
				if ($model->delete($isPermanent)) {
					$deletedModels[] = $model->uuid;
				} else {
					throw new \Exception();
				}
			}
			if ($isPermanent) {
				foreach ($deletedModels as $deletedModel) {
					FileHelper::removeDirectory(Yii::getAlias("@uploads/{$this->id}/{$deletedModel}"));
				}
			}
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllWorkspaces']));
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
	 * Restores Workspace models that are marked as deleted.
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
			/** @var Workspace $model */
			foreach ($models->each() as $model) {
				if ($model->restore()) {
				} else {
					throw new \Exception();
				}
			}
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllWorkspaces']));
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
	 * Finds the Workspace model(s) based on its primary key value.
	 * A 404 HTTP exception will be thrown if no record was found.
	 *
	 * @param int|array $id
	 * @param \yii\db\ActiveRecord|null $modelName
	 * @param bool $asActiveQuery
	 * @return \yii\db\ActiveQuery|\yii\db\ActiveRecord|Workspace|WorkspaceForm
	 * @throws NotFoundHttpException if no model was found
	 */
	protected function findModel($id, $modelName = null, $asActiveQuery = false)
	{
		$modelName = class_exists($modelName) ? $modelName : Workspace::class;
		// Scoped to the caller's own workspaces, the same way WorkspaceSearch scopes the
		// list this page is reached from. Without it any signed-in account could view,
		// update, reinstall or delete any tenant by id -- the actions are open to every
		// authenticated user, and beforeAction() only asks that the request be AJAX.
		$query = $modelName::find()
			->alias('w')
			->joinWith([
				'subscription.subscriber s' => function (\yii\db\ActiveQuery $query) {
					$query->andWhere(['s.user_id' => Yii::$app->user->id]);
				},
			], false)
			->andWhere([
				'w.id' => $id,
			]);

		if ($asActiveQuery) {
			if (!$query->count()) {
				throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
			}
			return $query;
		}

		$query->andWhere(['w.deleted' => Workspace::NO]);
		if (($model = $query->one()) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
