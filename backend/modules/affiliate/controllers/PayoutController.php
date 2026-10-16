<?php

namespace backend\modules\affiliate\controllers;

use backend\controllers\MainController;
use backend\modules\affiliate\models\PayoutCreateForm;
use backend\modules\affiliate\models\PayoutPaidForm;
use backend\modules\affiliate\models\PayoutProofForm;
use backend\modules\affiliate\models\PayoutRejectForm;
use backend\modules\affiliate\models\PayoutSearch;
use common\helpers\PrivateFileHelper;
use common\models\Affiliate;
use common\models\AffiliatePayout;
use common\services\AffiliatePayoutService;
use Yii;
use yii\base\UserException;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;

/**
 * Payouts to affiliates: creation from the payable commissions, the affiliate's invoice
 * review, payment proofs, settlement and cancellation. Every state change goes through
 * AffiliatePayoutService (POST only); files are streamed from the private storage by
 * [[actionDownload()]] — all of it behind `manageAffiliatePayout`.
 */
class PayoutController extends MainController
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
						'actions' => ['index', 'view', 'dt-payouts', 'download', 'create'],
						'roles' => ['manageAffiliatePayout'],
					],
					[
						'allow' => true,
						'actions' => ['accept-invoice', 'reject-invoice', 'upload-proof', 'remove-proof', 'mark-paid', 'cancel'],
						'roles' => ['manageAffiliatePayout'],
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
			'dt-payouts' => PayoutSearch::class,
		];
	}

	/**
	 * @var AffiliatePayoutService
	 */
	private $_service;

	/**
	 * @return AffiliatePayoutService
	 */
	protected function getService()
	{
		if ($this->_service === null) {
			$this->_service = new AffiliatePayoutService();
		}
		return $this->_service;
	}

	/**
	 * Lists the payouts.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		return $this->render('index');
	}

	/**
	 * Shows what an affiliate can be paid (per currency) and creates the payout on POST.
	 *
	 * @param string|null $affiliate_id
	 * @param string|null $currency
	 * @return mixed
	 */
	public function actionCreate($affiliate_id = null, $currency = null)
	{
		$model = new PayoutCreateForm([
			'affiliate_id' => $affiliate_id,
			'currency' => $currency,
		]);

		if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
			if ($model->create()) {
				Yii::$app->session->setFlash('success', Yii::t('common', 'Record has been created.'));
				return $this->redirect(['view', 'id' => $model->payout->uuid]);
			}
		}

		$affiliate = $model->getAffiliate();
		$affiliates = [];
		foreach (Affiliate::find()->with('user')->where(['deleted' => Affiliate::NO])->orderBy(['code' => SORT_ASC])->all() as $item) {
			$affiliates[$item->uuid] = trim(($item->user ? $item->user->getFullName() : '') . " ({$item->code})");
		}

		return $this->render('create', [
			'model' => $model,
			'affiliate' => $affiliate,
			'affiliates' => $affiliates,
			'summary' => $affiliate ? $this->getService()->getPayableSummary($affiliate) : [],
		]);
	}

	/**
	 * Displays a payout with its commissions, the affiliate's invoice and the proofs.
	 *
	 * @param string $id
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionView($id)
	{
		return $this->render('view', [
			'model' => $this->findModel($id),
			'rejectForm' => new PayoutRejectForm(),
			'proofForm' => new PayoutProofForm(),
			'paidForm' => new PayoutPaidForm(),
		]);
	}

	/**
	 * Streams one of the payout's files (the affiliate's invoice or a proof). Only names
	 * recorded on this payout resolve (AffiliatePayout::getFilePath()), inside its private
	 * directory; access is the controller's `manageAffiliatePayout` rule.
	 *
	 * @param string $id
	 * @param string $file stored name
	 * @return \yii\web\Response
	 * @throws NotFoundHttpException
	 */
	public function actionDownload($id, $file)
	{
		$model = $this->findModel($id);
		$path = is_string($file) ? $model->getFilePath($file) : null;
		if ($path === null) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}
		$extension = pathinfo($path, PATHINFO_EXTENSION);
		$name = $file === $model->invoice_file
			? 'invoice-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $model->invoice_number)
			: 'payment-proof-' . substr($file, 0, 8);

		return Yii::$app->response->sendFile($path, "{$name}.{$extension}", [
			'mimeType' => PrivateFileHelper::getMimeType($path),
			'inline' => false,
		]);
	}

	/**
	 * Accepts the affiliate's invoice under review.
	 *
	 * @param string $id
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionAcceptInvoice($id)
	{
		$model = $this->findModel($id);
		return $this->runServiceAction($model, function () use ($model) {
			$this->getService()->acceptInvoice($model);
			return Yii::t('common', 'The invoice has been accepted.');
		});
	}

	/**
	 * Rejects the affiliate's invoice with a reason (e-mailed to the affiliate).
	 *
	 * @param string $id
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionRejectInvoice($id)
	{
		$model = $this->findModel($id);
		$form = new PayoutRejectForm();
		if (!$form->load(Yii::$app->request->post()) || !$form->validate()) {
			return $this->flashErrors($model, $form);
		}
		return $this->runServiceAction($model, function () use ($model, $form) {
			$this->getService()->rejectInvoice($model, $form->reason);
			return Yii::t('common', 'The invoice has been rejected.');
		});
	}

	/**
	 * Attaches one or more payment proofs.
	 *
	 * @param string $id
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionUploadProof($id)
	{
		$model = $this->findModel($id);
		$form = new PayoutProofForm();
		$form->loadFiles();
		if (!$form->validate()) {
			return $this->flashErrors($model, $form);
		}
		return $this->runServiceAction($model, function () use ($model, $form) {
			$added = $this->getService()->addProofFiles($model, $form->proofFiles);
			return Yii::t('common', '{count, plural, one{# file was} other{# files were}} uploaded.', ['count' => count($added)]);
		});
	}

	/**
	 * Removes one payment proof.
	 *
	 * @param string $id
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionRemoveProof($id)
	{
		$model = $this->findModel($id);
		$file = (string) Yii::$app->request->post('file', '');
		return $this->runServiceAction($model, function () use ($model, $file) {
			$this->getService()->removeProofFile($model, $file);
			return Yii::t('common', 'Record has been deleted.');
		});
	}

	/**
	 * Marks the payout paid; its commissions become PAID (the affiliate is e-mailed).
	 *
	 * @param string $id
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionMarkPaid($id)
	{
		$model = $this->findModel($id);
		$form = new PayoutPaidForm();
		if (!$form->load(Yii::$app->request->post()) || !$form->validate()) {
			return $this->flashErrors($model, $form);
		}
		return $this->runServiceAction($model, function () use ($model, $form) {
			$this->getService()->markPaid($model, $form->getPaidAt(), !$form->withoutInvoice);
			return Yii::t('common', 'The payout has been marked as paid.');
		});
	}

	/**
	 * Cancels an unpaid payout; its commissions return to the payable pool.
	 *
	 * @param string $id
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionCancel($id)
	{
		$model = $this->findModel($id);
		$reason = mb_substr(trim((string) Yii::$app->request->post('reason', '')), 0, 2000);
		return $this->runServiceAction($model, function () use ($model, $reason) {
			$this->getService()->cancel($model, $reason !== '' ? $reason : null);
			return Yii::t('common', 'The payout has been cancelled.');
		});
	}

	/**
	 * Runs a service call, flashes its outcome (UserException messages are safe to show)
	 * and goes back to the payout.
	 *
	 * @param AffiliatePayout $model
	 * @param callable $action returns the success message
	 * @return \yii\web\Response
	 */
	protected function runServiceAction(AffiliatePayout $model, callable $action)
	{
		Yii::$app->eventLog
			->setData([
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
			])
			->beginRecord($model);
		try {
			$message = $action();
			Yii::$app->eventLog->endRecord();
			Yii::$app->session->setFlash('success', $message);
		} catch (UserException $e) {
			Yii::$app->session->setFlash('error', $e->getMessage());
		}
		return $this->redirect(['view', 'id' => $model->uuid]);
	}

	/**
	 * @param AffiliatePayout $model
	 * @param \yii\base\Model $form
	 * @return \yii\web\Response
	 */
	protected function flashErrors(AffiliatePayout $model, $form)
	{
		$errors = $form->getFirstErrors();
		Yii::$app->session->setFlash('error', $errors ? reset($errors) : Yii::t('common', 'The operation was unsuccessful.'));
		return $this->redirect(['view', 'id' => $model->uuid]);
	}

	/**
	 * Finds a non-deleted payout by uuid.
	 *
	 * @param string $id
	 * @return AffiliatePayout
	 * @throws NotFoundHttpException
	 */
	protected function findModel($id)
	{
		if (is_string($id) && ($model = AffiliatePayout::find()->andWhere(['id' => $id, 'deleted' => AffiliatePayout::NO])->one()) !== null) {
			return $model;
		}
		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
