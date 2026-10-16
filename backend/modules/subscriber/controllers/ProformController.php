<?php

namespace backend\modules\subscriber\controllers;

use common\models\Invoice;
use common\models\OblioInvoice;
use common\models\Template;
use backend\controllers\MainController;
use backend\modules\subscriber\models\ProformSearch;
use kartik\mpdf\Pdf;
use Yii;
use yii\filters\AccessControl;
use tws\helpers\Url;
use yii\helpers\FileHelper;
use yii\web\NotFoundHttpException;

class ProformController extends MainController
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
						'roles' => ['viewInvoice'],
					],
					[
						'allow' => true,
						'actions' => ['send-to-oblio'],
						'roles' => ['viewInvoice'],
						// creates the proforma in the external Oblio system; must not be
						// reachable via GET (CSRF)
						'verbs' => ['POST'],
					],
                    [
                        'allow' => true,
                        'actions' => ['delete'],
                        'roles' => ['deleteSubscriber'],
                        'verbs' => ['POST'],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['restore'],
                        'roles' => ['restoreSubscriber'],
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
			'dt-invoices' => ProformSearch::class,
		];
	}

	/**
	 * Displays index view.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
        if (Yii::$app->request->get('deleted') == Invoice::YES) {
            if (!Yii::$app->settings->get('enableSoftDelete') || !Yii::$app->user->can('restoreSubscriber')) {
                return $this->redirect(['index']);
            }
        }
        return $this->render('index');
	}

	/**
	 * Pushes a proforma to Oblio (manual, idempotent — a re-send returns the stored document).
	 *
	 * @param int $id
	 * @return mixed
	 */
	public function actionSendToOblio($id)
	{
		$model = $this->findModel($id);
		$response = [
			'success' => true,
			'message' => [
				'title' => '',
				'body' => [],
			],
		];

		if (!empty($model->external_id)) {
			$response['message']['title'] = Yii::t('common', 'Proform already sent to Oblio.');
			if (Yii::$app->request->isAjax) {
				return $this->asJson($response);
			}
			Yii::$app->session->setFlash('info', $response['message']['title']);
			return $this->redirect(['index']);
		}

		try {
			OblioInvoice::createProformaFromLocalInvoice($model, [
				'seriesName' => $model->document_series,
				'disableAutoSeries' => 1,
				'number' => $model->document_number,
			]);

			$response['message']['title'] = Yii::t('common', 'Proform {invoice} sent to Oblio successfully.', [
				'invoice' => $model->getDocumentSeriesNumber(),
			]);
		} catch (\Exception $e) {
			$response['success'] = false;
			$response['message']['title'] = Yii::t('common', 'Failed to send proform to Oblio.');
			$response['message']['body'][] = $e->getMessage();

			Yii::error([
				'message' => 'Oblio proforma send failed.',
				'invoice_id' => $model->uuid,
				'error' => $e->getMessage(),
			], 'oblio');
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson($response);
		}

		Yii::$app->session->setFlash($response['success'] ? 'success' : 'error', [$response['message']]);
		return $this->redirect(['index']);
	}

	/**
	 * Views the invoice of an existing model.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws \yii\web\NotFoundHttpException if the model cannot be found
	 * @throws \yii\base\InvalidConfigException
	 */
	public function actionView($id)
	{
		$model = $this->findModel($id);
		// If the proforma has external_id (sent to Oblio), return the Oblio PDF
		if (!empty($model->external_id)) {
			$details = $model->getUnserializedValue('details') ?: [];

			try {
				$merchant = $details['merchant'] ?? [];
				$sellerCif = $merchant['tin'] ?? null;

				if (empty($sellerCif)) {
					throw new \Exception('Missing seller CIF to retrieve Oblio proforma.');
				}

				// Get series and number from invoice details (stored when the proforma was created)
				$seriesName = $details['oblio_series'] ?? $model->document_series;
				$number = $details['oblio_number'] ?? $model->document_number;

				if (empty($seriesName) || empty($number)) {
					throw new \Exception('Missing series or number to retrieve Oblio proforma.');
				}

				// The link stored at push time short-circuits the lookup; otherwise ask
				// the proforma register of Oblio.
				$oblioUrl = $details['oblio_url'] ?? null;
				if (empty($oblioUrl)) {
					$response = OblioInvoice::viewProforma($sellerCif, $seriesName, (int) $number);
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

		/** @var Template $template */
		$template = Template::find()
            ->Where([
			'type' => Template::TYPE_INVOICE,
			'variant' => Template::INVOICE_VARIANT_PROFORM,
			'status' => Template::STATUS_ACTIVE,
			'deleted' => Template::NO,
		])->limit(1)->one();


		if (!$template || !($templateTranslation = $template->getTranslation())) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}

		$fileName = "{$model->getDocumentSeriesNumber()}.pdf";
		$filePath = Yii::getAlias("@runtime/" . Yii::$app->user->id . "-{$fileName}");
		$pdf = new Pdf([
			'mode' => Pdf::MODE_UTF8,
			'format' => Pdf::FORMAT_A4,
			'orientation' => Pdf::ORIENT_PORTRAIT,
			'destination' => Pdf::DEST_BROWSER,
			'content' => strtr($templateTranslation->content, $model->getShortCodeValues(true)),
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
     * Deletes existing Invoice models.
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
            /** @var Invoice $model */
            foreach ($models->each() as $model) {
                Yii::$app->eventLog
                    ->setData([
                        'operation' => $isPermanent ? (Yii::$app->eventLog)::ACTION_DELETE : (Yii::$app->eventLog)::ACTION_SOFT_DELETE,
                    ])
                    ->beginRecord($model);
                if ($model->delete($isPermanent)) {
                    $deletedModels[] = $model->uuid;
                    Yii::$app->eventLog->endRecord();
                } else {
                    throw new \Exception();
                }
            }
            if ($isPermanent) {
                foreach ($deletedModels as $deletedModel) {
                    FileHelper::removeDirectory(Yii::getAlias("@uploads/{$this->id}/{$deletedModel}"));
                }
            }
            Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllInvoice']));
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
     * Restores Subscription models that are marked as deleted.
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
            /** @var Invoice $model */
            foreach ($models->each() as $model) {
                Yii::$app->eventLog->beginRecord($model);
                if ($model->restore()) {
                    Yii::$app->eventLog->endRecord();
                } else {
                    throw new \Exception();
                }
            }
            Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllInvoice']));
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
