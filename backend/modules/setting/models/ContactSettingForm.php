<?php

namespace backend\modules\setting\models;

use borales\extensions\phoneInput\PhoneInputValidator;
use common\models\Setting;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class ContactSettingForm extends Setting
{
	/**
	 * @var string The company administrator first name.
	 */
	public $firstName;

	/**
	 * @var string The company administrator middle name.
	 */
	public $middleName;

	/**
	 * @var string The company administrator last name.
	 */
	public $lastName;

	/**
	 * @var string The company name.
	 */
	public $company;

	/**
	 * @var string The company registration number.
	 */
	public $registrationNumber;

	/**
	 * @var string The company Taxpayer Identification Number.
	 */
	public $tin;

	/**
	 * @var array The multilingual schedule.
	 */
	public $schedule = [];

	/**
	 * @var string The mobile mobile phone.
	 */
	public $mobilePhone;

	/**
	 * @var string The mobile fixed phone.
	 */
	public $fixedPhone;

	/**
	 * @var string The fax number.
	 */
	public $fax;

	/**
	 * @var string The email.
	 */
	public $email;

	/**
	 * @var string The street name.
	 */
	public $streetName;

	/**
	 * @var string The street name.
	 */
	public $streetNumber;

	/**
	 * @var string The locality.
	 */
	public $locality;

	/**
	 * @var string The ZIP code.
	 */
	public $zipCode;

	/**
	 * @var string The county.
	 */
	public $county;

	/**
	 * @var string The country.
	 */
	public $country;

	/**
	 * @var string The latitude.
	 */
	public $latitude;

	/**
	 * @var string The longitude.
	 */
	public $longitude;

	/**
	 * @var string The address.
	 */
	public $address;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->country = Yii::$app->settings->get('defaultCountry', 'general');
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['firstName', 'middleName', 'lastName', 'company', 'registrationNumber', 'tin', 'mobilePhone', 'fixedPhone', 'fax', 'email', 'streetName', 'streetNumber', 'locality', 'zipCode', 'county', 'country', 'latitude', 'longitude'], 'string'],
			[['firstName', 'middleName', 'lastName', 'company', 'registrationNumber', 'tin', 'mobilePhone', 'fixedPhone', 'fax', 'email', 'streetName', 'streetNumber', 'locality', 'zipCode', 'county', 'country', 'latitude', 'longitude'], 'trim'],
			[['schedule'], 'each', 'rule' => ['trim']],
			['mobilePhone', PhoneInputValidator::class, 'skipOnEmpty' => true],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'firstName' => Yii::t('label', 'First Name'),
			'middleName' => Yii::t('label', 'Middle Name'),
			'lastName' => Yii::t('label', 'Last Name'),
			'company' => Yii::t('label', 'Company'),
			'registrationNumber' => Yii::t('label', 'Registration Number'),
			'tin' => Yii::t('label', 'Taxpayer Identification Number'),
			'schedule' => Yii::t('label', 'Schedule'),
			'mobilePhone' => Yii::t('label', 'Mobile Phone'),
			'fixedPhone' => Yii::t('label', 'Fixed Phone'),
			'fax' => Yii::t('label', 'Fax'),
			'email' => Yii::t('label', 'Email'),
			'streetName' => Yii::t('label', 'Street Name'),
			'streetNumber' => Yii::t('label', 'Street Number'),
			'locality' => Yii::t('label', 'City'),
			'zipCode' => Yii::t('label', 'Zip Code'),
			'county' => Yii::t('label', 'County'),
			'country' => Yii::t('label', 'Country'),
			'latitude' => Yii::t('label', 'Latitude'),
			'longitude' => Yii::t('label', 'Longitude'),
			'address' => Yii::t('label', 'Address'),
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
	 * @inheritdoc
	 */
	public function afterFind()
	{
		parent::afterFind();

		$this->setAttributes($this->getUnserializedValue('setting'));
	}

	/**
	 * Saves the setting.
	 *
	 * @return bool|\yii\db\ActiveRecord
	 */
	public function saveModel()
	{
		$this->name = 'contact';
		$this->type = static::TYPE_APP;
		$this->status = static::STATUS_ACTIVE;
		$this->setSerializedValue('setting', [
			'firstName' => $this->firstName,
			'middleName' => $this->middleName,
			'lastName' => $this->lastName,
			'company' => $this->company,
			'registrationNumber' => $this->registrationNumber,
			'tin' => $this->tin,
			'schedule' => $this->schedule,
			'mobilePhone' => $this->mobilePhone,
			'fixedPhone' => $this->fixedPhone,
			'fax' => $this->fax,
			'email' => $this->email,
			'streetName' => $this->streetName,
			'streetNumber' => $this->streetNumber,
			'locality' => $this->locality,
			'zipCode' => $this->zipCode,
			'county' => $this->county,
			'country' => $this->country,
			'latitude' => $this->latitude,
			'longitude' => $this->longitude,
		]);

		return $this->save();
	}
}
