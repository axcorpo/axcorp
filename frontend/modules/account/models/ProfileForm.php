<?php

namespace frontend\modules\account\models;

use borales\extensions\phoneInput\PhoneInputValidator;
use common\models\Country;
use common\models\MarketingRecipient;
use common\models\Subscriber;
use common\models\User;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;
use yii\web\UploadedFile;

class ProfileForm extends User
{
	/**
	 * The form attributes stored on the subscriber row.
	 */
	const SUBSCRIBER_ATTRIBUTES = ['pin', 'street_name', 'street_number', 'address', 'locality', 'zip_code', 'county', 'country', 'date_of_birth'];

	/**
	 * @var UploadedFile The imageFile.
	 */
	public $imageFile;

	/**
	 * @var string The personal identification number.
	 */
	public $pin;

	/**
	 * @var string The street name.
	 */
	public $street_name;

	/**
	 * @var string The street number.
	 */
	public $street_number;

	/**
	 * @var string The address.
	 */
	public $address;

	/**
	 * @var string The locality.
	 */
	public $locality;

	/**
	 * @var string The zip code.
	 */
	public $zip_code;

	/**
	 * @var string The county.
	 */
	public $county;

	/**
	 * @var string The country.
	 */
	public $country;

	/**
	 * @var string The date of birth.
	 */
	public $date_of_birth;

	/**
	 * @var bool Indicates if the user is a marketing recipient.
	 */
	public $marketing_recipient;


	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['first_name', 'last_name', 'phone'], 'required'],
			[['address', 'locality', 'county', 'country'], 'required', 'when' => function ($model) {
				return $model->getHasSubscriberProfile();
			}],
			['phone', PhoneInputValidator::class, 'skipOnEmpty' => true],
//			[['phone'], 'unique'],
			[['gender'], 'integer'],
			['gender', 'in', 'range' => [1, 2]],
			[['date_of_birth'], 'safe'],
			['marketing_recipient', 'boolean'],
			[['first_name', 'middle_name', 'last_name', 'phone', 'pin', 'street_name', 'street_number', 'address', 'locality', 'zip_code', 'county'], 'string', 'max' => 255],
			[['first_name', 'middle_name', 'last_name', 'phone', 'pin', 'street_name', 'street_number', 'address', 'locality', 'zip_code', 'county'], 'trim'],
			[['imageFile'], 'file', 'extensions' => ['jpeg', 'jpg', 'png', 'gif'], 'mimeTypes' => ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'], 'maxSize' => Yii::$app->settings->get('maxFileSize'), 'skipOnEmpty' => true],
			[['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['parent_id' => 'id']],
			[['country'], 'exist', 'skipOnError' => true, 'targetClass' => Country::class, 'targetAttribute' => ['country' => 'iso_alpha2']],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'imageFile' => Yii::t('label', 'Image'),
			'pin' => Yii::t('label', 'Personal Identification Number'),
			'street_name' => Yii::t('label', 'Street Name'),
			'street_number' => Yii::t('label', 'Street Number'),
			'address' => Yii::t('label', 'Address'),
			'locality' => Yii::t('label', 'Locality'),
			'zip_code' => Yii::t('label', 'Zip Code'),
			'county' => Yii::t('label', 'County'),
			'country' => Yii::t('label', 'Country'),
			'date_of_birth' => Yii::t('label', 'Date Of Birth'),
			'marketing_recipient' => Yii::t('label', 'Receive newsletter emails'),
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios()
	{
		$scenarios = Model::scenarios();
		// The subscriber fields are not rendered for a pure affiliate, so they are not
		// loadable either (forms load only the fields they show).
		if (!$this->getHasSubscriberProfile()) {
			foreach ($scenarios as $name => $attributes) {
				$scenarios[$name] = array_values(array_diff($attributes, static::SUBSCRIBER_ATTRIBUTES));
			}
		}
		return $scenarios;
	}

	/**
	 * Whether the profile carries the subscriber data (address, date of birth). A pure
	 * affiliate has no subscriber row, and saving the profile must not create one: that
	 * would turn them into a subscriber and open the subscriber pages.
	 *
	 * @return bool
	 */
	public function getHasSubscriberProfile()
	{
		return $this->subscriber !== null || !$this->getIsAffiliate(false);
	}

	/**
	 * @inheritdoc
	 */
	public function afterFind()
	{
		parent::afterFind();

		if (($subscriber = $this->subscriber) !== null) {
			foreach (static::SUBSCRIBER_ATTRIBUTES as $attribute) {
				$this->$attribute = $subscriber->$attribute;
			}
		}

		$this->marketing_recipient = MarketingRecipient::find()
			->andWhere([
				'email' => $this->email,
				'status' => MarketingRecipient::STATUS_ACTIVE,
				'deleted' => MarketingRecipient::NO,
			])
			->exists();
	}

	/**
	 * Saves the Subscriber model.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function saveSubscriber()
	{
		if (!$this->getHasSubscriberProfile()) {
			return true;
		}
		try {
			if (!$subscriber = $this->subscriber) {
				$subscriber = new Subscriber();
				$subscriber->user_id = $this->id;
				$subscriber->code = Subscriber::generateUniqueCode();
				$subscriber->status = Subscriber::STATUS_ACTIVE;
			}
			$subscriber->pin = $this->pin;
			$subscriber->street_name = $this->street_name;
			$subscriber->street_number = $this->street_number;
			$subscriber->address = $this->address;
			$subscriber->locality = $this->locality;
			$subscriber->county = $this->county;
			$subscriber->country = $this->country;
			$subscriber->zip_code = $this->zip_code;
			$subscriber->date_of_birth = $this->date_of_birth;

			if (!$subscriber->save()) {
				throw new \Exception($subscriber->getErrorSummary(false)[0]);
			}
			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * Saves the MarketingRecipient model.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function saveMarketingRecipient()
	{
		try {
			/** @var MarketingRecipient $marketingRecipient */
			$marketingRecipient = MarketingRecipient::find()
				->andWhere([
					'email' => $this->email,
					'status' => MarketingRecipient::STATUS_ACTIVE,
					'deleted' => MarketingRecipient::NO,
				])
				->one();
			if (!$marketingRecipient) {
				$marketingRecipient = new MarketingRecipient();
			}
			$marketingRecipient->email = $this->email;
			$marketingRecipient->phone = $this->phone;
			$marketingRecipient->status = $this->marketing_recipient ? MarketingRecipient::STATUS_ACTIVE : MarketingRecipient::STATUS_INACTIVE;
			if (!$marketingRecipient->save()) {
				throw new \Exception($marketingRecipient->getErrorSummary(false)[0]);
			}
			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
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
	 * Saves the user files.
	 *
	 * @return bool
	 */
	protected function saveFiles()
	{
		try {
			if (!($file = UploadedFile::getInstance($this, 'imageFile'))) {
				return true;
			}

			$dirPath = Yii::getAlias("@uploads/user/{$this->uuid}");
			$oldFilePath = "{$dirPath}/{$this->oldAttributes['image']}";
			$fileName = StringHelper::truncate(implode('_', array_filter([
				Inflector::slug($this->fullName),
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
			if (!$this->validate()) {
				throw new \Exception();
			}
			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->saveSubscriber()) {
				throw new \Exception();
			}
			if (!$this->saveMarketingRecipient()) {
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
