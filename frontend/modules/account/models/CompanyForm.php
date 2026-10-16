<?php

namespace frontend\modules\account\models;

use borales\extensions\phoneInput\PhoneInputValidator;
use common\models\Company;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;
use yii\web\UploadedFile;

class CompanyForm extends Company
{
	/**
	 * @var UploadedFile The imageFile.
	 */
	public $imageFile;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->country = Yii::$app->settings->get('defaultCountry', 'general');
		$this->status = static::STATUS_ACTIVE;
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return ArrayHelper::merge(parent::rules(), [
			[['tin', 'registration_number', 'name', 'status', 'email', 'phone', 'address', 'country', 'locality'], 'required'],
			['phone', PhoneInputValidator::class, 'skipOnEmpty' => true],
			[['imageFile'], 'file', 'extensions' => Yii::$app->params['image.extensions'], 'mimeTypes' => Yii::$app->params['image.mimeTypes'], 'maxSize' => Yii::$app->settings->get('maxFileSize'), 'maxFiles' => 1, 'skipOnEmpty' => true],
			[['county'], 'required', 'when' => function ($model) {
				return $model->country == 'RO';
			}, 'whenClient' => 'function (attribute, value) {
				if ($($form.find("[name*=\"country\"]")).val() == "RO") {
					$($form.find("[name*=\"county\"]")).closest(".form-group").addClass("required");
				} else {
					$($form.find("[name*=\"county\"]")).closest(".form-group").removeClass("required");
				}
                return attribute.$form.find("[name*=\"country\"]").val() == "RO";
    	    }'],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'imageFile' => Yii::t('label', 'Image'),
			'tin' => Yii::t('label', 'Tax Identification Number'),
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return Model::scenarios();
	}

	/**
	 * {@inheritdoc}
	 *
	 * Only what the form renders. The inherited rules make user_id, status, deleted and
	 * created_by safe too, which let a posted field move a company to another account.
	 */
	public function safeAttributes()
	{
		return array_values(array_intersect(parent::safeAttributes(), [
			'tin', 'registration_number', 'name', 'email', 'phone', 'country', 'county', 'locality', 'zip_code', 'address',
		]));
	}

	/**
	 * {@inheritdoc}
	 *
	 * The file validator in [[rules()]] only bites if the attribute is populated before
	 * validation runs; without this, saveFiles() would pull the raw upload straight from
	 * the request and the rule would never have been applied.
	 */
	public function load($data, $formName = null)
	{
		$result = parent::load($data, $formName);
		if (!empty($data)) {
			$this->imageFile = UploadedFile::getInstance($this, 'imageFile');
		}
		return $result;
	}

	/**
	 * Saves the company files.
	 *
	 * @return bool
	 */
	protected function saveFiles()
	{
		try {
			if (!($file = UploadedFile::getInstance($this, 'imageFile'))) {
				return true;
			}

			$dirPath = Yii::getAlias("@uploads/company/{$this->uuid}");
			$oldFilePath = "{$dirPath}/{$this->oldAttributes['image']}";
			$fileName = StringHelper::truncate(implode('_', array_filter([
				Inflector::slug($this->name),
				Yii::$app->security->generateRandomString(8),
			])), 255 - (mb_strlen($file->extension) + 1), '') . ".{$file->extension}";
			$filePath = "{$dirPath}/{$fileName}";

			FileHelper::createDirectory($dirPath);
			if (!$file->saveAs($filePath)) {
				throw new \Exception();
			}
			if (!$this->updateAttributes(['image' => $fileName])) {
				throw new \Exception();
			}
			if (is_file($oldFilePath) && $oldFilePath != $filePath) {
				FileHelper::unlink($oldFilePath);
			}
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Saves the model.
	 *
	 * @return bool|\yii\db\ActiveRecord|self
	 */
	public function saveModel()
	{
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->saveFiles()) {
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
