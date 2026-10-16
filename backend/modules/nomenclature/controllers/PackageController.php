<?php

namespace backend\modules\nomenclature\controllers;

use backend\controllers\MainController;
use backend\modules\nomenclature\models\PackageForm;
use backend\modules\nomenclature\models\PackageFeatureForm;
use backend\modules\nomenclature\models\PackageFeatureSearch;
use backend\modules\nomenclature\models\PackageSearch;
use backend\modules\nomenclature\models\SubscriptionUpgradeForm;
use common\models\Package;
use common\models\PackageFeature;
use common\models\Subscription;
use Mpdf\Tag\Sub;
use Yii;
use yii\db\Expression;
use yii\filters\AccessControl;
use yii\helpers\FileHelper;
use yii\web\NotFoundHttpException;

class PackageController extends MainController
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
						'actions' => ['index', 'view', 'dt-packages'],
						'roles' => ['viewPackage'],
					],
					[
						'allow' => true,
						'actions' => ['create'],
						'roles' => ['createPackage'],
					],
					[
						'allow' => true,
						'actions' => ['update', 'dt-package-features', 'create-package-feature', 'update-package-feature'],
						'roles' => ['updatePackage'],
					],
					[
						'allow' => true,
						'actions' => ['delete-package-feature'],
						'roles' => ['updatePackage'],
						'verbs' => ['POST'],
					],
					[
						'allow' => true,
						'actions' => ['delete'],
						'roles' => ['deletePackage'],
						'verbs' => ['POST'],
					],
					[
						'allow' => true,
						'actions' => ['restore'],
						'roles' => ['restorePackage'],
						'verbs' => ['POST'],
					],
                    [
                        'allow' => true,
                        'actions' => ['upgrade'],
                        'roles' => ['updatePackage'],
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
			'dt-packages' => PackageSearch::class,
			'dt-package-features' => PackageFeatureSearch::class,
		];
	}

	/**
	 * Lists all Package models.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		if (Yii::$app->request->get('deleted') == Package::YES) {
			if (!Yii::$app->settings->get('enableSoftDelete') || !Yii::$app->user->can('restorePackage')) {
				return $this->redirect(['index']);
			}
		}
		return $this->render('index');
	}

	/**
	 * Displays a single Package model.
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
	 * Creates a new Package model.
	 * If creation is successful, the browser will be redirected to the 'view' page.
	 *
	 * @return mixed
	 */
	public function actionCreate()
	{
		$model = new PackageForm();
		$result = true;

		Yii::$app->eventLog->beginRecord($model);
		if ($model->load(Yii::$app->request->post()) && ($result = $model->saveModel())) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllPackages']));
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
	 * Updates an existing Package model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionUpdate($id)
	{
		$model = $this->findModel($id, PackageForm::class);
		$result = true;

		Yii::$app->eventLog->beginRecord($model);
		if ($model->load(Yii::$app->request->post()) && ($result = $model->saveModel())) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllPackages']));
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
     * Updates an existing Package model.
     * If update is successful, the browser will be redirected to the 'view' page.
     *
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpgrade($id)
    {
		$emptyModel = new SubscriptionUpgradeForm();
    	$package = Package::findOne([
    		'id' => $id,
		]);
        $result = true;
        $models = Subscription::find()
			->where([
				'package_id' => $id,
			])
            ->andWhere([
                'deleted' => Subscription::NO,
            ])
			->andWhere(new Expression("TIMESTAMPDIFF(SECOND, [[created_at]], '{$package->updated_at}') >= 0"));
		foreach ($models->each() as $model) {
			$currentModel = $this->findModel($model->id, SubscriptionUpgradeForm::class);
			$eventLog = new \backend\modules\eventlog\components\EventLog([
				'model' => Yii::$app->eventLog->model,
			]);

			$eventLog->beginRecord($currentModel);

			if ($result &= $currentModel->saveModel()) {

				Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllSubscriptions']));

				$eventLog->endRecord();
			}
		}

		if ($result) {
			$message = Yii::t('common', 'Records have been updated.');

			if (Yii::$app->request->isAjax) {
				return $this->asJson([
					'success' => true,
					'message' => $message,
				]);
			}

			Yii::$app->session->setFlash('success', $message);
			return $this->redirect(['index']);
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson([
				'success' => (bool) $result,
				'data' => $this->renderAjax('_upgrade', [
					'model' => $emptyModel,
				]),
			]);
		}

        return $this->render('upgrade', [
            'model' => $emptyModel,
        ]);
    }

	/**
	 * Deletes existing Package models.
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
			/** @var Package $model */
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
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllPackages']));
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
	 * Restores Package models that are marked as deleted.
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
			/** @var Package $model */
			foreach ($models->each() as $model) {
				Yii::$app->eventLog->beginRecord($model);
				if ($model->restore()) {
					Yii::$app->eventLog->endRecord();
				} else {
					throw new \Exception();
				}
			}
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllPackages']));
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
	 * Finds the Package model(s) based on its primary key value.
	 * A 404 HTTP exception will be thrown if no record was found.
	 *
	 * @param int|array $id
	 * @param \yii\db\ActiveRecord|null $modelName
	 * @param bool $asActiveQuery
	 * @return \yii\db\ActiveQuery|\yii\db\ActiveRecord|Package|PackageForm
	 * @throws NotFoundHttpException if no model was found
	 */
	/**
	 * Adds a feature to a package (modal form).
	 *
	 * @param int $package_id
	 * @return mixed
	 */
	public function actionCreatePackageFeature($package_id)
	{
		$model = new PackageFeatureForm();
		$model->package_id = $package_id;
		$result = true;

		if ($model->load(Yii::$app->request->post()) && ($result = $model->save())) {
			$message = Yii::t('common', 'Record has been created.');
			if (Yii::$app->request->isAjax) {
				return $this->asJson([
					'success' => true,
					'message' => $message,
				]);
			}
			Yii::$app->session->setFlash('success', $message);
			return $this->redirect(['update', 'id' => $package_id]);
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson([
				'success' => (bool) $result,
				'data' => $this->renderAjax('_feature-form', [
					'model' => $model,
				]),
			]);
		}

		return $this->render('_feature-form', [
			'model' => $model,
		]);
	}

	/**
	 * Updates a package feature (modal form).
	 *
	 * @param int $id
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionUpdatePackageFeature($id)
	{
		$model = PackageFeatureForm::findOne(['id' => $id, 'deleted' => PackageFeature::NO]);
		if (!$model) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}
		$result = true;

		if ($model->load(Yii::$app->request->post()) && ($result = $model->save())) {
			$message = Yii::t('common', 'Record has been updated.');
			if (Yii::$app->request->isAjax) {
				return $this->asJson([
					'success' => true,
					'message' => $message,
				]);
			}
			Yii::$app->session->setFlash('success', $message);
			return $this->redirect(['update', 'id' => $model->package_id]);
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson([
				'success' => (bool) $result,
				'data' => $this->renderAjax('_feature-form', [
					'model' => $model,
				]),
			]);
		}

		return $this->render('_feature-form', [
			'model' => $model,
		]);
	}

	/**
	 * Deletes a package feature.
	 *
	 * @param int $id
	 * @return mixed
	 */
	public function actionDeletePackageFeature($id)
	{
		$model = PackageFeature::findOne(['id' => $id, 'deleted' => PackageFeature::NO]);
		$response = [
			'success' => true,
			'message' => [
				'title' => Yii::t('common', 'The delete operation was successful.'),
			],
		];
		// Package features have no trash: the row is removed outright, regardless of the
		// soft-delete setting that the packages themselves honour.
		if (!$model || !$model->delete(true)) {
			$response['success'] = false;
			$response['message']['title'] = Yii::t('common', 'The delete operation was unsuccessful.');
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson($response);
		}
		Yii::$app->session->setFlash($response['success'] ? 'success' : 'error', [$response['message']]);
		return $this->redirect(['index']);
	}

	protected function findModel($id, $modelName = null, $asActiveQuery = false)
	{
		$modelName = class_exists($modelName) ? $modelName : Package::class;
		$query = $modelName::find()->andWhere([
			'id' => $id,
		]);

		if ($asActiveQuery) {
			if (!$query->count()) {
				throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
			}
			return $query;
		}

		$query->andWhere(['deleted' => Package::NO]);
		if (($model = $query->one()) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
