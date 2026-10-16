<?php

namespace backend\modules\marketing\controllers;

use backend\controllers\MainController;
use backend\modules\marketing\models\DirectEmailCampaignForm;
use backend\modules\marketing\models\DirectEmailCampaignSearch;
use backend\modules\marketing\models\DirectMarketingCampaign;
use common\models\MarketingRecipientSearchForm;
use Yii;
use yii\base\Model;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\web\NotFoundHttpException;

class DirectEmailCampaignController extends MainController
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
						'actions' => ['index', 'view', 'dt-direct-email-campaigns'],
						'roles' => ['viewDirectMarketingCampaign'],
					],
					[
						'allow' => true,
						'actions' => ['create'],
						'roles' => ['createDirectMarketingCampaign'],
					],
					[
						'allow' => true,
						'actions' => ['update', 'start', 'restart', 'pause', 'stop'],
						'roles' => ['updateDirectMarketingCampaign'],
					],
					[
						'allow' => true,
						'actions' => ['delete'],
						'roles' => ['deleteDirectMarketingCampaign'],
						'verbs' => ['POST'],
					],
					[
						'allow' => true,
						'actions' => ['restore'],
						'roles' => ['restoreDirectMarketingCampaign'],
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
			'dt-direct-email-campaigns' => DirectEmailCampaignSearch::class,
		];
	}

	/**
	 * Lists all DirectMarketingCampaign models.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		if (Yii::$app->request->get('deleted') == DirectMarketingCampaign::YES) {
			if (!Yii::$app->settings->get('enableSoftDelete') || !Yii::$app->user->can('restoreDirectMarketingCampaign')) {
				return $this->redirect(['index']);
			}
		}
		return $this->render('index');
	}

	/**
	 * Displays a single DirectMarketingCampaign model.
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
	 * Creates a new DirectMarketingCampaign model.
	 * If creation is successful, the browser will be redirected to the 'view' page.
	 *
	 * @return mixed
	 */
	public function actionCreate()
	{
		$model = new DirectEmailCampaignForm();
		$searchModel = new MarketingRecipientSearchForm();
		$result = true;

		Yii::$app->eventLog->beginRecord($model);
		if ($model->load(Yii::$app->request->post()) && $searchModel->load(Yii::$app->request->post()) && Model::validateMultiple([$model, $searchModel])) {
			$model->filters = $searchModel->getAttributes();
			$model->targetedRecipients = ArrayHelper::getColumn($searchModel->search(), 'id');
			if ($result = $model->saveModel()) {
				Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllDirectMarketingCampaigns']));
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
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson([
				'success' => (bool) $result,
				'data' => $this->renderAjax('create', [
					'model' => $model,
					'searchModel' => $searchModel,
				]),
			]);
		}

		return $this->render('create', [
			'model' => $model,
			'searchModel' => $searchModel,
		]);
	}

	/**
	 * Updates an existing DirectMarketingCampaign model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionUpdate($id)
	{
		$model = $this->findModel($id, DirectEmailCampaignForm::class);
		$searchModel = new MarketingRecipientSearchForm();
		$searchModel->load($model->filters, '');
		$result = true;

		Yii::$app->eventLog->beginRecord($model);
		if ($model->load(Yii::$app->request->post()) && $searchModel->load(Yii::$app->request->post()) && Model::validateMultiple([$model, $searchModel])) {
			$model->filters = $searchModel->getAttributes();
			$model->targetedRecipients = ArrayHelper::getColumn($searchModel->search(), 'id');
			if ($result = $model->saveModel()) {
				Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllDirectMarketingCampaigns']));
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
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson([
				'success' => (bool) $result,
				'data' => $this->renderAjax('update', [
					'model' => $model,
					'searchModel' => $searchModel,
				]),
			]);
		}

		return $this->render('update', [
			'model' => $model,
			'searchModel' => $searchModel,
		]);
	}

	/**
	 * Deletes existing DirectMarketingCampaign models.
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
			/** @var DirectMarketingCampaign $model */
			foreach ($models->each() as $model) {
				Yii::$app->eventLog
					->setData([
						'operation' => $isPermanent ? (Yii::$app->eventLog)::ACTION_DELETE : (Yii::$app->eventLog)::ACTION_SOFT_DELETE,
					])
					->beginRecord($model);
				if ($model->delete($isPermanent)) {
					$model->getScheduledTask()->delete($isPermanent);
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
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllDirectMarketingCampaigns']));
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
	 * Restores DirectMarketingCampaign models that are marked as deleted.
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
			/** @var DirectMarketingCampaign $model */
			foreach ($models->each() as $model) {
				Yii::$app->eventLog->beginRecord($model);
				if ($model->restore()) {
					$model->getScheduledTask()->restore();
					Yii::$app->eventLog->endRecord();
				} else {
					throw new \Exception();
				}
			}
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllDirectMarketingCampaigns']));
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
	 * Updates an existing DirectMarketingCampaign model status.
	 * If update is successful, the browser will be redirected to the 'index' page.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionStart($id)
	{
		$model = $this->findModel($id);

		Yii::$app->eventLog
			->setData([
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
			])
			->beginRecord($model);

		if ($result = $model->startCampaign()) {
			Yii::$app->eventLog->endRecord();
		}

		$responseData = [
			'success' => (bool) $result,
			'message' => $result ?
				Yii::t('common', 'Record has been updated.') :
				Yii::t('common', 'Cannot update the record.'),
		];

		if (Yii::$app->request->isAjax) {
			return $this->asJson($responseData);
		}

		Yii::$app->session->setFlash($responseData['success'] ? 'success' : 'error', $responseData['message']);

		return $this->redirect(['index']);
	}

	/**
	 * Updates an existing DirectMarketingCampaign model status.
	 * If update is successful, the browser will be redirected to the 'index' page.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionRestart($id)
	{
		$model = $this->findModel($id);

		Yii::$app->eventLog
			->setData([
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
			])
			->beginRecord($model);

		if ($result = $model->restartCampaign()) {
			Yii::$app->eventLog->endRecord();
		}

		$responseData = [
			'success' => (bool) $result,
			'message' => $result ?
				Yii::t('common', 'Record has been updated.') :
				Yii::t('common', 'Cannot update the record.'),
		];

		if (Yii::$app->request->isAjax) {
			return $this->asJson($responseData);
		}

		Yii::$app->session->setFlash($responseData['success'] ? 'success' : 'error', $responseData['message']);

		return $this->redirect(['index']);
	}

	/**
	 * Updates an existing DirectMarketingCampaign model status.
	 * If update is successful, the browser will be redirected to the 'index' page.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionPause($id)
	{
		$model = $this->findModel($id);

		Yii::$app->eventLog
			->setData([
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
			])
			->beginRecord($model);

		if ($result = $model->pauseCampaign()) {
			Yii::$app->eventLog->endRecord();
		}

		$responseData = [
			'success' => (bool) $result,
			'message' => $result ?
				Yii::t('common', 'Record has been updated.') :
				Yii::t('common', 'Cannot update the record.'),
		];

		if (Yii::$app->request->isAjax) {
			return $this->asJson($responseData);
		}

		Yii::$app->session->setFlash($responseData['success'] ? 'success' : 'error', $responseData['message']);

		return $this->redirect(['index']);
	}

	/**
	 * Updates an existing DirectMarketingCampaign model status.
	 * If update is successful, the browser will be redirected to the 'index' page.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionStop($id)
	{
		$model = $this->findModel($id);

		Yii::$app->eventLog
			->setData([
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
			])
			->beginRecord($model);

		if ($result = $model->stopCampaign()) {
			Yii::$app->eventLog->endRecord();
		}

		$responseData = [
			'success' => (bool) $result,
			'message' => $result ?
				Yii::t('common', 'Record has been updated.') :
				Yii::t('common', 'Cannot update the record.'),
		];

		if (Yii::$app->request->isAjax) {
			return $this->asJson($responseData);
		}

		Yii::$app->session->setFlash($responseData['success'] ? 'success' : 'error', $responseData['message']);

		return $this->redirect(['index']);
	}

	/**
	 * Finds the DirectMarketingCampaign model(s) based on its primary key value.
	 * A 404 HTTP exception will be thrown if no record was found.
	 *
	 * @param int|array $id
	 * @param \yii\db\ActiveRecord|null $modelName
	 * @param bool $asActiveQuery
	 * @return \yii\db\ActiveQuery|\yii\db\ActiveRecord|DirectMarketingCampaign|DirectEmailCampaignForm
	 * @throws NotFoundHttpException if no model was found
	 */
	protected function findModel($id, $modelName = null, $asActiveQuery = false)
	{
		$modelName = class_exists($modelName) ? $modelName : DirectMarketingCampaign::class;
		$query = $modelName::find()->andWhere([
			'id' => $id,
			'type' => DirectMarketingCampaign::TYPE_DIRECT,
			'variant' => DirectMarketingCampaign::VARIANT_EMAIL,
		]);

		if ($asActiveQuery) {
			if (!$query->count()) {
				throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
			}
			return $query;
		}

		$query->andWhere(['deleted' => DirectMarketingCampaign::NO]);
		if (($model = $query->one()) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
