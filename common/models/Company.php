<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\helpers\ArrayHelper;
use tws\helpers\Url;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%company}}".
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $tin
 * @property string $registration_number
 * @property string $address
 * @property string $street_name
 * @property string $street_number
 * @property string $staircase
 * @property string $block
 * @property string $floor
 * @property string $apartment
 * @property string $locality
 * @property string $zip_code
 * @property string $county
 * @property string $country
 * @property string $image
 * @property string $signature
 * @property string $attachment
 * @property string $nace_code
 * @property string $phone
 * @property string $email
 * @property string $fax
 * @property string $bank_account
 * @property string $bank
 * @property string $representative_first_name
 * @property string $representative_middle_name
 * @property string $representative_last_name
 * @property string $representative_function
 * @property string $representative_phone
 * @property string $representative_email
 * @property string $taxation
 * @property int $reverse_taxation
 * @property int $split_vat
 * @property int $vat_at_collection
 * @property string $net_turnover
 * @property string $social_capital
 * @property int $fiscal_status
 * @property int $type
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property User $user
 * @property Subscription[] $subscriptions
 * @property Invoice[] $invoices
 * @property CompanyTranslation[] $companyTranslations
 * @property CompanyTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 *
 * @property string fullAddress
 * @property string representativeFullName
 * @property string imageUrl
 * @property string signatureUrl
 */
class Company extends UuidActiveRecord
{
	const TAXATION_AFP = 1;
	const TAXATION_VAT_PAYER = 2;
	const TAXATION_INDIVIDUAL = 3;
	const TAXATION_ONG = 4;

	const COMPANY_TYPE_HOST = 1;
	const COMPANY_TYPE_CLIENT = 2;

