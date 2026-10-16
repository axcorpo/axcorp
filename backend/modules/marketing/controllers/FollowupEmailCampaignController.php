<?php

namespace backend\modules\marketing\controllers;

use backend\controllers\MainController;
use backend\modules\marketing\models\FollowupEmailCampaignForm;
use backend\modules\marketing\models\FollowupEmailCampaignSearch;
use backend\modules\marketing\models\FollowupMarketingCampaign;
use common\models\MarketingRecipientSearchForm;
use Yii;
use yii\base\Model;
use yii\filters\AccessControl;
use yii\helpers\FileHelper;
use yii\web\NotFoundHttpException;

class FollowupEmailCampaignController extends MainController
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
						'actions' => ['index', 'view', 'dt-followup-email-campaigns'],
						'roles' => ['viewFollowupMarketingCampaign'],
					],
					[
						'allow' => true,
						'actions' => ['create'],
						'roles' => ['createFollowupMarketingCampaign'],
					],
					[
						'allow' => true,
						'actions' => ['update', 'start', 'restart', 'pause', 'stop'],
						'roles' => ['updateFollowupMarketingCampaign'],
					],
					[
						'allow' => true,
						'actions' => ['delete'],
						'roles' => ['deleteFollowupMarketingCampaign'],
						'verbs' => ['POST'],
					],
					[
						'allow' => true,
						'actions' => ['restore'],
						'roles' => ['restoreFollowupMarketingCampaign'],
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
			'dt-followup-email-campaigns' => FollowupEmailCampaignSearch::class,
		];
	}

	/**
	 * Lists all FollowupMarketingCampaign models.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		if (Yii::$app->request->get('deleted') == FollowupMarketingCampaign::YES) {
			if (!Yii::$app->settings->get('enableSoftDelete') || !Yii::$app->user->can('restoreFollowupMarketingCampaign')) {
				return $this->redirect(['index']);
			}
		}
		return $this->render('index');
	}

	/**
	 * Displays a single FollowupMarketingCampaign model.
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
	 * Creates a new FollowupMarketingCampaign model.
	 * If creation is successful, the browser will be redirected to the 'view' page.
	 *
	 * @return mixed
	 */
	public function actionCreate()
	{
		$model = new FollowupEmailCampaignForm();
		$searchModel = new MarketingRecipientSearchForm();
		$result = true;

		Yii::$app->eventLog->beginRecord($model);
		if ($model->load(Yii::$app->request->post()) && $searchModel->load(Yii::$app->request->post()) && Model::validateMultiple([$model, $searchModel])) {
			$model->filters = $searchModel->getAttributes();
			if ($result = $model->saveModel()) {
				Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllFollowupMarketingCampaigns']));
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
	 * Updates an existing FollowupMarketingCampaign model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionUpdate($id)
	{
		$model = $this->findModel($id, FollowupEmailCampaignForm::class);
		$searchModel = new MarketingRecipientSearchForm();
		$searchModel->load($model->filters, '');
		$result = true;

		Yii::$app->eventLog->beginRecord($model);
		if ($model->load(Yii::$app->request->post()) && $searchModel->load(Yii::$app->request->post()) && Model::validateMultiple([$model, $searchModel])) {
			$model->filters = $searchModel->getAttributes();
			if ($result = $model->saveModel()) {
				Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllFollowupMarketingCampaigns']));
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
	 * Deletes existing FollowupMarketingCampaign models.
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
			/** @var FollowupMarketingCampaign $model */
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
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllFollowupMarketingCampaigns']));
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
	 * Restores FollowupMarketingCampaign models that are marked as deleted.
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
			/** @var FollowupMarketingCampaign $model */
			foreach ($models->each() as $model) {
				Yii::$app->eventLog->beginRecord($model);
				if ($model->restore()) {
					$model->getScheduledTask()->restore();
					Yii::$app->eventLog->endRecord();
				} else {
					throw new \Exception();
				}
			}
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllFollowupMarketingCampaigns']));
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
	 * Updates an existing FollowupMarketingCampaign model status.
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
	 * Updates an existing FollowupMarketingCampaign model status.
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
	 * Finds the FollowupMarketingCampaign model(s) based on its primary key value.
	 * A 404 HTTP exception will be thrown if no record was found.
	 *
	 * @param int|array $id
	 * @param \yii\db\ActiveRecord|null $modelName
	 * @param bool $asActiveQuery
	 * @return \yii\db\ActiveQuery|\yii\db\ActiveRecord|FollowupMarketingCampaign|FollowupEmailCampaignForm
	 * @throws NotFoundHttpException if no model was found
	 */
	protected function findModel($id, $modelName = null, $asActiveQuery = false)
	{
		$modelName = class_exists($modelName) ? $modelName : FollowupMarketingCampaign::class;
		$query = $modelName::find()->andWhere([
			'id' => $id,
			'type' => FollowupMarketingCampaign::TYPE_FOLLOW_UP,
			'variant' => FollowupMarketingCampaign::VARIANT_EMAIL,
		]);

		if ($asActiveQuery) {
			if (!$query->count()) {
				throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
			}
			return $query;
		}

		$query->andWhere(['deleted' => FollowupMarketingCampaign::NO]);
		if (($model = $query->one()) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
