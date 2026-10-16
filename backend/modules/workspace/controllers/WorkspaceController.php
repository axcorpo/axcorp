<?php

namespace backend\modules\workspace\controllers;

use backend\controllers\MainController;
use backend\modules\workspace\models\WorkspaceDatabaseUpdateLogSearch;
use backend\modules\workspace\models\WorkspaceForm;
use backend\modules\workspace\models\WorkspaceSearch;
use common\models\AuthAssignment;
use common\models\Workspace;
use common\models\WorkspaceDatabaseUpdateForm;
use common\models\WorkspaceDatabaseUpdateLog;
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
						'actions' => ['index', 'view', 'dt-workspaces'],
						'roles' => ['viewWorkspace'],
					],
					[
						'allow' => true,
						'actions' => ['create'],
						'roles' => ['createWorkspace'],
					],
					[
						'allow' => true,
						'actions' => ['update', 'database-update', 'database-update-logs', 'dt-database-update-logs', 'symlink-update', 'cache-flush'],
						'roles' => ['updateWorkspace'],
					],
					[
						// Wipes and reinstalls the tenant database; POST so a link cannot.
						'allow' => true,
						'actions' => ['reinstall'],
						'roles' => ['updateWorkspace'],
						'verbs' => ['POST'],
					],
					[
						'allow' => true,
						'actions' => ['regenerate-api-token'],
						'roles' => ['updateWorkspace'],
						'verbs' => ['POST'],
					],
					[
						'allow' => true,
						'actions' => ['delete', 'delete-file'],
						'roles' => ['deleteWorkspace'],
						'verbs' => ['POST'],
					],
					[
						'allow' => true,
						'actions' => ['restore'],
						'roles' => ['restoreWorkspace'],
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
			'dt-workspaces' => WorkspaceSearch::class,
			'dt-database-update-logs' => WorkspaceDatabaseUpdateLogSearch::class,
		];
	}

	/**
	 * @inheritdoc
	 * @throws NotFoundHttpException if no model was found or the permissions are incorrect
	 */
	public function beforeAction($action)
	{
		return parent::beforeAction($action);
	}

	/**
	 * Lists all Workspace models.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		if (Yii::$app->request->get('deleted') == Workspace::YES) {
			if (!Yii::$app->settings->get('enableSoftDelete') || !Yii::$app->user->can('restoreWorkspace')) {
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
	 * @throws \yii\web\NotFoundHttpException if the model cannot be found
	 */
	public function actionView($id)
	{
		return $this->render('view', [
			'model' => $this->findModel($id),
		]);
	}

	/**
	 * Creates a new Workspace model.
	 * If creation is successful, the browser will be redirected to the 'view' page.
	 *
	 * @return mixed
	 */
	public function actionCreate()
	{
		$model = new WorkspaceForm();
		$result = true;

		Yii::$app->eventLog->beginRecord($model);
		if ($model->load(Yii::$app->request->post()) && ($result = $model->saveModel())) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllWorkspaces']));
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

		Yii::$app->eventLog->beginRecord($model);
		if ($model->load(Yii::$app->request->post()) && ($result = $model->saveModel())) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllWorkspaces']));
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
	 * Regenerates the workspace API token (the Bearer secret its tenant uses to publish
	 * announcements to the master hub) and rewrites the tenant's config with the new value.
	 * The old token stops working immediately. When the tenant directory is not provisioned
	 * locally (e.g. cPanel-only tenants) the config rewrite is skipped — the flash message
	 * says so, and the tenant keeps failing to publish until its config is updated manually
	 * or the workspace is reinstalled.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionRegenerateApiToken($id)
	{
		$model = $this->findModel($id);

		Yii::$app->eventLog->beginRecord($model);
		// Clearing the column makes ensureApiToken() generate and persist a fresh token.
		$model->updateAttributes(['api_token' => null]);
		$model->ensureApiToken();
		$configWritten = $model->writeTenantApiConfig();
		Yii::$app->eventLog->endRecord();

		if ($configWritten) {
			Yii::$app->session->setFlash('success', Yii::t('common', 'The API token has been regenerated and the tenant configuration has been updated.'));
		} else {
			Yii::$app->session->setFlash('warning', Yii::t('common', 'The API token has been regenerated, but the tenant configuration could not be rewritten. Update it manually or reinstall the workspace, otherwise the tenant cannot publish announcements.'));
		}

		return $this->redirect(['update', 'id' => $model->id]);
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
				Yii::$app->eventLog
					->setData([
						'operation' => $isPermanent ? (Yii::$app->eventLog)::ACTION_DELETE : (Yii::$app->eventLog)::ACTION_SOFT_DELETE,
					])
					->beginRecord($model);
				if ($isPermanent && !$model->uninstall()) {
					throw new \Exception();
				}
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
	 * Update existing Workspace databases.
	 *
	 * When a workspace $code is provided, the query is executed only against that
	 * single workspace database (used to rerun a failed update). Each execution is
	 * recorded so it is possible to tell which database succeeded, which failed and
	 * with what exact error (see the history datatable on the same page).
	 *
	 * @param string|null $code The workspace code to target a single database.
	 * @return mixed
	 */
	public function actionDatabaseUpdate($code = null)
	{
		if (Yii::$app->user->identity->authAssignment->item_name != 'superAdmin') {
			return $this->redirect(['index']);
		}

		$model = new WorkspaceDatabaseUpdateForm();
		$model->workspaceCode = $code;
		$results = [];

		if ($model->load(Yii::$app->request->post()) && $model->validate()) {
			$results = $model->saveModel();
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllWorkspaces']));

			$total = count($results);
			$succeeded = count(array_filter($results, function (WorkspaceDatabaseUpdateLog $log) {
				return $log->getIsSuccessful();
			}));
			$failed = $total - $succeeded;

			if ($total === 0) {
				Yii::$app->session->setFlash('warning', Yii::t('common', 'No active workspace matched the request.'));
			} elseif ($failed === 0) {
				Yii::$app->session->setFlash('success', Yii::t('common', 'The query ran successfully on all {n} database(s).', ['n' => $total]));
			} else {
				Yii::$app->session->setFlash('error', Yii::t('common', 'The query failed on {failed} of {total} database(s). See the results below.', [
					'failed' => $failed,
					'total' => $total,
				]));
			}
		} elseif (!empty($code)) {
			// Prefill the query with the last one run against this workspace, to ease reruns.
			$lastLog = WorkspaceDatabaseUpdateLog::find()
				->where(['workspace_code' => $code])
				->orderBy(['id' => SORT_DESC])
				->one();
			if ($lastLog !== null) {
				$model->query = $lastLog->query;
			}
		}

		return $this->render('database-update', [
			'model' => $model,
			'results' => $results,
		]);
	}

	/**
	 * Lists the history of executed Workspace database updates.
	 *
	 * @return mixed
	 */
	public function actionDatabaseUpdateLogs()
	{
		return $this->redirect(['database-update']);
	}

	/**
	 * Update existing Workspace symbolic links for static assets.
	 *
	 */
	public function actionSymlinkUpdate()
	{
		if (Yii::$app->user->identity->authAssignment->item_name != 'superAdmin') {
			return $this->redirect(['index']);
		}

		$models = Workspace::find()
			->where([
				'status' => Workspace::STATUS_ACTIVE,
				'deleted' => Workspace::NO,
			])
			->all();
		$errors = [];
		if (!empty($models)) {
			foreach ($models as $model) {
				try {
					$dirPath = $model->getDirectoryPath();
					// Skip workspaces that are not installed on this environment
					// (no directory provisioned locally, e.g. cPanel-only tenants).
					if (!$dirPath || !is_dir($dirPath)) {
						continue;
					}
					// Single source of truth: the same map used by install/reinstall,
					// so any shared directory (assets, plugins, theme tpl, ...) stays in
					// sync between provisioning and this manual refresh.
					$targets = $model->getSymlinkMap();
					// Remove the existing destination links so they get recreated below
					// as relative symlinks (an older provisioning could have created them
					// with absolute targets, which break across different mount roots).
					foreach (array_values($targets) as $target) {
						if (is_link($target)) {
							@unlink($target);
						}
					}
					// (Re)create the static asset links as relative symlinks.
					\common\helpers\FileHelper::symlink($targets);
					Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllWorkspaces']));
				} catch(\Exception $e) {
					$errors[] = $e->getMessage();
					continue;
				}
			}
		}
		if (!empty($errors)) {
			$message = implode('<br>', $errors);
			Yii::$app->session->setFlash('error', $message);
		} else {
			$message = Yii::t('common', 'Records successfully updated.');
			if (Yii::$app->request->isAjax) {
				return $this->asJson([
					'success' => true,
					'message' => $message,
				]);
			}
			Yii::$app->session->setFlash('success', $message);
		}
		return $this->redirect(['index']);
	}

	/**
	 * Flushes the runtime file cache of every active installed workspace, so direct
	 * changes in master (settings pushes, database updates) become visible in the
	 * tenants immediately instead of being masked by their cached values.
	 *
	 * @return mixed
	 */
	public function actionCacheFlush()
	{
		$models = Workspace::find()
			->where([
				'status' => Workspace::STATUS_ACTIVE,
				'deleted' => Workspace::NO,
			])
			->all();
		$errors = [];
		$flushed = 0;
		foreach ($models as $model) {
			try {
				$dirPath = $model->getDirectoryPath();
				// Skip workspaces that are not installed on this environment
				// (no directory provisioned locally, e.g. cPanel-only tenants).
				if (!$dirPath || !is_dir($dirPath)) {
					continue;
				}
				$model->flushTenantCache();
				$flushed++;
			} catch (\Exception $e) {
				$errors[] = $model->code . ': ' . $e->getMessage();
			}
		}
		if (!empty($errors)) {
			Yii::$app->session->setFlash('error', implode('<br>', $errors));
		} else {
			$message = Yii::t('common', 'The cache of {n} workspaces has been flushed.', ['n' => $flushed]);
			if (Yii::$app->request->isAjax) {
				return $this->asJson([
					'success' => true,
					'message' => $message,
				]);
			}
			Yii::$app->session->setFlash('success', $message);
		}
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
				Yii::$app->eventLog->beginRecord($model);
				if ($model->restore()) {
					Yii::$app->eventLog->endRecord();
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

		Yii::$app->eventLog->beginRecord($model);
		if ($model->install(true)) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllWorkspaces']));
			Yii::$app->trigger('workspace.afterReinstall', new \yii\base\Event(['sender' => $model]));
			Yii::$app->eventLog->endRecord();
		} else {
			$response['success'] = false;
			$response['message']['title'] = Yii::t('common', 'Cannot reinstall the workspace.');
			// Show which step actually failed. install() collects the underlying error
			// (a refused SQL statement, a cPanel API rejection, an unwritable path) on the
			// model; without this the operator only sees the generic title and has to go
			// digging through backend/runtime/logs/app.log.
			if ($errors = array_merge([], ...array_values($model->getErrors()))) {
				$response['message']['body'] = $errors;
			}
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson($response);
		}

		Yii::$app->session->setFlash($response['success'] ? 'success' : 'error', [$response['message']]);

		return $this->redirect(['view', 'id' => $model->id]);
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
		$query = $modelName::find()->andWhere([
			'id' => $id,
		]);

		if ($asActiveQuery) {
			if (!$query->count()) {
				throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
			}
			return $query;
		}

		$query->andWhere(['deleted' => Workspace::NO]);
		if (($model = $query->one()) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
