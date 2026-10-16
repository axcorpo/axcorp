<?php

namespace frontend\modules\account\controllers;

use common\models\User;
use frontend\controllers\MainController;
use frontend\modules\account\models\PasswordForm;
use frontend\modules\account\models\ProfileForm;
use Yii;
use yii\filters\AccessControl;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;
use yii\validators\FileValidator;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

class ProfileController extends MainController
{
	/**
	 * @var string[] The User attributes [[actionUploadFile()]] is allowed to write.
	 */
	const UPLOADABLE_ATTRIBUTES = ['image'];

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
						'actions' => ['index', 'upload-file'],
						'roles' => ['@'],
					],
				],
			],
		];
	}

	/**
	 * Displays index view.
	 *
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionIndex()
	{
		$model = $this->findModel(Yii::$app->user->id, ProfileForm::class);
		$passwordForm = new PasswordForm($this->findModel(Yii::$app->user->id));
		$post = Yii::$app->request->post();

		// Two forms on one page, told apart by their form name in the POST body.
		if ($passwordForm->load($post)) {
			if ($passwordForm->changePassword()) {
				// The auth key was rotated, so the identity cookie has to be reissued.
				$user = User::findOne(['id' => $passwordForm->getUser()->id]);
				if (Yii::$app->getUser()->login($user, (int) Yii::$app->settings->get('userLoginDuration'))) {
					Yii::$app->session->setFlash('success', Yii::t('common', 'Your password was successfully changed.'));
					return $this->refresh();
				}
			}
		} elseif ($model->load($post) && $model->saveModel()) {
			$user = User::findOne(['id' => $model->id]);
			if (Yii::$app->getUser()->login($user, (int) Yii::$app->settings->get('userLoginDuration'))) {
				Yii::$app->session->setFlash('success', Yii::t('common', 'Your profile was successfully updated.'));
				return $this->refresh();
			}
		}

		return $this->render('index', [
			'model' => $model,
			'passwordForm' => $passwordForm,
		]);
	}

	/**
	 * Uploads a new file.
	 *
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionUploadFile()
	{
		$model = $this->findModel(Yii::$app->user->id);
		$bodyParams = Yii::$app->request->post();
		$attribute = $bodyParams['attribute'] ?? null;
		$fileName = $bodyParams['fileName'] ?? null;
		$response = [];

		try {
			// Only the avatar may be written from here. `hasAttribute()` alone would let the
			// caller name any User column (auth_key, email, status, ...) and have the uploaded
			// file name written into it.
			if (!in_array($attribute, self::UPLOADABLE_ATTRIBUTES, true)) {
				throw new \Exception();
			}
			if ($fileName === null || !($file = UploadedFile::getInstanceByName($fileName))) {
				throw new \Exception();
			}
			// This action never runs ProfileForm's rules, so the same allow-list is enforced
			// here. `checkExtensionByMimeType` (on by default) makes the validator reject a
			// file whose real content does not match its extension, which is what stops a
			// PHP script from being stored as `avatar.php` under the web root.
			$validator = new FileValidator([
				'extensions' => Yii::$app->params['image.extensions'],
				'mimeTypes' => Yii::$app->params['image.mimeTypes'],
				'maxSize' => Yii::$app->settings->get('maxFileSize'),
			]);
			if (!$validator->validate($file, $validationError)) {
				throw new \Exception($validationError);
			}

			$dirPath = Yii::getAlias("@uploads/user/{$model->uuid}");
			$oldFilePath = "{$dirPath}/{$model->oldAttributes['image']}";
			$fileName = StringHelper::truncate(implode('_', array_filter([
				Inflector::slug($model->fullName),
				Yii::$app->security->generateRandomString(8),
			])), 255 - (mb_strlen($file->extension) + 1), '') . ".{$file->extension}";
			$filePath = "{$dirPath}/{$fileName}";

			FileHelper::createDirectory($dirPath);
			if (!$file->saveAs($filePath)) {
				throw new \Exception();
			}
			if (!$model->updateAttributes([$attribute => $fileName])) {
				throw new \Exception();
			}
			if (is_file($oldFilePath) && $oldFilePath != $filePath) {
				FileHelper::unlink($oldFilePath);
			}
			$response['success'] = true;
		} catch (\Exception $e) {
			$response['success'] = false;
			$response['error'] = Yii::t('common', 'Cannot upload the file.');
		}

		return $this->asJson($response);
	}

	/**
	 * Finds the User model(s) based on its primary key value.
	 * A 404 HTTP exception will be thrown if no record was found.
	 *
	 * @param int|array $id
	 * @param \yii\db\ActiveRecord|null $modelName
	 * @param bool $asActiveQuery
	 * @return \yii\db\ActiveQuery|\yii\db\ActiveRecord|User|ProfileForm
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
