<?php

namespace backend\modules\subscriber\controllers;

use backend\modules\subscriber\models\EInvoiceForm;
use backend\modules\subscriber\models\InvoiceForm;
use common\models\Integration;
use common\models\Invoice;
use common\models\OblioInvoice;
use common\models\Template;
use backend\controllers\MainController;
use backend\modules\subscriber\models\InvoiceSearch;
use kartik\mpdf\Pdf;
use Yii;
use yii\filters\AccessControl;
use tws\helpers\Url;
use yii\helpers\FileHelper;
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
						'actions' => ['index', 'view', 'dt-invoices', 'e-invoice'],
						'roles' => ['viewInvoice'],
					],
					[
						'allow' => true,
						'actions' => ['send-to-oblio'],
						'roles' => ['viewInvoice'],
						// creates the invoice in the external Oblio system; must not be
						// reachable via GET (CSRF)
						'verbs' => ['POST'],
					],
					[
						'allow' => true,
						'actions' => ['update'],
						'roles' => ['updateInvoice'],
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
			'dt-invoices' => InvoiceSearch::class,
		];
	}

	/**
	 * Pushes an invoice to Oblio (manual, idempotent — a re-send returns the stored document).
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
			$response['message']['title'] = Yii::t('common', 'Invoice already sent to Oblio.');
			if (Yii::$app->request->isAjax) {
				return $this->asJson($response);
			}
			Yii::$app->session->setFlash('info', $response['message']['title']);
			return $this->redirect(['index']);
		}

		try {
			OblioInvoice::createInvoiceFromLocalInvoice($model, [
				'seriesName' => $model->document_series,
				'disableAutoSeries' => 1,
				'number' => $model->document_number,
			]);

			$response['message']['title'] = Yii::t('common', 'Invoice {invoice} sent to Oblio successfully.', [
				'invoice' => $model->getDocumentSeriesNumber(),
			]);
		} catch (\Exception $e) {
			$response['success'] = false;
			$response['message']['title'] = Yii::t('common', 'Failed to send invoice to Oblio.');
			$response['message']['body'][] = $e->getMessage();

			Yii::error([
				'message' => 'Oblio invoice send failed.',
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
		$query = "
		    SELECT document_series, document_number, type, MIN(id) AS min_id
		    FROM invoice
		    GROUP BY document_series, document_number, type
		    HAVING COUNT(*) > 1
		    LIMIT 1
		";
		$result = Yii::$app->db->createCommand($query)->queryOne();
		if (!empty($result)) {
			$query = "
			    DELETE i
				FROM invoice i
				JOIN (
				    SELECT document_series, document_number, type, MIN(id) AS min_id
				    FROM invoice
				    GROUP BY document_series, document_number, type
				    HAVING COUNT(*) > 1
				) dup ON i.document_series = dup.document_series AND i.document_number = dup.document_number AND i.type = dup.type
				WHERE i.id > dup.min_id";
			Yii::$app->db->createCommand($query)->execute();
		}

		$integration = Integration::find()
			->where([
				'type' => Integration::TYPE_SPV,
				'status' => Integration::STATUS_ACTIVE,
				'deleted' => Integration::NO,
			])
			->one();

        return $this->render('index', [
			'integration' => $integration,
        ]);
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

		/** @var Template $template */
		$template = $model->getTemplates()->andWhere([
			'type' => Template::TYPE_INVOICE,
            'variant' => Template::INVOICE_VARIANT_FISCAL,
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
	 * Updates an existing Invoice model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionUpdate($id)
	{
		$model = $this->findModel($id, InvoiceForm::class);
		$result = true;

		Yii::$app->eventLog->beginRecord($model);
		if ($model->load(Yii::$app->request->post()) && ($result = $model->save())) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllTemplates']));
			Yii::$app->eventLog->endRecord();

			$message = Yii::t('common', 'Record has been updated.');
			if (Yii::$app->request->isAjax) {
				return $this->asJson([
					'success' => true,
					'message' => $message,
				]);
			}
			Yii::$app->session->setFlash('success', $message);

			return $this->redirect(['update', 'id' => $model->id]);
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
	 * Generates an XML document as export for e-Invoice.
	 *
	 * @return false|string
	 */
	public function actionEInvoice($id)
	{
		try {
			$model = $this->findModel($id, EInvoiceForm::class);
			$action = Yii::$app->request->get('action');
			if (!$model->saveModel($action)) {
				throw new \Exception();
			}
			if (!in_array($action, ['generate', 'download'])) {
				return $this->redirect(['index']);
			}

		} catch (\Exception $e) {
			if (!empty($model->errors)) {
				$errors = array_values($model->errors)[0];
				$message = implode('<br>', $errors);
				Yii::$app->session->setFlash('error', $message);
				switch ($action) {
					default:
						$this->redirect(['index']);
						break;
				}
			}
			return null;
		}
	}

    /**
     * Deletes existing Invoices models.
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
