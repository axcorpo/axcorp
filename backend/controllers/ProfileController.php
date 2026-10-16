<?php

namespace backend\controllers;

use backend\models\UserProfileForm;
use common\models\User;
use tws\helpers\FileHelper;
use Yii;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;

class ProfileController extends MainController
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
						'actions' => ['index', 'delete-file'],
						'roles' => ['@'],
					],
				],
			],
		];
	}

	/**
	 * Displays profile page.
	 *
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionIndex()
	{
		$model = $this->findModel(Yii::$app->user->id, UserProfileForm::class);

		if ($model->load(Yii::$app->request->post()) && $model->save()) {
			Yii::$app->session->setFlash('success', Yii::t('common', 'Record has been updated.'));
			return $this->refresh();
		}

		return $this->render('index', [
			'model' => $model
		]);
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
		$filePath = Yii::getAlias("@uploads/user/{$id}/{$model->$attribute}");
		$result = true;

		if (file_exists($filePath) && ($result = FileHelper::unlink($filePath))) {
			$model->$attribute = null;
			$model->updateAttributes([$attribute]);
		}
		return $this->asJson([
			'success' => $result,
			'message' => $result ?
				Yii::t('common', 'Record has been deleted.') :
				Yii::t('common', 'Cannot delete the record.'),
		]);
	}

	/**
	 * Finds the User model(s) based on its primary key value.
	 * A 404 HTTP exception will be thrown if no record was found.
	 *
	 * @param int|array $id
	 * @param \yii\db\ActiveRecord|null $modelName
	 * @param bool $asActiveQuery
	 * @return \yii\db\ActiveQuery|\yii\db\ActiveRecord|User|UserProfileForm
	 * @throws NotFoundHttpException if no model was found
	 */
	protected function findModel($id, $modelName = null, $asActiveQuery = false)
	{
		$modelName = class_exists($modelName) ? $modelName : User::class;
		$query = $modelName::find()->andWhere([
			'id' => $id,
		]);

		if ($asActiveQuery) {
			if (!$query->count()) {
				throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
			}
			return $query;
		}

		$query->andWhere(['deleted' => User::NO]);
		if (($model = $query->one()) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
