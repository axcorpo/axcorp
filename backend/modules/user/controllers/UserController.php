<?php

namespace backend\modules\user\controllers;

use backend\controllers\MainController;
use backend\modules\user\models\UserForm;
use backend\modules\user\models\UserSearch;
use common\models\AuthAssignment;
use common\models\Subscriber;
use common\models\Subscription;
use common\models\User;
use common\models\Workspace;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\web\NotFoundHttpException;

class UserController extends MainController
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
						'actions' => ['index', 'view', 'dt-users', 'find-existing-users'],
						'roles' => ['viewUser'],
					],
					[
						'allow' => true,
						'actions' => ['create'],
						'roles' => ['createUser'],
					],
					[
						'allow' => true,
						'actions' => ['update', 'delete-file'],
						'roles' => ['updateUser'],
					],
					[
						'allow' => true,
						'actions' => ['delete'],
						'roles' => ['deleteUser'],
						'verbs' => ['POST'],
					],
					[
						'allow' => true,
						'actions' => ['restore'],
						'roles' => ['restoreUser'],
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
			'dt-users' => UserSearch::class,
		];
	}

	/**
	 * Lists all User models.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		if (Yii::$app->request->get('deleted') == User::YES) {
			if (!Yii::$app->settings->get('enableSoftDelete') || !Yii::$app->user->can('restoreUser')) {
				return $this->redirect(['index']);
			}
		}
		return $this->render('index');
	}

	/**
	 * Displays a single User model.
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
	 * Creates a new User model.
	 * If creation is successful, the browser will be redirected to the 'view' page.
	 *
	 * @return mixed
	 */
	public function actionCreate()
	{
		$model = new UserForm();
		$result = true;

		Yii::$app->eventLog->beginRecord($model);
		if ($model->load(Yii::$app->request->post()) && ($result = $model->save())) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllUsers']));
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
				'success' => $result,
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
	 * Updates an existing User model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws \yii\web\NotFoundHttpException if the model cannot be found
	 */
	public function actionUpdate($id)
	{
		$model = $this->findModel($id, UserForm::class);
		$result = true;

		Yii::$app->eventLog->beginRecord($model);
		if ($model->load(Yii::$app->request->post()) && ($result = $model->save())) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllUsers']));
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
     * Deletes existing Product models.
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
            /** @var User $model */
            foreach ($models->each() as $model) {
                Yii::$app->eventLog
                    ->setData([
                        'operation' => $isPermanent ? (Yii::$app->eventLog)::ACTION_DELETE : (Yii::$app->eventLog)::ACTION_SOFT_DELETE,
                    ])
                    ->beginRecord($model);

                    if ($model->delete($isPermanent)) {
                        $deletedModels[] = $model->uuid;
                        Yii::$app->eventLog->endRecord();

                        $workspaces = Workspace::find()
                            ->alias('ws')
                            ->select([
                                'ws.*',
                            ])
                            ->joinWith([
                                'subscription s',
                                'subscription.subscriber sub',
                            ])
                            ->andWhere([
                                'sub.user_id' => $model->id,
                            ])
                            ->all();

                        foreach ($workspaces as $workspace) {
                            try {
                                if ($workspace->uninstall()) {
                                    Workspace::deleteAll(['id' => $workspace->id]);
                                    continue;
                                } else {
                                    throw new \Exception('Cannot uninstall workspace');
                                }
                            } catch (\Exception $e) {
                                return false;
                            }
                        }
                    } else {
                        throw new \Exception();
                    }
                }

            if ($isPermanent) {
                foreach ($deletedModels as $deletedModel) {
                    FileHelper::removeDirectory(Yii::getAlias("@uploads/{$this->id}/{$deletedModel}"));
                }
            }
            Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllProducts']));
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
	 * Restores User models that are marked as deleted.
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
			/** @var User $model */
			foreach ($models->each() as $model) {
				Yii::$app->eventLog->beginRecord($model);
				if ($model->restore()) {
					Yii::$app->eventLog->endRecord();
				} else {
					throw new \Exception();
				}
			}
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllUsers']));
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
	 * Deletes an existing file.
	 *
	 * @param $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionDeleteFile($id)
	{
		if (!Yii::$app->request->isAjax) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}
		$model = $this->findModel($id);
		$attribute = Yii::$app->request->post('attribute', 'image');
		$filePath = Yii::getAlias("@uploads/{$this->id}/{$id}/{$model->$attribute}");
		$result = true;

		if (file_exists($filePath) && ($result = FileHelper::unlink($filePath))) {
			$model->$attribute = null;
			$model->save(false);
		}
		return $this->asJson([
			'success' => $result,
			'message' => $result ?
				Yii::t('common', 'Record has been deleted.') :
				Yii::t('common', 'Cannot delete the record.'),
		]);
	}

	/**
	 * Finds all existing users with no role or permission associated.
	 *
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionFindExistingUsers()
	{
		if (!Yii::$app->request->isAjax) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}

		if (!($params = Yii::$app->request->post())) {
			$params = Yii::$app->request->get();
		}

		return $this->asJson([
			'success' => true,
			'data' => reset(User::findAllUsersWithoutRoleByUsername($params['value'])),
		]);
	}

	/**
	 * Finds the User model(s) based on its primary key value.
	 * A 404 HTTP exception will be thrown if no record was found.
	 *
	 * @param int|array $id
	 * @param \yii\db\ActiveRecord|null $modelName
	 * @param bool $asActiveQuery
	 * @return \yii\db\ActiveQuery|\yii\db\ActiveRecord|User|UserForm
	 * @throws NotFoundHttpException if no model was found
	 */
	protected function findModel($id, $modelName = null, $asActiveQuery = false)
	{
		$modelName = class_exists($modelName) ? $modelName : User::class;
		$query = $modelName::find()
			->alias('u')
			->joinWith([
				'authAssignment aa' => function (ActiveQuery $query) {
					$query->andWhere(new \yii\db\Expression('IF([[aa.item_name]] = "superAdmin", 1, 0) = 0'));
				},
			], false)
			->andWhere(['u.id' => $id])
			->andWhere(['!=', 'u.id', Yii::$app->user->id]);

		if ($asActiveQuery) {
			if (!$query->count()) {
				throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
			}
			return $query;
		}

		$query->andWhere(['u.deleted' => User::NO]);
		if (($model = $query->one()) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
