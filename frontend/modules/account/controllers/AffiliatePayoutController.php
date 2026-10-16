<?php

namespace frontend\modules\account\controllers;

use common\helpers\PrivateFileHelper;
use common\models\AffiliatePayout;
use frontend\modules\account\components\AffiliateAreaController;
use frontend\modules\account\models\AffiliateInvoiceForm;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Inflector;
use yii\web\NotFoundHttpException;

/**
 * The affiliate's payouts: the list, one payout with its invoice and the payment proofs,
 * the invoice upload and the file downloads.
 *
 * Every action finds the payout through [[findModel()]], scoped to the signed-in
 * affiliate, and files are streamed from the private storage only after that check,
 * never linked.
 */
class AffiliatePayoutController extends AffiliateAreaController
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
						'actions' => ['index', 'view', 'upload-invoice', 'download'],
						'roles' => ['@'],
					],
				],
			],
			'verbs' => [
				'class' => VerbFilter::class,
				'actions' => [
					'upload-invoice' => ['POST'],
				],
			],
		];
	}

	/**
	 * Lists the payouts.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		$dataProvider = new ActiveDataProvider([
			'query' => AffiliatePayout::find()
				->andWhere([
					'affiliate_id' => $this->getAffiliate()->getAttribute('id'),
					'deleted' => AffiliatePayout::NO,
				])
				->orderBy(['created_at' => SORT_DESC]),
			'pagination' => [
				'pageSize' => 20,
			],
			'sort' => false,
		]);

		return $this->render('index', [
			'dataProvider' => $dataProvider,
		]);
	}

	/**
	 * Displays one payout, with the invoice upload form while it awaits the invoice.
	 *
	 * @param string $id
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionView($id)
	{
		$model = $this->findModel($id);

		return $this->render('view', [
			'model' => $model,
			'invoiceForm' => new AffiliateInvoiceForm($model),
			'affiliate' => $this->getAffiliate(),
		]);
	}

	/**
	 * Attaches the affiliate's invoice to an OPEN payout.
	 *
	 * @param string $id
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionUploadInvoice($id)
	{
		$model = $this->findModel($id);
		$invoiceForm = new AffiliateInvoiceForm($model);

		if ($invoiceForm->load(Yii::$app->request->post()) && $invoiceForm->save()) {
			Yii::$app->session->setFlash('success', Yii::t('frontend', 'Your invoice was uploaded and is awaiting review.'));
			return $this->redirect(['view', 'id' => $model->uuid]);
		}

		return $this->render('view', [
			'model' => $model,
			'invoiceForm' => $invoiceForm,
			'affiliate' => $this->getAffiliate(),
		]);
	}

	/**
	 * Streams one of the payout's files: the affiliate's invoice or a payment proof.
	 *
	 * @param string $id
	 * @param string $file the stored name, as recorded on the payout
	 * @return \yii\web\Response
	 * @throws NotFoundHttpException
	 */
	public function actionDownload($id, $file)
	{
		$model = $this->findModel($id);
		// Only names recorded on this payout resolve (AffiliatePayout::hasFile()).
		$path = $model->getFilePath($file);
		if ($path === null) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}

		$extension = pathinfo($path, PATHINFO_EXTENSION);
		if ($file === $model->invoice_file) {
			$downloadName = Inflector::slug(Yii::t('frontend', 'Invoice') . ' ' . $model->invoice_number) ?: 'invoice';
		} else {
			$index = (int) array_search($file, $model->getProofFileList(), true);
			$downloadName = Inflector::slug(Yii::t('frontend', 'Payment proof') . ' ' . ($index + 1));
		}

		return Yii::$app->response->sendFile($path, "{$downloadName}.{$extension}", [
			'mimeType' => PrivateFileHelper::getMimeType($path),
			'inline' => false,
		]);
	}

	/**
	 * Finds a payout of the signed-in affiliate. Any other payout is a 404, the same as a
	 * missing one, so the ids of other affiliates cannot be probed.
	 *
	 * @param string $id canonical uuid
	 * @return AffiliatePayout
	 * @throws NotFoundHttpException
	 */
	protected function findModel($id)
	{
		if (!is_string($id) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}
		$model = AffiliatePayout::find()
			->andWhere([
				'id' => $id,
				'affiliate_id' => $this->getAffiliate()->getAttribute('id'),
				'deleted' => AffiliatePayout::NO,
			])
			->one();
		if ($model === null) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}
		return $model;
	}
}
