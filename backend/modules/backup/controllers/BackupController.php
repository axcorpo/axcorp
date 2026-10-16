<?php

namespace backend\modules\backup\controllers;

use backend\controllers\MainController;
use backend\modules\backup\models\BackupForm;
use backend\modules\backup\models\BackupSearch;
use tws\helpers\ArchiveHelper;
use tws\helpers\DbHelper;
use common\models\Backup;
use common\models\EventLog;
use Yii;
use yii\filters\AccessControl;
use yii\helpers\FileHelper;
use yii\web\NotFoundHttpException;

class BackupController extends MainController
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
						'actions' => ['index', 'view', 'dt-backups'],
						'roles' => ['viewBackup'],
					],
					[
						'allow' => true,
						'actions' => ['create'],
						'roles' => ['createBackup'],
					],
					[
						'allow' => true,
						'actions' => ['download'],
						'roles' => ['downloadBackup'],
					],
					[
						'allow' => true,
						'actions' => ['recover'],
						'roles' => ['recoverBackup'],
					],
					[
						'allow' => true,
						'actions' => ['delete'],
						'roles' => ['deleteBackup'],
						'verbs' => ['POST'],
					],
					[
						'allow' => true,
						'actions' => ['restore'],
						'roles' => ['restoreBackup'],
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
			'dt-backups' => BackupSearch::class,
		];
	}

	/**
	 * Lists all Backup models.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		if (Yii::$app->request->get('deleted') == Backup::YES) {
			if (!Yii::$app->settings->get('enableSoftDelete') || !Yii::$app->user->can('restoreBackup')) {
				return $this->redirect(['index']);
			}
		}
		return $this->render('index');
	}

	/**
	 * Displays a single Backup model.
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
	 * Creates a new Backup model.
	 * If creation is successful, the browser will be redirected to the 'view' page.
	 *
	 * @return mixed
	 */
	public function actionCreate()
	{
		$model = new Backup();

		$dbTransaction = Yii::$app->db->beginTransaction();
		try {
			Yii::$app->eventLog->beginRecord($model);
			$model->status = Backup::STATUS_RUNNING;
			$model->save();

			$dbFile = DbHelper::dump(Yii::$app->db, Yii::getAlias('@runtime/db.sql'), [
				'--ignore-table' => [
					preg_replace('/\W/', '', Backup::tableName()),
					preg_replace('/\W/', '', EventLog::tableName()),
				],
			]);
			$backupFile = ArchiveHelper::pack([
				$dbFile,
				Yii::getAlias("@uploads"),
			], Yii::getAlias("@backups/{$model->id}.zip"));
			FileHelper::unlink($dbFile);
			if (is_file($backupFile)) {
				$model->status = Backup::STATUS_COMPLETE;
				$model->file_size = filesize($backupFile);
				$model->save();
				Yii::$app->eventLog->endRecord();
				$dbTransaction->commit();
				Yii::$app->session->setFlash('success', Yii::t('common', 'Record has been created.'));
			} else {
				throw new \Exception();
			}
		} catch (\Exception $e) {
			$dbTransaction->rollBack();
			Yii::$app->session->setFlash('error', Yii::t('common', 'Cannot create the record.'));

			return $this->redirect(['index']);
		}

		return $this->redirect(['view', 'id' => $model->id]);
	}

	/**
	 * Downloads an existing Backup model file.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionDownload($id)
	{
		$model = $this->findModel($id, BackupForm::class);

		return Yii::$app->response->sendFile($model->getFilePath(), $model->getFileName());
	}

	/**
	 * Recovers an existing Backup model file.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionRecover($id)
	{
		$model = $this->findModel($id);

		$dbTransaction = Yii::$app->db->beginTransaction();
		try {
			Yii::$app->eventLog->beginRecord($model);

			if (!ArchiveHelper::unpack($model->getFilePath(), Yii::getAlias('@root'))) {
				throw new \Exception();
			}

			$dbFile = Yii::getAlias("@root/db.sql");
			if (!is_file($dbFile)) {
				throw new \Exception();
			}
			Yii::$app->db->createCommand()->setRawSql(file_get_contents($dbFile))->execute();
			FileHelper::unlink($dbFile);

			Yii::$app->eventLog->endRecord();
			Yii::$app->session->setFlash('success', Yii::t('common', 'Backup has been recovered.'));
			$dbTransaction->commit();
		} catch (\Exception $e) {
			Yii::$app->session->setFlash('error', Yii::t('common', 'Cannot recover the backup.'));
			$dbTransaction->rollBack();
		}

		return $this->redirect(['index']);
	}

	/**
	 * Deletes existing Backup models.
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
			/** @var Backup $model */
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
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllBackups']));
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
	 * Restores Backup models that are marked as deleted.
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
			/** @var Backup $model */
			foreach ($models->each() as $model) {
				Yii::$app->eventLog->beginRecord($model);
				if ($model->restore()) {
					Yii::$app->eventLog->endRecord();
				} else {
					throw new \Exception();
				}
			}
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllBackups']));
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
	 * Finds the Backup model(s) based on its primary key value.
	 * A 404 HTTP exception will be thrown if no record was found.
	 *
	 * @param int|array $id
	 * @param \yii\db\ActiveRecord|null $modelName
	 * @param bool $asActiveQuery
	 * @return \yii\db\ActiveQuery|\yii\db\ActiveRecord|Backup|BackupForm
	 * @throws NotFoundHttpException if no model was found
	 */
	protected function findModel($id, $modelName = null, $asActiveQuery = false)
	{
		$modelName = class_exists($modelName) ? $modelName : Backup::class;
		$query = $modelName::find()->andWhere([
			'id' => $id,
		]);

		if ($asActiveQuery) {
			if (!$query->count()) {
				throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
			}
			return $query;
		}

		$query->andWhere(['deleted' => Backup::NO]);
		if (($model = $query->one()) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
