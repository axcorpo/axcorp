<?php

namespace backend\modules\website\controllers;

use backend\controllers\MainController;
use backend\modules\website\models\CarouselItemForm;
use common\models\Carousel;
use common\models\CarouselItem;
use Yii;
use yii\db\Expression;
use yii\filters\AccessControl;
use yii\helpers\FileHelper;
use yii\web\NotFoundHttpException;

class CarouselItemController extends MainController
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
						'actions' => ['index', 'view'],
						'roles' => ['viewCarousel'],
					],
					[
						'allow' => true,
						'actions' => ['create'],
						'roles' => ['createCarousel'],
					],
					[
						'allow' => true,
						'actions' => ['update', 'reorder'],
						'roles' => ['updateCarousel'],
					],
					[
						'allow' => true,
						'actions' => ['delete'],
						'roles' => ['deleteCarousel'],
						'verbs' => ['POST'],
					],
					[
						'allow' => true,
						'actions' => ['restore'],
						'roles' => ['restoreCarousel'],
						'verbs' => ['POST'],
					],
				],
			],
		];
	}

	/**
	 * @inheritdoc
	 * @throws \yii\web\BadRequestHttpException
	 * @throws \yii\web\NotFoundHttpException if the model cannot be found
	 */
	public function beforeAction($action)
	{
		$model = Carousel::find()->where([
			'id' => Yii::$app->request->get('carousel_id'),
			'deleted' => Carousel::NO,
		]);

		if (!$model->exists()) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}

		return parent::beforeAction($action);
	}

	/**
	 * Lists all CarouselItem models.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		return $this->redirect(['carousel/update', 'id' => Yii::$app->request->get('carousel_id')], 301);
	}

	/**
	 * Displays a single CarouselItem model.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws \yii\web\NotFoundHttpException if the model cannot be found
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
	 * Creates a new CarouselItem model.
	 * If creation is successful, the browser will be redirected to the 'view' page.
	 *
	 * @return mixed
	 */
	public function actionCreate()
	{
		$model = new CarouselItemForm();
		$model->carousel_id = Yii::$app->request->get('carousel_id');

		$result = true;

		Yii::$app->eventLog
			->setData([
				'model' => 'common\models\Carousel',
				'module' => 'website-manager',
				'controller' => 'carousel',
				'action' => (Yii::$app->eventLog)::ACTION_UPDATE,
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
				'resource' => 'Carousel',
			])
			->beginRecord($model->carousel);

		if ($model->load(Yii::$app->request->post()) && ($result = $model->saveModel())) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllCarousels']));
			$model->carousel->touch('updated_at');
			Yii::$app->eventLog->endRecord();

			$message = Yii::t('common', 'Record has been created.');
			if (Yii::$app->request->isAjax) {
				return $this->asJson([
					'success' => true,
					'message' => $message,
				]);
			}
			Yii::$app->session->setFlash('success', $message);

			return $this->redirect(['carousel/update', 'id' => Yii::$app->request->get('carousel_id')]);
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson([
				'success' => (bool) $result,
				'data' => $this->renderAjax('create', [
					'model' => $model,
					'carousel' => $model->carousel,
				]),
			]);
		}

		return $this->render('create', [
			'model' => $model,
			'carousel' => $model->carousel,
		]);
	}

	/**
	 * Updates an existing CarouselItem model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws \yii\web\NotFoundHttpException if the model cannot be found
	 */
	public function actionUpdate($id)
	{
		$model = $this->findModel($id, CarouselItemForm::class);
		$result = true;

		Yii::$app->eventLog
			->setData([
				'model' => 'common\models\Carousel',
				'module' => 'website-manager',
				'controller' => 'carousel',
				'action' => (Yii::$app->eventLog)::ACTION_UPDATE,
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
				'resource' => 'Carousel',
			])
			->beginRecord($model->carousel);

		if ($model->load(Yii::$app->request->post()) && ($result = $model->saveModel())) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllCarousels']));
			$model->carousel->touch('updated_at');
			Yii::$app->eventLog->endRecord();

			$message = Yii::t('common', 'Record has been updated.');
			if (Yii::$app->request->isAjax) {
				return $this->asJson([
					'success' => true,
					'message' => $message,
				]);
			}
			Yii::$app->session->setFlash('success', $message);

			return $this->redirect(['carousel/update', 'id' => Yii::$app->request->get('carousel_id')]);
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson([
				'success' => (bool) $result,
				'data' => $this->renderAjax('update', [
					'model' => $model,
					'carousel' => $model->carousel,
				]),
			]);
		}

		return $this->render('update', [
			'model' => $model,
			'carousel' => $model->carousel,
		]);
	}

	/**
	 * Deletes existing CarouselItem models.
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
			$carouselModel = Carousel::findOne([
				'id' => Yii::$app->request->get('carousel_id'),
				'deleted' => Carousel::NO,
			]);
			Yii::$app->eventLog
				->setData([
					'model' => 'common\models\Carousel',
					'module' => 'website-manager',
					'controller' => 'carousel',
					'action' => (Yii::$app->eventLog)::ACTION_UPDATE,
					'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
					'resource' => 'Carousel',
				])
				->beginRecord($carouselModel);
			/** @var CarouselItem $model */
			foreach ($models->each() as $model) {
				if ($model->delete($isPermanent)) {
					$deletedModels[] = [
						'carousel_id' => $model->uuidOf('carousel_id'),
						'image' => $model->image,
					];
				} else {
					throw new \Exception();
				}
			}
			if ($isPermanent) {
				foreach ($deletedModels as $deletedModel) {
					$filePath = Yii::getAlias("@uploads/carousel/{$deletedModel['carousel_id']}/{$deletedModel['image']}");
					if (is_file($filePath)) {
						FileHelper::unlink($filePath);
					}
				}
			}
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllCarousels']));
			$carouselModel->touch('updated_at');
			Yii::$app->eventLog->endRecord();
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

		return $this->redirect(['carousel/update', 'id' => Yii::$app->request->get('carousel_id')]);
	}

	/**
	 * Restores CarouselItem models that are marked as deleted.
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
			/** @var CarouselItem $model */
			foreach ($models->each() as $model) {
				Yii::$app->eventLog->beginRecord($model);
				if ($model->restore()) {
					Yii::$app->eventLog->endRecord();
				} else {
					throw new \Exception();
				}
			}
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllCarousels']));
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
	 * Reorders CarouselItem models.
	 *
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionReorder()
	{
		if (!Yii::$app->request->isAjax) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}
		$response = [
			'success' => true,
			'message' => [
				'title' => Yii::t('common', 'Record has been updated.'),
			],
		];

		$dbTransaction = CarouselItem::getDb()->beginTransaction();
		try {
			$sortable = Yii::$app->request->post('sortable');
			$ids = array_column($sortable, 'id');
			$flippedIds = array_flip($ids);
			/** @var CarouselItemForm[] $models */
			$models = CarouselItemForm::find()
				->where([
					'id' => $ids,
					'status' => CarouselItem::STATUS_ACTIVE,
					'deleted' => CarouselItem::NO,
				])
				->orderBy(new Expression('[[sort_order]] IS NULL'))
				->addOrderBy(['sort_order' => SORT_ASC])
				->all();

			foreach ($models as $model) {
				// The posted sortable ids are canonical UUID strings, so index with the
				// model's UUID string rather than its raw BINARY(16) id.
				$sortableOrder = $flippedIds[$model->uuid];
				$model->sort_order = $sortableOrder + 1;
				if (!$model->saveModel()) {
					throw new \Exception();
				}
			}
			$dbTransaction->commit();
		} catch (\Exception $e) {
			$dbTransaction->rollBack();
			$response['success'] = false;
			$response['message']['title'] = Yii::t('common', 'Cannot update the record.');
		}

		return $this->asJson($response);
	}

	/**
	 * Finds the CarouselItem model(s) based on its primary key value.
	 * A 404 HTTP exception will be thrown if no record was found.
	 *
	 * @param int|array $id
	 * @param \yii\db\ActiveRecord|null $modelName
	 * @param bool $asActiveQuery
	 * @return \yii\db\ActiveQuery|\yii\db\ActiveRecord|CarouselItem|CarouselItemForm
	 * @throws NotFoundHttpException if no model was found
	 */
	protected function findModel($id, $modelName = null, $asActiveQuery = false)
	{
		$modelName = class_exists($modelName) ? $modelName : CarouselItem::class;
		$query = $modelName::find()
			->alias('mi')
			->joinWith(['carousel m'], false)
			->andWhere([
				'mi.id' => $id,
				'm.id' => Yii::$app->request->get('carousel_id'),
			]);

		if ($asActiveQuery) {
			if (!$query->count()) {
				throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
			}
			return $query;
		}

		$query->andWhere(['mi.deleted' => CarouselItem::NO]);
		if (($model = $query->one()) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
