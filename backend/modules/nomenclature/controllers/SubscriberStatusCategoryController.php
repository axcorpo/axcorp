<?php

namespace backend\modules\nomenclature\controllers;

use backend\controllers\MainController;
use backend\modules\nomenclature\models\SubscriberStatusCategoryForm;
use backend\modules\nomenclature\models\SubscriberStatusCategorySearch;
use common\models\SubscriberStatusCategory;
use Yii;
use yii\caching\TagDependency;
use yii\filters\AccessControl;
use yii\helpers\FileHelper;
use yii\web\NotFoundHttpException;

class SubscriberStatusCategoryController extends MainController
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
						'actions' => ['index', 'view', 'dt-subscriber-status-categories'],
						'roles' => ['manageSubscriberStatusCategory'],
					],
					[
						'allow' => true,
						'actions' => ['create'],
						'roles' => ['manageSubscriberStatusCategory'],
					],
					[
						'allow' => true,
						'actions' => ['update'],
						'roles' => ['manageSubscriberStatusCategory'],
					],
					[
						'allow' => true,
						'actions' => ['delete', 'bulk-delete'],
						'roles' => ['manageSubscriberStatusCategory'],
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
			'dt-subscriber-status-categories' => SubscriberStatusCategorySearch::class,
		];
	}

	/**
	 * Lists all SubscriberStatusCategory models.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		return $this->render('index');
	}

	/**
	 * Displays a single SubscriberStatusCategory model.
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
	 * Creates a new SubscriberStatusCategory model.
	 * If creation is successful, the browser will be redirected to the 'view' page.
	 *
	 * @return mixed
	 * @throws \yii\db\Exception
	 */
	public function actionCreate()
	{
		$model = new SubscriberStatusCategoryForm();

		$result = true;

		Yii::$app->eventLog->beginRecord($model);

		if ($model->load(Yii::$app->request->post()) && ($result = $model->saveModel())) {
			TagDependency::invalidate(Yii::$app->cache, 'findAllSubscriberStatusCategories');

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
	 * Updates an existing SubscriberStatusCategory model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws \Exception
	 * @throws \Throwable
	 * @throws \yii\db\Exception
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionUpdate($id)
	{
		$model = $this->findModel($id, SubscriberStatusCategoryForm::class);
		$result = true;

		Yii::$app->eventLog->beginRecord($model);

		if ($model->load(Yii::$app->request->post()) && ($result = $model->saveModel())) {
			TagDependency::invalidate(Yii::$app->cache, 'findAllSubscriberStatusCategories');

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
	 * Deletes an existing SubscriberStatusCategory model.
	 * If deletion is successful, JSON is returned or the browser will be redirected to the 'index' page.
	 *
	 * @param $id
	 * @return mixed
	 * @throws \Exception
	 * @throws \Throwable
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionDelete($id)
	{
		$model = $this->findModel($id);
		$response = [
			'success' => true,
			'message' => [
				'title' => Yii::t('common', 'Record successfully deleted.'),
			],
		];

		try {
			Yii::$app->eventLog->beginRecord($model);

			if ($result = $model->delete()) {
				FileHelper::removeDirectory(Yii::getAlias("@uploads/{$this->getParentModuleId()}/{$this->id}/{$id}"));

				TagDependency::invalidate(Yii::$app->cache, 'findAllSubscriberStatusCategories');

				Yii::$app->eventLog->endRecord();
			}
		} catch (\Exception $e) {
			$response['success'] = false;
			$response['message']['title'] = Yii::t('common', 'Cannot delete the record.');
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson($response);
		}

		Yii::$app->session->setFlash($response['success'] ? 'success' : 'error', [$response['message']]);

		return $this->redirect(['index']);
	}

	/**
	 * Bulk deletes existing SubscriberStatusCategory models.
	 * If deletion is successful, JSON is returned or the browser will be redirected to the 'index' page.
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function actionBulkDelete()
	{
		$models = SubscriberStatusCategory::find()->where(['id' => Yii::$app->request->post('selection')]);
		$response = [
			'success' => true,
			'message' => [
				'title' => Yii::t('common', 'Records successfully deleted.'),
			],
		];

		$transaction = Yii::$app->getDb()->beginTransaction();
		try {
			$deletedModels = [];

			foreach ($models->each() as $model) {
				Yii::$app->eventLog
					->setData([
						'operation' => (Yii::$app->eventLog)::ACTION_DELETE,
					])
					->beginRecord($model);

				if ($model->delete()) {
					$deletedModels[] = $model->uuid;

					Yii::$app->eventLog->endRecord();
				} else {
					throw new \Exception();
				}
			}

			foreach ($deletedModels as $deletedModel) {
				FileHelper::removeDirectory(Yii::getAlias("@uploads/{$this->getParentModuleId()}/{$this->id}/{$deletedModel}"));
			}

			TagDependency::invalidate(Yii::$app->cache, 'findAllSubscriberStatusCategories');

			$transaction->commit();
		} catch (\Exception $e) {
			$transaction->rollBack();

			$response['success'] = false;
			$response['message']['title'] = Yii::t('common', 'Cannot delete the records.');
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson($response);
		}

		Yii::$app->session->setFlash($response['success'] ? 'success' : 'error', [$response['message']]);

		return $this->redirect(['index']);
	}

	/**
	 * Finds the SubscriberStatusCategory model based on its primary key value.
	 * If the model is not found, a 404 HTTP exception will be thrown.
	 *
	 * @param integer $id
	 * @param \yii\db\ActiveRecord|null $modelName
	 * @return \yii\db\ActiveRecord|SubscriberStatusCategoryForm the loaded model
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	protected function findModel($id, $modelName = null)
	{
		$modelName = class_exists($modelName) ? $modelName : SubscriberStatusCategory::class;

		if (($model = $modelName::findOne($id)) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