	const FISCAL_STATUS_ACTIVE = 1;
	const FISCAL_STATUS_INACTIVE = 2;
	const FISCAL_STATUS_REACTIVE = 3;

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%company}}';
	}

	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'BlameableBehavior' => [
				'class' => BlameableBehavior::class,
			],
			'TimestampBehavior' => [
				'class' => TimestampBehavior::class,
				'value' => (new \DateTime)->format('Y-m-d H:i:s'),
			],
			'SoftDeleteBehavior' => [
				'class' => SoftDeleteBehavior::class,
				'softDeleteAttributeValues' => [
					'deleted' => static::YES,
				],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['user_id', 'reverse_taxation', 'split_vat', 'vat_at_collection', 'fiscal_status', 'type', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['user_id', 'name', 'status'], 'required'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['name', 'tin', 'registration_number', 'address', 'street_name', 'street_number', 'staircase', 'block', 'floor', 'apartment', 'locality', 'zip_code', 'county', 'country', 'image', 'signature', 'attachment', 'nace_code', 'phone', 'email', 'fax', 'bank_account', 'bank', 'representative_first_name', 'representative_middle_name', 'representative_last_name', 'representative_function', 'representative_phone', 'representative_email', 'taxation', 'net_turnover', 'social_capital'], 'string', 'max' => 255],
			[['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'user_id' => Yii::t('label', 'User ID'),
			'name' => Yii::t('label', 'Name'),
			'tin' => Yii::t('label', 'Tin'),
			'registration_number' => Yii::t('label', 'Registration Number'),
			'address' => Yii::t('label', 'Address'),
			'street_name' => Yii::t('label', 'Street Name'),
			'street_number' => Yii::t('label', 'Street Number'),
			'staircase' => Yii::t('label', 'Staircase'),
			'block' => Yii::t('label', 'Block'),
			'floor' => Yii::t('label', 'Floor'),
			'apartment' => Yii::t('label', 'Apartment'),
			'locality' => Yii::t('label', 'Locality'),
			'zip_code' => Yii::t('label', 'Zip Code'),
			'county' => Yii::t('label', 'County'),
			'country' => Yii::t('label', 'Country'),
			'image' => Yii::t('label', 'Image'),
			'signature' => Yii::t('label', 'Signature'),
			'attachment' => Yii::t('label', 'Attachment'),
			'nace_code' => Yii::t('label', 'Nace Code'),
			'phone' => Yii::t('label', 'Phone'),
			'email' => Yii::t('label', 'Email'),
			'fax' => Yii::t('label', 'Fax'),
			'bank_account' => Yii::t('label', 'Bank Account'),
			'bank' => Yii::t('label', 'Bank'),
			'representative_first_name' => Yii::t('label', 'Representative First Name'),
			'representative_middle_name' => Yii::t('label', 'Representative Middle Name'),
			'representative_last_name' => Yii::t('label', 'Representative Last Name'),
			'representative_function' => Yii::t('label', 'Representative Function'),
			'representative_phone' => Yii::t('label', 'Representative Phone'),
			'representative_email' => Yii::t('label', 'Representative Email'),
			'taxation' => Yii::t('label', 'Taxation'),
			'reverse_taxation' => Yii::t('label', 'Reverse Taxation'),
			'split_vat' => Yii::t('label', 'Split Vat'),
			'vat_at_collection' => Yii::t('label', 'Vat At Collection'),
			'net_turnover' => Yii::t('label', 'Net Turnover'),
			'social_capital' => Yii::t('label', 'Social Capital'),
			'fiscal_status' => Yii::t('label', 'Fiscal Status'),
			'type' => Yii::t('label', 'Type'),
			'created_by' => Yii::t('label', 'Created By'),
			'updated_by' => Yii::t('label', 'Updated By'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
			'status' => Yii::t('label', 'Status'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUser()
	{
		return $this->hasOne(User::class, ['id' => 'user_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriptions()
	{
		return $this->hasMany(Subscription::class, ['company_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getInvoices()
	{
		return $this->hasMany(Invoice::class, ['company_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getCompanyTranslations()
	{
		return $this->hasMany(CompanyTranslation::class, ['company_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param null|string $language
	 * @return null|CompanyTranslation
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->companyTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%company_translation}}', ['company_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCreator()
	{
		return $this->hasOne(User::class, ['id' => 'created_by']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUpdater()
	{
		return $this->hasOne(User::class, ['id' => 'updated_by']);
	}

	/**
	 * Gets the partialAddress.
	 *
	 * @return string
	 */
	public function getPartialAddress()
	{
		return $this->address;
	}

	/**
	 * Gets the fullAddress.
	 *
	 * @return string
	 */
	public function getFullAddress()
	{
		return implode(', ', array_filter([
			$this->address,
			$this->locality,
			$this->zip_code,
			$this->county,
			$this->country ? Country::findAllCountries()[$this->country]->translation->name : null,
		]));
	}

	/**
	 * Gets the representativeFullName.
	 *
	 * @return string
	 */
	public function getRepresentativeFullName()
	{
		return implode(', ', array_filter([
			$this->representative_first_name,
			$this->representative_middle_name,
			$this->representative_last_name,
		]));
	}

	/**
	 * Gets the imageUrl.
	 *
	 * @param bool $scheme
	 * @return string
	 */
	public function getImageUrl($scheme = false)
	{
		return Url::to("@uploads/company/{$this->uuid}/{$this->image}", $scheme);
	}

	/**
	 * Gets the signatureUrl.
	 *
	 * @param bool $scheme
	 * @return string
	 */
	public function getSignatureUrl($scheme = false)
	{
		return Url::to("@uploads/company/{$this->uuid}/{$this->signature}", $scheme);
	}

	/**
	 * Gets the attachmentUrl.
	 *
	 * @param bool $scheme
	 * @return string
	 */
	public function getAttachmentUrl($scheme = false)
	{
		return Url::to("@uploads/company/{$this->uuid}/{$this->attachment}", $scheme);
	}

	/**
	 * Model taxation labels.
	 *
	 * @return array
	 */
	public static function getTaxationLabels()
	{
		return [
			static::TAXATION_AFP => Yii::t('label', 'Authorized Person'),
			static::TAXATION_VAT_PAYER => Yii::t('label', 'VAT Payer'),
			static::TAXATION_INDIVIDUAL => Yii::t('label', 'Individual'),
			static::TAXATION_ONG => Yii::t('label', 'ONG'),
		];
	}

	/**
	 * Model CompanyType labels.
	 *
	 * @return array
	 */
	public static function getCompanyTypeLabels()
	{
		return [
			static::COMPANY_TYPE_HOST => Yii::t('label', 'Host'),
			static::COMPANY_TYPE_CLIENT => Yii::t('label', 'Client'),
		];
	}

	/**
	 * Model Fiscalstatus labels.
	 *
	 * @return array
	 */
	public static function getFiscalStatusLabels()
	{
		return [
			static::FISCAL_STATUS_ACTIVE => Yii::t('label', 'Active'),
			static::FISCAL_STATUS_INACTIVE => Yii::t('label', 'Inactive'),
			static::FISCAL_STATUS_REACTIVE => Yii::t('label', 'Reactive'),
		];
	}
}
