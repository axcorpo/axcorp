<?php

namespace frontend\modules\account\controllers;

use common\models\Invoice;
use common\models\OblioInvoice;
use common\models\Template;
use frontend\controllers\MainController;
use frontend\modules\account\models\InvoiceSearch;
use kartik\mpdf\Pdf;
use Yii;
use yii\filters\AccessControl;
use tws\helpers\Url;
use yii\web\NotFoundHttpException;

class InvoiceController extends MainController
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
						'actions' => ['index', 'view', 'dt-invoices'],
						'roles' => ['@'],
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
			'dt-invoices' => InvoiceSearch::class,
		];
	}

	/**
	 * Displays index view.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		return $this->render('index');
	}

	/**
	 * Views invoice of an existing model.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws \yii\web\NotFoundHttpException if the model cannot be found
	 * @throws \yii\base\InvalidConfigException
	 */
	public function actionView($id)
	{
		$model = $this->findModel($id);

		// If invoice has external_id (sent to Oblio), return Oblio PDF
		if (!empty($model->external_id)) {
			$details = $model->getUnserializedValue('details') ?: [];

			try {
				$merchant = $details['merchant'] ?? [];
				$sellerCif = $merchant['tin'] ?? null;

				if (empty($sellerCif)) {
					throw new \Exception('Missing seller CIF to retrieve Oblio invoice.');
				}

				// Get series and number from invoice details (stored when invoice was created)
				$seriesName = $details['oblio_series'] ?? $model->document_series;
				$number = $details['oblio_number'] ?? $model->document_number;

				if (empty($seriesName) || empty($number)) {
					throw new \Exception('Missing series or number to retrieve Oblio invoice.');
				}

				// The link stored at push time short-circuits the lookup; otherwise ask
				// the right Oblio register — proformas live in a separate one.
				$oblioUrl = $details['oblio_url'] ?? null;
				if (empty($oblioUrl)) {
					$response = $model->type == Invoice::TYPE_PROFORMA
						? OblioInvoice::viewProforma($sellerCif, $seriesName, (int) $number)
						: OblioInvoice::viewInvoice($sellerCif, $seriesName, (int) $number);
					$oblioUrl = $response['data']['link'] ?? null;
				}

				if (!empty($oblioUrl)) {
					// Fetch PDF content from Oblio URL
					$pdfContent = OblioInvoice::downloadInvoicePdf($oblioUrl);

					if ($pdfContent) {
						// Return PDF content
						Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
						Yii::$app->response->headers->set('Content-Type', 'application/pdf');
						Yii::$app->response->headers->set('Content-Disposition', 'inline; filename="' . $model->getDocumentSeriesNumber() . '.pdf"');
						return $pdfContent;
					}
				}
			} catch (\Exception $e) {
				// If we can't get the PDF, fall through to local PDF generation
			}
		}

		$template = $model->getTemplates()
			->with('templateTranslations')
			->andWhere([
				'type' => Template::TYPE_INVOICE,
				'status' => Template::STATUS_ACTIVE,
				'deleted' => Template::NO,
			])
			->limit(1)
			->one();

		if (!$template || !$template->translation->content) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}
		$fileName = "{$model->getDocumentSeriesNumber()}.pdf";
		$filePath = Yii::getAlias("@runtime/" . Yii::$app->user->id . "-{$fileName}");

		$pdf = new Pdf([
			'mode' => Pdf::MODE_UTF8,
			'format' => Pdf::FORMAT_A4,
			'orientation' => Pdf::ORIENT_PORTRAIT,
			'destination' => Pdf::DEST_BROWSER,
			'content' => strtr($template->translation->content, $model->getShortCodeValues(true)),
			'filename' => $filePath,
			'marginTop' => 5,
			'marginBottom' => 5,
		]);
		$pdfApi = $pdf->getApi();

		$pdfApi->setAutoTopMargin = true;
		$pdfApi->setAutoBottomMargin = true;
		$pdfApi->defaultheaderline = 0;
		$pdfApi->defaultfooterline = 0;

		if ($model->status == Invoice::STATUS_UNPAID) {
			$pdfApi->SetWatermarkText(mb_strtoupper(Invoice::getStatusLabels()[$model->status]['label']), 0.1);
			$pdfApi->showWatermarkText = true;
		}

		return $pdf->render();
	}

	/**
	 * Finds the Invoice model(s) based on its primary key value.
	 * A 404 HTTP exception will be thrown if no record was found.
	 *
	 * @param int|array $id
	 * @param \yii\db\ActiveRecord|null $modelName
	 * @param bool $asActiveQuery
	 * @return \yii\db\ActiveQuery|\yii\db\ActiveRecord|Invoice
	 * @throws NotFoundHttpException if no model was found
	 */
	protected function findModel($id, $modelName = null, $asActiveQuery = false)
	{
		$modelName = class_exists($modelName) ? $modelName : Invoice::class;
		$query = $modelName::find()->andWhere([
			'id' => $id,
            'subscriber_id' => Yii::$app->user->identity->subscriber->id,
		]);

		if ($asActiveQuery) {
			if (!$query->count()) {
				throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
			}
			return $query;
		}

		$query->andWhere(['deleted' => Invoice::NO]);
		if (($model = $query->one()) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
