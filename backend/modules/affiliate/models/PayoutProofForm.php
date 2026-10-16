<?php

namespace backend\modules\affiliate\models;

use common\helpers\PrivateFileHelper;
use Yii;
use yii\base\Model;
use yii\web\UploadedFile;

/**
 * Upload of one or more payment proofs (bank statements). Files are validated with the
 * private storage's rule (PDF/JPG/PNG, size limit, sniffed content) and stored through
 * AffiliatePayoutService::addProofFiles() — never under the public uploads.
 */
class PayoutProofForm extends Model
{
	/**
	 * Most files accepted in one upload.
	 */
	const MAX_FILES = 10;

	/**
	 * @var UploadedFile[]
	 */
	public $proofFiles = [];

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			PrivateFileHelper::fileRule('proofFiles', static::MAX_FILES, false),
		];
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return [
			static::SCENARIO_DEFAULT => ['proofFiles'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'proofFiles' => Yii::t('label', 'Payment Proofs'),
		];
	}

	/**
	 * Takes the uploaded files of the request.
	 *
	 * @return bool whether any file was sent
	 */
	public function loadFiles()
	{
		$this->proofFiles = UploadedFile::getInstances($this, 'proofFiles');
		return !empty($this->proofFiles);
	}
}
