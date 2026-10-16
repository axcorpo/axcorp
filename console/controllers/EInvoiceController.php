<?php
namespace console\controllers;

use backend\modules\subscriber\models\EInvoiceForm;
use common\models\Integration;
use common\models\Invoice;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Expression;

ini_set('memory_limit', '-1');
set_time_limit(0);

class EInvoiceController extends Controller
{
	/**
	 * @var \DateTime The current date and time instance used for this process operations.
	 */
	public static $currentDate;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		self::$currentDate = new \DateTime();
	}

	public function actionUpload()
	{
		try {
			$currentDate = self::$currentDate->format('Y-m-d H:i:s');
			$count = 0;
			$integration = Integration::find()
				->alias('i')
				->select([
					'i.*',
				])
				->joinWith([
					'workspaces w',
				])
				->where([
					'i.type' => Integration::TYPE_SPV,
					'i.status' => Integration::STATUS_ACTIVE,
					'i.deleted' => Integration::NO,
				])
				->andWhere([
					'IS', 'w.id', null
				])
				->andWhere(new Expression("TIMESTAMPDIFF(SECOND, '{$currentDate}', [[i.expire_at]]) > 0"))
				->one();
			if (!empty($integration)) {
				$issueDate = '2024-06-25 00:00:00';
				$query = Invoice::find()
					->alias('i')
					->select([
						'i.id',
						'i.e_invoice_upload_id',
						'i.e_invoice_download_id',
						'i.e_invoice_status',
						'i.e_invoice_sent_at',
						'i.e_invoice_error',
					])
					->andWhere([
						'i.deleted' => Invoice::NO,
						'i.status' => Invoice::STATUS_PAID,
					])
					->andWhere([
						'AND',
						['IN', 'i.e_invoice_status', [Invoice::E_STATUS_NOT_SENT]],
						['IS', 'i.e_invoice_upload_id', null],
					])
					->andWhere(new Expression("TIMESTAMPDIFF(SECOND, '{$issueDate}', [[i.issued_at]]) > 0"));
				$invoices = $query->all();
				if (!empty($invoices)) {
					foreach ($invoices as $invoice) {
						$model = EInvoiceForm::findOne(['id' => $invoice->id]);
						if (!$model->saveModel('upload')) {
							continue;
						}
						$count++;
					}
				}
			}

			$this->stdout("[" . date("Y-m-d H:i:s") . "] Processed {$count} invoice(s).");
			return ExitCode::OK;
		} catch (\Exception $e) {
			$this->stderr($e->getMessage());
			return ExitCode::UNSPECIFIED_ERROR;
		} catch (\Throwable $e) {
			$this->stderr($e->getMessage());
			return ExitCode::UNSPECIFIED_ERROR;
		}
	}

	/**
	 * Invoice verify.
	 *
	 * @return int
	 */
	public function actionVerify()
	{
		try {
			$currentDate = self::$currentDate->format('Y-m-d H:i:s');
			$count = 0;
			$integration = Integration::find()
				->alias('i')
				->select([
					'i.*',
				])
				->joinWith([
					'workspaces w',
				])
				->where([
					'i.type' => Integration::TYPE_SPV,
					'i.status' => Integration::STATUS_ACTIVE,
					'i.deleted' => Integration::NO,
				])
				->andWhere([
					'IS', 'w.id', null
				])
				->andWhere(new Expression("TIMESTAMPDIFF(SECOND, '{$currentDate}', [[i.expire_at]]) > 0"))
				->one();
			if (!empty($integration)) {
				$issueDate = '2024-06-25 00:00:00';
				$invoices = Invoice::find()
					->alias('i')
					->select([
						'i.id',
						'i.e_invoice_upload_id',
						'i.e_invoice_download_id',
						'i.e_invoice_status',
						'i.e_invoice_sent_at',
						'i.e_invoice_error',
					])
					->andWhere([
						'i.deleted' => Invoice::NO,
						'i.status' => Invoice::STATUS_PAID,
					])
					->andWhere([
						'AND',
						['IN', 'i.e_invoice_status', [Invoice::E_STATUS_SENT, Invoice::E_STATUS_PENDING]],
						['IS NOT', 'i.e_invoice_upload_id', null],
					])
					->andWhere(new Expression("TIMESTAMPDIFF(SECOND, '{$issueDate}', [[i.issued_at]]) > 0"))
					->all();
				if (!empty($invoices)) {
					foreach ($invoices as $invoice) {
						$model = EInvoiceForm::findOne(['id' => $invoice->id]);
						if (!$model->saveModel('verify')) {
							continue;
						}
						$count++;
					}
				}
			}

			$this->stdout("[" . date("Y-m-d H:i:s") . "] Processed {$count} invoice(s).");
			return ExitCode::OK;
		} catch (\Exception $e) {
			$this->stderr($e->getMessage());
			return ExitCode::UNSPECIFIED_ERROR;
		} catch (\Throwable $e) {
			$this->stderr($e->getMessage());
			return ExitCode::UNSPECIFIED_ERROR;
		}
	}
}
