<?php

namespace common\models;

use tws\behaviors\DefaultBehavior;
use DateTime;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\helpers\ArrayHelper;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%template}}".
 *
 * @property int $id
 * @property int $type
 * @property int $variant
 * @property int $section
 * @property int $default
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property TemplateTranslation[] $templateTranslations
 * @property TemplateTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 */
class Template extends UuidActiveRecord
{
	const TYPE_EMAIL = 10;
	const EMAIL_VARIANT_ACCOUNT_ACTIVATION = 101;
	const EMAIL_VARIANT_PASSWORD_RESET = 102;
	const EMAIL_VARIANT_WELCOME = 103;
	const EMAIL_VARIANT_INVOICE_ISSUANCE = 104;
	const EMAIL_VARIANT_INVOICE_PAYMENT_CONFIRMATION = 105;
	const EMAIL_VARIANT_SUBSCRIPTION_CANCELLATION = 106;
	const EMAIL_VARIANT_SUBSCRIPTION_REACTIVATION = 107;
	const EMAIL_VARIANT_TWO_FACTOR_AUTHENTICATION = 108;
	// Affiliate module (docs/afiliere.md), sent by common\services\AffiliateNotificationService.
	const EMAIL_VARIANT_AFFILIATE_PAYOUT_PAID = 109;
	const EMAIL_VARIANT_AFFILIATE_INVOICE_REJECTED = 110;
	const EMAIL_VARIANT_AFFILIATE_APPLICATION_APPROVED = 111;
	const EMAIL_VARIANT_AFFILIATE_APPLICATION_REJECTED = 112;

	const TYPE_INVOICE = 30;
    const INVOICE_VARIANT_FISCAL = 301;
    const INVOICE_VARIANT_PROFORM = 302;
    const INVOICE_VARIANT_RECTIFY = 303;

    const TYPE_SUBSCRIBER = 40;
    const SUBSCRIBER_EMAIL = 401;
    const SUBSCRIBER_SMS = 402;
    const SUBSCRIBER_ISSUED_DOCUMENT = 403;

	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%template}}';
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
				'value' => (new DateTime)->format('Y-m-d H:i:s'),
			],
			'DefaultBehavior' => [
				'class' => DefaultBehavior::class,
				'groupAttributes' => ['type', 'variant'],
				'ensureDefaultValue' => true,
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
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['type', 'status'], 'required'],
			[['type', 'variant', 'section', 'default', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'type' => Yii::t('label', 'Type'),
			'variant' => Yii::t('label', 'Variant'),
			'section' => Yii::t('label', 'Section'),
			'default' => Yii::t('label', 'Default'),
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
	public function getTemplateTranslations()
	{
		return $this->hasMany(TemplateTranslation::class, ['template_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%template_translation}}', ['template_id' => 'id']);
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
	 * Gets the model translation.
	 *
	 * @param null|string $language
	 * @return null|TemplateTranslation
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->templateTranslations, 'language_id')[$language];
	}

	/**
	 * Model variant labels.
	 *
	 * @return array
	 */
	public static function getVariantLabels()
	{
		return [
			self::TYPE_EMAIL => [
				self::EMAIL_VARIANT_ACCOUNT_ACTIVATION => Yii::t('common', 'Account Activation'),
				self::EMAIL_VARIANT_PASSWORD_RESET => Yii::t('common', 'Password Reset'),
				self::EMAIL_VARIANT_WELCOME => Yii::t('common', 'Welcome'),
				self::EMAIL_VARIANT_INVOICE_ISSUANCE => Yii::t('common', 'Invoice Issuance'),
				self::EMAIL_VARIANT_INVOICE_PAYMENT_CONFIRMATION => Yii::t('common', 'Invoice Payment Confirmation'),
				self::EMAIL_VARIANT_SUBSCRIPTION_CANCELLATION => Yii::t('common', 'Subscription Cancellation'),
				self::EMAIL_VARIANT_SUBSCRIPTION_REACTIVATION => Yii::t('common', 'Subscription Reactivation'),
				self::EMAIL_VARIANT_TWO_FACTOR_AUTHENTICATION => Yii::t('common', 'Two-Factor Authentication'),
				self::EMAIL_VARIANT_AFFILIATE_PAYOUT_PAID => Yii::t('common', 'Affiliate Payout Paid'),
				self::EMAIL_VARIANT_AFFILIATE_INVOICE_REJECTED => Yii::t('common', 'Affiliate Invoice Rejected'),
				self::EMAIL_VARIANT_AFFILIATE_APPLICATION_APPROVED => Yii::t('common', 'Affiliate Application Approved'),
				self::EMAIL_VARIANT_AFFILIATE_APPLICATION_REJECTED => Yii::t('common', 'Affiliate Application Rejected'),
			],
            self::TYPE_INVOICE => [
                self::INVOICE_VARIANT_FISCAL => Yii::t('common', 'Invoice'),
                self::INVOICE_VARIANT_PROFORM => Yii::t('common', 'Proform'),
                self::INVOICE_VARIANT_RECTIFY => Yii::t('common', 'Rectify'),
            ],
            self::TYPE_SUBSCRIBER => [
                self::SUBSCRIBER_EMAIL => Yii::t('common', 'Email'),
                self::SUBSCRIBER_SMS => Yii::t('common', 'SMS'),
                self::SUBSCRIBER_ISSUED_DOCUMENT => Yii::t('common', 'Issued Document'),
            ],
		];
	}

	/**
	 * Finds default record by type.
	 * A fallback to a non-default record can be returned.
	 *
	 * @param int $type
	 * @param bool $fallback Flag that indicates if a non-default record should be returned instead.
	 * @return array|\yii\db\ActiveRecord|null|self
	 */
	public static function findDefaultByType($type, $fallback = true)
	{
		$query = static::find()
			->where([
				'type' => $type,
				'status' => self::STATUS_ACTIVE,
				'deleted' => self::NO,
			])
			->limit(1);

		if ((!$model = $query->andWhere(['default' => self::YES])->one())) {
			if ($fallback === true) {
				$model = $query->one();
			}
		}

		return $model;
	}

	/**
	 * Finds default record by type and variant.
	 * A fallback to a non-default record can be returned.
	 *
	 * @param int $type
	 * @param int $variant
	 * @param bool $fallback Flag that indicates if a non-default record should be returned instead.
	 * @return array|\yii\db\ActiveRecord|null|self
	 */
	public static function findDefaultByTypeAndVariant($type, $variant, $fallback = true)
	{
		$query = static::find()
			->where([
				'type' => $type,
				'variant' => $variant,
				'status' => self::STATUS_ACTIVE,
				'deleted' => self::NO,
			])
			->limit(1);

		if ((!$model = $query->andWhere(['default' => self::YES])->one())) {
			if ($fallback === true) {
				$model = $query->one();
			}
		}

		return $model;
	}
}
