<?php

namespace backend\modules\setting\controllers;

use backend\controllers\MainController;
use backend\modules\setting\models\AffiliateSettingForm;
use backend\modules\setting\models\CommercialSettingForm;
use backend\modules\setting\models\ContactSettingForm;
use backend\modules\setting\models\EmailSettingForm;
use backend\modules\setting\models\GeneralSettingForm;
use backend\modules\setting\models\PaymentSettingForm;
use backend\modules\setting\models\ScriptSettingForm;
use backend\modules\setting\models\SeoSettingForm;
use backend\modules\setting\models\SmsSettingForm;
use backend\modules\setting\models\SocialNetworkSettingForm;
use common\models\AffiliateSettings;
use common\models\Setting;
use tws\caching\CacheEvent;
use Yii;
use yii\filters\AccessControl;
use yii\helpers\FileHelper;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class SettingController extends MainController
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
						'actions' => ['view'],
						'roles' => ['@'],
					],
					[
						'allow' => true,
						'actions' => ['update'],
						'roles' => ['updateGeneralSetting', 'updateEmailSetting', 'updatePaymentSetting', 'updateCommercialSetting', 'updateSeoSetting', 'updateSocialNetworkSetting', 'updateContactSetting', 'updateScriptSetting', 'updateAffiliateSetting'],
					],
					[
						'allow' => true,
						'actions' => ['index', 'delete-file', 'delete-tts-sa-file'],
						'roles' => ['updateGeneralSetting'],
					],
					[
						'allow' => true,
						'actions' => ['email'],
						'roles' => ['updateEmailSetting'],
					],
					[
						'allow' => true,
						'actions' => ['payment'],
						'roles' => ['updatePaymentSetting'],
					],
					[
						'allow' => true,
						'actions' => ['commercial'],
						'roles' => ['updateCommercialSetting'],
					],
					[
						'allow' => true,
						'actions' => ['affiliate'],
						'roles' => ['updateAffiliateSetting'],
					],
					[
						'allow' => true,
						'actions' => ['seo'],
						'roles' => ['updateSeoSetting'],
					],
					[
						'allow' => true,
						'actions' => ['social-network'],
						'roles' => ['updateSocialNetworkSetting'],
					],
					[
						'allow' => true,
						'actions' => ['contact'],
						'roles' => ['updateContactSetting'],
					],
					[
						'allow' => true,
						'actions' => ['script'],
						'roles' => ['updateScriptSetting'],
					],
					[
						'allow' => true,
						'actions' => ['clear-cache'],
						'roles' => ['clearCacheSetting'],
						'verbs' => ['POST'],
					],
				],
			],
		];
	}

    /**
     * Displays a single Setting model.
     *
     * @param integer $id
     * @return mixed
     * @throws \yii\web\NotFoundHttpException if the model cannot be found
     * @throws ForbiddenHttpException if user does not have the right permission
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);
        $permissionName = implode('', ['update', ucfirst($model->name), 'Setting']);

        if (!Yii::$app->user->can($permissionName)) {
            throw new ForbiddenHttpException(Yii::t('yii', 'You are not allowed to perform this action.'));
        }

        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

	/**
	 * Displays a single Setting model.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws \yii\web\NotFoundHttpException if the model cannot be found
	 */
	public function actionUpdate($id)
	{
		$model = $this->findModel($id);
		$redirectActions = [
			'general' => 'index',
			'email' => 'email',
			'sms' => 'sms',
			'contact' => 'contact',
			'social-network' => 'social-network',
			'seo' => 'seo',
			'payment' => 'payment',
			'affiliate' => 'affiliate',
		];

		return $this->redirect([$redirectActions[$model->name]], 301);
	}

	/**
	 * Lists general Setting model.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		$model = GeneralSettingForm::findOne([
			'name' => 'general',
			'type' => Setting::TYPE_APP,
			'status' => Setting::STATUS_ACTIVE,
			'deleted' => Setting::NO,
		]);
		if (!$model) {
			$model = new GeneralSettingForm();
		}

		Yii::$app->eventLog
			->setData([
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
			])
			->beginRecord($model);

		if ($model->load(Yii::$app->request->post()) && $model->saveModel()) {
			Yii::$app->trigger('invalidate.cache', new CacheEvent(['key' => 'findAppSettings']));
			Yii::$app->eventLog->endRecord();
			Yii::$app->session->setFlash('success', Yii::t('common', 'Record has been updated.'));

			return $this->refresh();
		}

		return $this->render('index', [
			'model' => $model,
		]);
	}

	/**
	 * Lists email Setting model.
	 *
	 * @return mixed
	 */
	public function actionEmail()
	{
		$model = EmailSettingForm::findOne([
			'name' => 'email',
			'type' => Setting::TYPE_APP,
			'status' => Setting::STATUS_ACTIVE,
			'deleted' => Setting::NO,
		]);
		if (!$model) {
			$model = new EmailSettingForm();
		}

		Yii::$app->eventLog
			->setData([
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
			])
			->beginRecord($model);

		if ($model->load(Yii::$app->request->post()) && $model->saveModel()) {
			Yii::$app->trigger('invalidate.cache', new CacheEvent(['key' => 'findAppSettings']));
			Yii::$app->eventLog->endRecord();
			Yii::$app->session->setFlash('success', Yii::t('common', 'Record has been updated.'));

			return $this->refresh();
		}

		return $this->render('email', [
			'model' => $model,
		]);
	}

	/**
	 * Lists sms Setting model.
	 *
	 * @return mixed
	 */
	public function actionSms()
	{
		$model = SmsSettingForm::findOne([
			'name' => 'sms',
			'type' => Setting::TYPE_APP,
			'status' => Setting::STATUS_ACTIVE,
			'deleted' => Setting::NO,
		]);
		if (!$model) {
			$model = new SmsSettingForm();
		}

		Yii::$app->eventLog
			->setData([
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
			])
			->beginRecord($model);

		if ($model->load(Yii::$app->request->post()) && $model->saveModel()) {
			Yii::$app->trigger('invalidate.cache', new CacheEvent(['key' => 'findAppSettings']));
			Yii::$app->eventLog->endRecord();
			Yii::$app->session->setFlash('success', Yii::t('common', 'Record has been updated.'));

			return $this->refresh();
		}

		return $this->render('sms', [
			'model' => $model,
		]);
	}

	/**
	 * Lists payment Setting model.
	 *
	 * @return mixed
	 */
	public function actionPayment()
	{
		$model = PaymentSettingForm::findOne([
			'name' => 'payment',
			'type' => Setting::TYPE_APP,
			'status' => Setting::STATUS_ACTIVE,
			'deleted' => Setting::NO,
		]);
		if (!$model) {
			$model = new PaymentSettingForm();
		}

		Yii::$app->eventLog
			->setData([
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
			])
			->beginRecord($model);

		if ($model->load(Yii::$app->request->post()) && $model->saveModel()) {
			Yii::$app->trigger('invalidate.cache', new CacheEvent(['key' => 'findAppSettings']));
			Yii::$app->eventLog->endRecord();
			Yii::$app->session->setFlash('success', Yii::t('common', 'Record has been updated.'));

			return $this->refresh();
		}

		return $this->render('payment', [
			'model' => $model,
		]);
	}

	/**
	 * Lists commercial Setting model.
	 *
	 * @return mixed
	 */
	public function actionCommercial()
	{
		$model = CommercialSettingForm::findOne([
			'name' => 'commercial',
			'type' => Setting::TYPE_APP,
			'status' => Setting::STATUS_ACTIVE,
			'deleted' => Setting::NO,
		]);
		if (!$model) {
			$model = new CommercialSettingForm();
		}

		Yii::$app->eventLog
			->setData([
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
			])
			->beginRecord($model);

		if ($model->load(Yii::$app->request->post()) && $model->saveModel()) {
			Yii::$app->trigger('invalidate.cache', new CacheEvent(['key' => 'findAppSettings']));
			Yii::$app->eventLog->endRecord();
			Yii::$app->session->setFlash('success', Yii::t('common', 'Record has been updated.'));

			return $this->refresh();
		}

		return $this->render('commercial', [
			'model' => $model,
		]);
	}

	/**
	 * Lists affiliate Setting model (common\models\AffiliateSettings reads it).
	 *
	 * @return mixed
	 */
	public function actionAffiliate()
	{
		$model = AffiliateSettingForm::findOne([
			'name' => AffiliateSettings::GROUP,
			'type' => Setting::TYPE_APP,
			'status' => Setting::STATUS_ACTIVE,
			'deleted' => Setting::NO,
		]);
		if (!$model) {
			$model = new AffiliateSettingForm();
		}

		Yii::$app->eventLog
			->setData([
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
			])
			->beginRecord($model);

		if ($model->load(Yii::$app->request->post()) && $model->saveModel()) {
			Yii::$app->trigger('invalidate.cache', new CacheEvent(['key' => 'findAppSettings']));
			Yii::$app->eventLog->endRecord();
			Yii::$app->session->setFlash('success', Yii::t('common', 'Record has been updated.'));

			return $this->refresh();
		}

		return $this->render('affiliate', [
			'model' => $model,
		]);
	}

	/**
	 * Lists social network Setting model.
	 *
	 * @return mixed
	 */
	public function actionSocialNetwork()
	{
		$model = SocialNetworkSettingForm::findOne([
			'name' => 'socialNetwork',
			'type' => Setting::TYPE_APP,
			'status' => Setting::STATUS_ACTIVE,
			'deleted' => Setting::NO,
		]);
		if (!$model) {
			$model = new SocialNetworkSettingForm();
		}

		Yii::$app->eventLog
			->setData([
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
			])
			->beginRecord($model);

		if ($model->load(Yii::$app->request->post()) && $model->saveModel()) {
			Yii::$app->trigger('invalidate.cache', new CacheEvent(['key' => 'findAppSettings']));
			Yii::$app->eventLog->endRecord();
			Yii::$app->session->setFlash('success', Yii::t('common', 'Record has been updated.'));

			return $this->refresh();
		}

		return $this->render('social-network', [
			'model' => $model,
		]);
	}

	/**
	 * Lists SEO Setting model.
	 *
	 * @return mixed
	 */
	public function actionSeo()
	{
		$model = SeoSettingForm::findOne([
			'name' => 'seo',
			'type' => Setting::TYPE_APP,
			'status' => Setting::STATUS_ACTIVE,
			'deleted' => Setting::NO,
		]);
		if (!$model) {
			$model = new SeoSettingForm();
		}

		Yii::$app->eventLog
			->setData([
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
			])
			->beginRecord($model);

		if ($model->load(Yii::$app->request->post()) && $model->saveModel()) {
			Yii::$app->trigger('invalidate.cache', new CacheEvent(['key' => 'findAppSettings']));
			Yii::$app->eventLog->endRecord();
			Yii::$app->session->setFlash('success', Yii::t('common', 'Record has been updated.'));

			return $this->refresh();
		}

		return $this->render('seo', [
			'model' => $model,
		]);
	}

	/**
	 * Lists contact Setting model.
	 *
	 * @return mixed
	 */
	public function actionContact()
	{
		$model = ContactSettingForm::findOne([
			'name' => 'contact',
			'type' => Setting::TYPE_APP,
			'status' => Setting::STATUS_ACTIVE,
			'deleted' => Setting::NO,
		]);
		if (!$model) {
			$model = new ContactSettingForm();
		}

		Yii::$app->eventLog
			->setData([
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
			])
			->beginRecord($model);

		if ($model->load(Yii::$app->request->post()) && $model->saveModel()) {
			Yii::$app->trigger('invalidate.cache', new CacheEvent(['key' => 'findAppSettings']));
			Yii::$app->eventLog->endRecord();
			Yii::$app->session->setFlash('success', Yii::t('common', 'Record has been updated.'));

			return $this->refresh();
		}

		return $this->render('contact', [
			'model' => $model,
		]);
	}

	/**
	 * Lists Script Setting model.
	 *
	 * @return mixed
	 */
	public function actionScript()
	{
		$model = ScriptSettingForm::findOne([
			'name' => 'script',
			'type' => Setting::TYPE_APP,
			'status' => Setting::STATUS_ACTIVE,
			'deleted' => Setting::NO,
		]);
		if (!$model) {
			$model = new ScriptSettingForm();
		}

		Yii::$app->eventLog
			->setData([
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
			])
			->beginRecord($model);

		if ($model->load(Yii::$app->request->post()) && $model->saveModel()) {
			Yii::$app->trigger('invalidate.cache', new CacheEvent(['key' => 'findAppSettings']));
			Yii::$app->eventLog->endRecord();
			Yii::$app->session->setFlash('success', Yii::t('common', 'Record has been updated.'));

			return $this->refresh();
		}

		return $this->render('script', [
			'model' => $model,
		]);
	}

	/**
	 * Clears the application cache.
	 *
	 * @return mixed
	 */
	public function actionClearCache()
	{
		if (Yii::$app->cache->flush()) {
			Yii::$app->session->setFlash('success', Yii::t('common', 'Cache has been cleared.'));
		} else {
			Yii::$app->session->setFlash('error', Yii::t('common', 'Cannot clear the cache.'));
		}
		Yii::$app->trigger('invalidate.cache');

		return $this->redirect(Yii::$app->request->referrer);
	}

	/**
	 * Deletes a Text To Speech Service Account File.
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
		$attribute = Yii::$app->request->post('attribute');
		$filePath = Yii::getAlias("@/{$model->getUnserializedValue('setting')[$attribute]}");
		$result = true;

		if (file_exists($filePath) && ($result = FileHelper::unlink($filePath))) {
			$model->setSerializedValue('setting', [
				$attribute => null,
			]);
			$model->save(false);
		}
		return $this->asJson([
			'success' => (bool) $result,
			'message' => $result ?
				Yii::t('common', 'Record has been deleted.') :
				Yii::t('common', 'Cannot delete the record.'),
		]);
	}

	/**
	 * Deletes an existing file.
	 *
	 * @param $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionDeleteTtsSaFile($id)
	{
		if (!Yii::$app->request->isAjax) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}
		$model = $this->findModel($id);
		$attribute = 'textToSpeechServiceAccountFile';
		$file = Yii::$app->request->get('file');
		// Only the stored key file, by bare name: the request value went into the path as-is.
		$stored = ((array) $model->getUnserializedValue('setting', []))[$attribute] ?? null;
		if (!is_string($file) || $file === '' || $file !== basename($file) || $file !== $stored) {
			return $this->asJson([
				'success' => false,
				'message' => Yii::t('common', 'Cannot delete the record.'),
			]);
		}
		$filePath = Yii::getAlias('@runtime/tts/' . basename($file));
		$result = true;

		if (file_exists($filePath) && ($result = FileHelper::unlink($filePath))) {
			$model->setSerializedValue('setting', [
				$attribute => null,
			]);
			$model->save(false);
		}
		return $this->asJson([
			'success' => (bool) $result,
			'message' => $result ?
				Yii::t('common', 'Record has been deleted.') :
				Yii::t('common', 'Cannot delete the record.'),
		]);
	}

	/**
	 * Finds the Setting model based on its primary key value.
	 * If the model is not found, a 404 HTTP exception will be thrown.
	 *
	 * @param integer $id
	 * @return \yii\db\ActiveRecord|Setting the loaded model
	 * @throws \yii\web\NotFoundHttpException if the model cannot be found
	 */
	protected function findModel($id)
	{
		if (($model = Setting::findOne($id)) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
