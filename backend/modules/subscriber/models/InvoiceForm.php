<?php

namespace backend\modules\subscriber\models;

use common\models\Invoice;
use Yii;
use yii\helpers\ArrayHelper;

class InvoiceForm extends Invoice
{

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['e_invoice_status'], 'integer'],
			[['e_invoice_upload_id', 'e_invoice_download_id', 'e_invoice_error'], 'string', 'max' => 255],
			[['e_invoice_sent_at'], 'safe'],
			[['e_invoice_upload_id', 'e_invoice_download_id', 'e_invoice_sent_at', 'e_invoice_error'], 'default', 'value' => null],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'e_invoice_upload_id' => Yii::t('label', 'Upload ID'),
			'e_invoice_download_id' => Yii::t('label', 'Download ID'),
			'e_invoice_status' => Yii::t('label', 'Status'),
			'e_invoice_error' => Yii::t('label', 'Error'),
			'e_invoice_sent_at' => Yii::t('label', 'Sent At'),
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function afterFind()
	{
		parent::afterFind();
		$merchant = $this->merchantDetails;
		$details = $this->getUnserializedValue('details');
		if (!empty($details['merchant'])) {
			foreach ($details['merchant'] as $key => $value) {
				switch ($key) {
					case 'name':
					case 'tin':
					case 'registration_number':
					case 'email':
					case 'phone':
					case 'address':
					case 'country':
					case 'county':
					case 'locality':
						$details['merchant'][$key] = $value ?: $merchant[$key];
						break;
					default:
						$details['merchant'][$key] = $value;
						break;
				}
			}
		}
		$this->setAttribute('details', $details);
	}

	/**
	 * @inheritdoc
	 */
	public function save($runValidation = true, $attributeNames = null)
	{
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			$details = Yii::$app->request->post('InvoiceForm')['details'];
			$this->details = $details;
			$this->setSerializedValue('details', $this->details);
			if (!parent::save($runValidation, $attributeNames)) {
				throw new \Exception();
			}
			$dbTransaction->commit();
			return $this;
		} catch(\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}
}
