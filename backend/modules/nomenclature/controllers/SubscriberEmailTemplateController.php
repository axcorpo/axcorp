<?php

namespace backend\modules\nomenclature\controllers;

use backend\controllers\MainController;
use backend\modules\nomenclature\models\SubscriberEmailTemplateForm;
use backend\modules\nomenclature\models\SubscriberEmailTemplateSearch;
use backend\modules\nomenclature\models\Template;
use Yii;
use yii\caching\TagDependency;
use yii\filters\AccessControl;
use yii\helpers\FileHelper;
use yii\web\NotFoundHttpException;

class SubscriberEmailTemplateController extends MainController
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
						'actions' => ['index', 'view', 'dt-subscriber-email-templates'],
						'roles' => ['manageSubscriberEmailTemplate'],
					],
					[
						'allow' => true,
						'actions' => ['create'],
						'roles' => ['manageSubscriberEmailTemplate'],
					],
					[
						'allow' => true,
						'actions' => ['update'],
						'roles' => ['manageSubscriberEmailTemplate'],
					],
					[
						'allow' => true,
						'actions' => ['delete', 'bulk-delete'],
						'roles' => ['manageSubscriberEmailTemplate'],
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
			'dt-subscriber-email-templates' => SubscriberEmailTemplateSearch::class,
		];
	}

	/**
	 * Lists all Template models.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		return $this->render('index');
	}

	/**
	 * Displays a single Template model.
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
	 * Creates a new Template model.
	 * If creation is successful, the browser will be redirected to the 'view' page.
	 *
	 * @return mixed
	 * @throws \yii\db\Exception
	 */
	public function actionCreate()
	{
		$model = new SubscriberEmailTemplateForm();

		Yii::$app->eventLog->beginRecord($model);

		if ($model->load(Yii::$app->request->post()) && $model->saveModel()) {
			TagDependency::invalidate(Yii::$app->cache, Template::SUBSCRIBER_EMAIL . 'findAllTemplates');

			Yii::$app->eventLog->endRecord();

			Yii::$app->session->setFlash('success', Yii::t('common', 'Record has been created.'));

			return $this->redirect(['view', 'id' => $model->id]);
		}

		return $this->render('create', [
			'model' => $model,
		]);
	}

	/**
	 * Updates an existing Template model.
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
		$model = $this->findModel($id, SubscriberEmailTemplateForm::class);

		Yii::$app->eventLog->beginRecord($model);

		if ($model->load(Yii::$app->request->post()) && $model->saveModel()) {
			TagDependency::invalidate(Yii::$app->cache, Template::SUBSCRIBER_EMAIL . 'findAllTemplates');

			Yii::$app->eventLog->endRecord();

			Yii::$app->session->setFlash('success', Yii::t('common', 'Record has been updated.'));

			return $this->redirect(['view', 'id' => $model->id]);
		}

		return $this->render('update', [
			'model' => $model,
		]);
	}

	/**
	 * Deletes an existing Template model.
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

				TagDependency::invalidate(Yii::$app->cache, Template::SUBSCRIBER_EMAIL . 'findAllTemplates');

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
	 * Bulk deletes existing Template models.
	 * If deletion is successful, JSON is returned or the browser will be redirected to the 'index' page.
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function actionBulkDelete()
	{
		$models = Template::find()->andWhere(['id' => Yii::$app->request->post('selection')]);
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

			TagDependency::invalidate(Yii::$app->cache, Template::SUBSCRIBER_EMAIL . 'findAllTemplates');

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
	 * Finds the Template model based on its primary key value.
	 * If the model is not found, a 404 HTTP exception will be thrown.
	 *
	 * @param integer $id
	 * @param \yii\db\ActiveRecord|null $modelName
	 * @return \yii\db\ActiveRecord|SubscriberEmailTemplateForm the loaded model
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	protected function findModel($id, $modelName = null)
	{
		$modelName = class_exists($modelName) ? $modelName : Template::class;
		$model = $modelName::findOne([
			'id' => $id,
			'type' => Template::SUBSCRIBER_EMAIL,
		]);

		if ($model !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
