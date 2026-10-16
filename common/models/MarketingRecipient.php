<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\helpers\Html;
use tws\helpers\Url;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%marketing_recipient}}".
 *
 * @property int $id
 * @property string $email
 * @property string $phone
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property MarketingCampaignHasRecipient[] $marketingCampaignHasRecipients
 * @property MarketingCampaign[] $marketingCampaigns
 * @property User $user
 * @property User $creator
 * @property User $updater
 */
class MarketingRecipient extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%marketing_recipient}}';
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
			[['created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['status'], 'required'],
			[['email', 'phone'], 'string', 'max' => 255],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'email' => Yii::t('label', 'Email'),
			'phone' => Yii::t('label', 'Phone'),
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
	public function getMarketingCampaignHasRecipients()
	{
		return $this->hasMany(MarketingCampaignHasRecipient::class, ['marketing_recipient_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getMarketingCampaigns()
	{
		return $this->hasMany(MarketingCampaign::class, ['id' => 'marketing_campaign_id'])->viaTable('{{%marketing_campaign_has_recipient}}', ['marketing_recipient_id' => 'id']);
	}

	/**
	 * Gets the user by email address.
	 *
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUser()
	{
		return $this->hasOne(User::class, ['email' => 'email']);
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
	 * Gets the model available short codes.
	 *
	 * @return array
	 */
	public static function getShortCodeItems()
	{
		return [
			'{{APP_NAME}}' => Yii::t('label', 'Application Name'),
			'{{APP_URL}}' => Yii::t('label', 'Application URL'),
			'{{APP_LOGO_URL}}' => Yii::t('label', 'Application Logo URL'),
			'{{APP_LOGO_ALT_URL}}' => Yii::t('label', 'Application Logo Alternative URL'),
			'{{COMPANY_NAME}}' => Yii::t('label', 'Company Name'),
			'{{COMPANY_TIN}}' => Yii::t('label', 'Company Taxpayer Identification Number'),
			'{{COMPANY_REG_NO}}' => Yii::t('label', 'Company Registration Number'),
			'{{COMPANY_LEGAL_REPRESENTATIVE}}' => Yii::t('label', 'Company Legal Representative'),
			'{{COMPANY_EMAIL}}' => Yii::t('label', 'Company Email'),
			'{{COMPANY_PHONE}}' => Yii::t('label', 'Company Phone'),
			'{{COMPANY_FAX}}' => Yii::t('label', 'Company Fax'),
			'{{COMPANY_ADDRESS}}' => Yii::t('label', 'Company Address'),
			'{{FIRST_NAME}}' => Yii::t('label', 'First Name'),
			'{{MIDDLE_NAME}}' => Yii::t('label', 'Middle Name'),
			'{{LAST_NAME}}' => Yii::t('label', 'Last Name'),
			'{{EMAIL}}' => Yii::t('label', 'Email'),
			'{{PHONE}}' => Yii::t('label', 'Phone'),
			'{{ADDRESS}}' => Yii::t('label', 'Address'),
		];
	}

	/**
	 * Gets the values for the model available short codes.
	 *
	 * @return array
	 */
	public function getShortCodeValues()
	{
		$contactSettings = Yii::$app->settings->getCategory('contact');
		$shortCodes = [
			'{{APP_NAME}}' => Yii::$app->name,
			'{{APP_URL}}' => Url::to(['/site/index'], true, '@frontend'),
			'{{APP_LOGO_URL}}' => Url::to('@uploads/' . Yii::$app->settings->get('appLogo'), true) ?: Url::to('@frontend/web/img/logo.png', true),
			'{{APP_LOGO_ALT_URL}}' => Url::to('@uploads/' . Yii::$app->settings->get('appLogoAlt'), true) ?: Url::to('@frontend/web/img/logo-alt.png', true),
			'{{COMPANY_NAME}}' => $contactSettings['company'],
			'{{COMPANY_TIN}}' => $contactSettings['tin'],
			'{{COMPANY_REG_NO}}' => $contactSettings['registrationNumber'],
			'{{COMPANY_LEGAL_REPRESENTATIVE}}' => implode(' ', array_filter([
				$contactSettings['firstName'],
				$contactSettings['middleName'],
				$contactSettings['lastName'],
			])),
			'{{COMPANY_EMAIL}}' => $contactSettings['email'],
			'{{COMPANY_PHONE}}' => $contactSettings['mobilePhone'] ?: $contactSettings['fixedPhone'],
			'{{COMPANY_FAX}}' => $contactSettings['fax'],
			'{{COMPANY_ADDRESS}}' => implode(', ', array_filter([
				$contactSettings['streetName'],
				$contactSettings['streetNumber'],
				$contactSettings['locality'],
				$contactSettings['zipCode'],
				$contactSettings['county'],
				$contactSettings['country'] ? \common\models\Country::findAllCountries()[$contactSettings['country']]->translation->name : null,
			])),
			'{{FIRST_NAME}}' => $this->user->first_name,
			'{{MIDDLE_NAME}}' => $this->user->middle_name,
			'{{LAST_NAME}}' => $this->user->last_name,
			'{{EMAIL}}' => $this->user->email,
			'{{PHONE}}' => $this->user->phone,
			'{{ADDRESS}}' => $this->user->subscriber->fullAddress,
		];

		return $shortCodes;
	}
}
