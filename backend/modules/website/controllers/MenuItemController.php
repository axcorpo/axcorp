<?php

namespace backend\modules\website\controllers;

use backend\controllers\MainController;
use backend\modules\website\models\MenuItemForm;
use common\models\Menu;
use common\models\MenuItem;
use Yii;
use yii\db\Expression;
use yii\filters\AccessControl;
use yii\helpers\FileHelper;
use yii\web\NotFoundHttpException;

class MenuItemController extends MainController
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
						'roles' => ['viewMenu'],
					],
					[
						'allow' => true,
						'actions' => ['create'],
						'roles' => ['createMenu'],
					],
					[
						'allow' => true,
						'actions' => ['update', 'reorder'],
						'roles' => ['updateMenu'],
					],
					[
						'allow' => true,
						'actions' => ['delete'],
						'roles' => ['deleteMenu'],
						'verbs' => ['POST'],
					],
					[
						'allow' => true,
						'actions' => ['restore'],
						'roles' => ['restoreMenu'],
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
		$model = Menu::find()->where([
			'id' => Yii::$app->request->get('menu_id'),
			'deleted' => Menu::NO,
		]);

		if (!$model->exists()) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}

		return parent::beforeAction($action);
	}

	/**
	 * Lists all MenuItem models.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		return $this->redirect(['menu/update', 'id' => Yii::$app->request->get('menu_id')], 301);
	}

	/**
	 * Displays a single MenuItem model.
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
	 * Creates a new MenuItem model.
	 * If creation is successful, the browser will be redirected to the 'view' page.
	 *
	 * @return mixed
	 */
	public function actionCreate()
	{
		$model = new MenuItemForm();
		$model->menu_id = Yii::$app->request->get('menu_id');
		$result = true;

		Yii::$app->eventLog
			->setData([
				'model' => 'common\models\Menu',
				'module' => 'website-manager',
				'controller' => 'menu',
				'action' => (Yii::$app->eventLog)::ACTION_UPDATE,
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
				'resource' => 'Menu',
			])
			->beginRecord($model->menu);

		if ($model->load(Yii::$app->request->post()) && ($result = $model->save())) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllMenus']));
			Menu::invalidateMenuCache($model->id, $model->menu_id);

			$model->menu->touch('updated_at');
			Yii::$app->eventLog->endRecord();

			$message = Yii::t('common', 'Record has been created.');
			if (Yii::$app->request->isAjax) {
				return $this->asJson([
					'success' => true,
					'message' => $message,
				]);
			}
			Yii::$app->session->setFlash('success', $message);

			return $this->redirect(['menu/update', 'id' => Yii::$app->request->get('menu_id')]);
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson([
				'success' => (bool) $result,
				'data' => $this->renderAjax('create', [
					'model' => $model,
					'menu' => $model->menu,
				]),
			]);
		}

		return $this->render('create', [
			'model' => $model,
			'menu' => $model->menu,
		]);
	}

	/**
	 * Updates an existing MenuItem model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws \yii\web\NotFoundHttpException if the model cannot be found
	 */
	public function actionUpdate($id)
	{
		$model = $this->findModel($id, MenuItemForm::class);
		$result = true;

		Yii::$app->eventLog
			->setData([
				'model' => 'common\models\Menu',
				'module' => 'website-manager',
				'controller' => 'menu',
				'action' => (Yii::$app->eventLog)::ACTION_UPDATE,
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
				'resource' => 'Menu',
			])
			->beginRecord($model->menu);
		if ($model->load(Yii::$app->request->post()) && ($result = $model->save())) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllMenus']));
			Menu::invalidateMenuCache($model->id, $model->menu_id);

			$model->menu->touch('updated_at');
			Yii::$app->eventLog->endRecord();

			$message = Yii::t('common', 'Record has been updated.');
			if (Yii::$app->request->isAjax) {
				return $this->asJson([
					'success' => true,
					'message' => $message,
				]);
			}
			Yii::$app->session->setFlash('success', $message);

			return $this->redirect(['menu/update', 'id' => Yii::$app->request->get('menu_id')]);
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson([
				'success' => (bool) $result,
				'data' => $this->renderAjax('update', [
					'model' => $model,
					'menu' => $model->menu,
				]),
			]);
		}

		return $this->render('update', [
			'model' => $model,
			'menu' => $model->menu,
		]);
	}

	/**
	 * Deletes existing MenuItem models.
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
			$menuModel = Menu::findOne([
				'id' => Yii::$app->request->get('menu_id'),
				'deleted' => Menu::NO,
			]);
			Yii::$app->eventLog
				->setData([
					'model' => 'common\models\Menu',
					'module' => 'website-manager',
					'controller' => 'menu',
					'action' => (Yii::$app->eventLog)::ACTION_UPDATE,
					'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
					'resource' => 'Menu',
				])
				->beginRecord($menuModel);
			/** @var MenuItem $model */
			foreach ($models->each() as $model) {
				if ($model->delete($isPermanent)) {
					$deletedModels[] = $model->uuid;
				} else {
					throw new \Exception();
				}
				Menu::invalidateMenuCache($model->id, $model->menu_id);
			}
			if ($isPermanent) {
				foreach ($deletedModels as $deletedModel) {
					FileHelper::removeDirectory(Yii::getAlias("@uploads/{$this->id}/{$deletedModel}"));
				}
			}
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllMenus']));

			$menuModel->touch('updated_at');
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

		return $this->redirect(['menu/update', 'id' => Yii::$app->request->get('menu_id')]);
	}

	/**
	 * Restores MenuItem models that are marked as deleted.
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
			/** @var MenuItem $model */
			foreach ($models->each() as $model) {
				Yii::$app->eventLog->beginRecord($model);
				if ($model->restore()) {
					Yii::$app->eventLog->endRecord();
				} else {
					throw new \Exception();
				}
				Menu::invalidateMenuCache($model->id, $model->menu_id);
			}
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllMenus']));
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
	 * Reorders MenuItem models.
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

		$dbTransaction = MenuItem::getDb()->beginTransaction();
		try {
			$sortable = Yii::$app->request->post('sortable');
			$ids = array_column($sortable, 'id');
			$flippedIds = array_flip($ids);
			/** @var MenuItemForm[] $models */
			$models = MenuItemForm::find()
				->where([
					'id' => $ids,
					'status' => MenuItem::STATUS_ACTIVE,
					'deleted' => MenuItem::NO,
				])
				->orderBy(new Expression('[[sort_order]] IS NULL'))
				->addOrderBy(['sort_order' => SORT_ASC])
				->all();

			foreach ($models as $model) {
				// The posted sortable ids are canonical UUID strings, so index with the
				// model's UUID string rather than its raw BINARY(16) id.
				$sortableOrder = $flippedIds[$model->uuid];
				$sortableItem = $sortable[$sortableOrder];

				$model->sort_order = $sortableOrder + 1;
				if ($sortableItem['parentId']) {
					$model->parent_id = $sortableItem['parentId'];
				} else {
					$model->parent_id = null;
				}

				if (!$model->save()) {
					throw new \Exception();
				}

				Menu::invalidateMenuCache($model->id, $model->menu_id);
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
	 * Finds the MenuItem model(s) based on its primary key value.
	 * A 404 HTTP exception will be thrown if no record was found.
	 *
	 * @param int|array $id
	 * @param \yii\db\ActiveRecord|null $modelName
	 * @param bool $asActiveQuery
	 * @return \yii\db\ActiveQuery|\yii\db\ActiveRecord|MenuItem|MenuItemForm
	 * @throws NotFoundHttpException if no model was found
	 */
	protected function findModel($id, $modelName = null, $asActiveQuery = false)
	{
		$modelName = class_exists($modelName) ? $modelName : MenuItem::class;
		$query = $modelName::find()
			->alias('mi')
			->joinWith(['menu m'], false)
			->where([
				'mi.id' => $id,
				'm.id' => Yii::$app->request->get('menu_id'),
			]);

		if ($asActiveQuery) {
			if (!$query->count()) {
				throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
			}
			return $query;
		}

		$query->andWhere(['mi.deleted' => MenuItem::NO]);
		if (($model = $query->one()) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
