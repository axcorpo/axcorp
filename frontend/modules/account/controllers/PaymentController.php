<?php

namespace frontend\modules\account\controllers;

use common\models\PaymentMetadata;
use common\models\Subscription;
use frontend\controllers\MainController;
use frontend\modules\account\models\PaymentInvoiceForm;
use frontend\modules\account\models\PaymentPackageForm;
use frontend\modules\account\models\PaymentRequest;
use frontend\modules\account\models\PaymentResult;
use frontend\modules\account\models\PaymentSubscriptionForm;
use Yii;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;

class PaymentController extends MainController
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
						'actions' => ['index', 'package', 'subscription', 'invoice'],
						'roles' => ['@'],
					],
					[
						'allow' => true,
						'actions' => ['result'],
						'roles' => ['@'],
						'verbs' => ['GET', 'POST'],
					],
				],
			],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function beforeAction($action)
	{
		if ($action->id === 'result') {
			if (Yii::$app->request->isPost) {
				$this->enableCsrfValidation = false;
			}
		}

		return parent::beforeAction($action);
	}

	/**
	 * Displays index page.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		return $this->redirect(['package']);
	}

	/**
	 * Payment page for Package model.
	 *
	 * @param int|string|null $id
	 * @return mixed
	 */
	public function actionPackage($id = null)
	{
		$model = new PaymentPackageForm();
		$model->package_id = Yii::$app->security->unmaskToken((string) $id);

		if ($model->load(Yii::$app->request->post()) && $model->validate()) {
			if ($model->save()) {
				if ($model->payment_method == PaymentMetadata::PAYMENT_METHOD_CARD) {
					$paymentRequestModel = new PaymentRequest();
					$paymentRequestModel->type = PaymentMetadata::PAYMENT_TYPE_PACKAGE;
					$paymentRequestModel->subscription_id = $model->getSubscription()->id;
					$paymentRequestModel->payment_processor = $model->payment_processor;
					$paymentRequestModel->email = $model->email;
					$paymentRequestModel->token = $model->token;
					$paymentRequestModel->validate();
					return $this->render('request', [
						'model' => $paymentRequestModel,
					]);
				} elseif ($model->payment_method == PaymentMetadata::PAYMENT_METHOD_BANK) {
					Yii::$app->session->setFlash('success', Yii::t('frontend', 'Your subscription was successfully created and it will be activated as soon as the payment is completed.'));
					return $this->redirect(['subscription/index']);
				}
			}
			Yii::$app->session->setFlash('error', Yii::t('frontend', 'Cannot process your request. Please try again or contact us if the problem persists.'));
		}

		return $this->render('package', [
			'model' => $model,
		]);
	}

	/**
	 * Payment page for Subscription model.
	 *
	 * @param int|string $id
	 * @return mixed
	 * @throws NotFoundHttpException if the Subscription model was not found.
	 */
	public function actionSubscription($id)
	{
		$model = new PaymentSubscriptionForm();
		$model->subscription_id = Yii::$app->security->unmaskToken((string) $id);
		$model->company_id = $model->getSubscription()->company_id;

		if (!$model->getSubscription()) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}

		if ($model->load(Yii::$app->request->post()) && $model->validate()) {
			if ($model->save()) {
				if ($model->payment_method == PaymentMetadata::PAYMENT_METHOD_CARD) {
					$paymentRequestModel = new PaymentRequest();
					$paymentRequestModel->type = PaymentMetadata::PAYMENT_TYPE_SUBSCRIPTION;
					$paymentRequestModel->subscription_id = $model->getSubscription()->id;
					$paymentRequestModel->payment_processor = $model->payment_processor;
					$paymentRequestModel->validate();
					return $this->render('request', [
						'model' => $paymentRequestModel,
					]);
				} elseif ($model->payment_method == PaymentMetadata::PAYMENT_METHOD_BANK) {
					Yii::$app->session->setFlash('success', Yii::t('frontend', 'Your subscription was successfully created and it will be activated as soon as the payment is completed.'));
					return $this->redirect(['subscription/index']);
				}
			}
			Yii::$app->session->setFlash('error', Yii::t('frontend', 'Cannot process your request. Please try again or contact us if the problem persists.'));
		}

		return $this->render('subscription', [
			'model' => $model,
		]);
	}

	/**
	 * Payment page for Invoice model.
	 *
	 * @param int|string $id
	 * @return mixed
	 * @throws NotFoundHttpException if the Invoice model was not found.
	 */
	public function actionInvoice($id)
	{
		$model = new PaymentInvoiceForm();
		$model->invoice_id = Yii::$app->security->unmaskToken((string) $id);
		$model->company_id = $model->getSubscription()->company_id;

		if (!$model->getInvoice() || !$model->getSubscription()) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}

		if ($model->load(Yii::$app->request->post()) && $model->validate()) {
			if ($model->save()) {
				if ($model->payment_method == PaymentMetadata::PAYMENT_METHOD_CARD) {
					$paymentRequestModel = new PaymentRequest();
					$paymentRequestModel->type = PaymentMetadata::PAYMENT_TYPE_INVOICE;
					$paymentRequestModel->subscription_id = $model->getSubscription()->id;
					$paymentRequestModel->payment_processor = $model->payment_processor;
					$paymentRequestModel->validate();
					return $this->render('request', [
						'model' => $paymentRequestModel,
					]);
				} elseif ($model->payment_method == PaymentMetadata::PAYMENT_METHOD_BANK) {
					Yii::$app->session->setFlash('success', Yii::t('frontend', 'Your subscription was successfully created and it will be activated as soon as the payment is completed.'));
					return $this->redirect(['subscription/index']);
				}
			}
			Yii::$app->session->setFlash('error', Yii::t('frontend', 'Cannot process your request. Please try again or contact us if the problem persists.'));
		}

		return $this->render('invoice', [
			'model' => $model,
		]);
	}

	/**
	 * Displays payment result page.
	 *
	 * @param string|int|null $pp the payment processor.
	 * @return mixed
	 */
	public function actionResult($pp = null)
	{
		if (!($params = Yii::$app->request->post())) {
			$params = Yii::$app->request->get();
		}

		if (!empty($params['cancel'])) {
			return $this->redirect(['index']);
		}

		$attributes = [];
		$model = new PaymentResult();

		if (empty($params['session_id'])) {
			$model->addError('', Yii::t('frontend', 'Payment response is not valid.'));
		}

		if (!empty($params['session_id'])) {
			$payment = Yii::$app->payment->via(PaymentMetadata::PAYMENT_PROCESSOR_STRIPE);
			$stripe = new \Stripe\StripeClient(
				$payment->privateKey
			);
			$session = $stripe->checkout->sessions->retrieve(
				$params['session_id'],
				[]
			);
			if (!empty($session) && $session['payment_status'] == 'paid') {
				$attributes = [
					'subscription_id' => $session['metadata']['subscription_id'],
					'payer_id' => $session['customer'],
				];

				if ($charge = PaymentResult::findSessionChargeId($stripe, $session)) {
					$attributes['payment_id'] = $charge;
				}

				// The Stripe subscription id first, so PaymentResult can read the
				// subscription from Stripe while saving (same order as the IPN).
				if ($subscription = Subscription::findOne(['id' => $session['metadata']['subscription_id']])) {
					$subscription->external_id = $session['subscription'];
					$subscription->save(false);
				}

				$model->load($attributes, '');
				$model->save();
			} else {
				$model->addError('', Yii::t('frontend', 'Payment failed.'));
			}
		}

		return $this->render('result', [
			'model' => $model,
		]);
	}
}
