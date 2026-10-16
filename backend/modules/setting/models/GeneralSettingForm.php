<?php

namespace backend\modules\setting\models;

use common\models\Country;
use common\models\Language;
use common\models\ScheduledTask;
use common\models\Setting;
use common\traits\SecretSettingTrait;
use common\models\User;
use tws\helpers\FileHelper;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use tws\helpers\Url;
use yii\validators\FileValidator;
use yii\web\UploadedFile;

class GeneralSettingForm extends Setting
{
	/**
	 * What a service-account key file may be sniffed as.
	 */
	const TTS_FILE_MIME_TYPES = ['application/json', 'text/plain'];

	use SecretSettingTrait;

	/**
	 * {@inheritdoc}
	 */
	public function secretAttributes()
	{
		return [
			'reCaptchaSecretKey',
			'oblioClientSecret',
			'googleMapKey',
		];
	}

	/**
	 * @var string The application host info.
	 */
	public $hostInfo;

	/**
	 * @var string The application name.
	 */
	public $appName;

	/**
	 * @var array The multilingual application description.
	 */
	public $appDescription = [];

	/**
	 * @var string The application logo.
	 */
	public $appLogo;

	/**
	 * @var string The application alternative logo.
	 */
	public $appLogoAlt;

	/**
	 * @var int The number of items displayed on a page.
	 */
	public $itemsPerPage = 25;

	/**
	 * @var string The default country.
	 */
	public $defaultCountry;

	/**
	 * @var string The default language.
	 */
	public $defaultLanguage;

	/**
	 * @var string The time zone.
	 */
	public $timeZone;

	/**
	 * @var string The time format.
	 */
	public $timeFormat;

	/**
	 * @var string The date format.
	 */
	public $dateFormat;

	/**
	 * @var string The date and time format.
	 */
	public $datetimeFormat;

	/**
	 * @var string The currency code.
	 */
	public $currencyCode;

	/**
	 * @var int The type of user account activation.
	 */
	public $userAccountActivation;

	/**
	 * @var int The time after the password reset token expires.
	 */
	public $userPasswordResetTokenExpiration;

	/**
	 * @var int The time for remember me functionality.
	 */
	public $userLoginDuration;

	/**
	 * @var string The maximum file size.
	 */
	public $maxFileSize;

	/**
	 * @var bool Flag that indicates if the app should record event logs.
	 */
	public $enableEventLogs;

	/**
	 * @var bool Flag that indicates if the app should use soft delete.
	 */
	public $enableSoftDelete;

	/**
	 * @var bool Flag that indicates if the app should display documentation.
	 */
	public $enableDocumentation;

	/**
	 * @var string The Google Map API key.
	 */
	public $googleMapKey;

    /**
     * @var string The Google reCAPTCHA Site key.
     */
    public $reCaptchaSiteKey;

    /**
     * @var string The Google reCAPTCHA Secret key.
     */
    public $reCaptchaSecretKey;

    /**
     * @var string The VAT rate in %.
     */
    public $vatRate;

	/**
	 * @var int The cycle period for exchange Rate scheduled task.
	 */
	public $exchangeRatePeriod;

	/**
	 * @var string The cycle for exchange Rate scheduled task.
	 */
	public $exchangeRateCycle;

	/**
	 * @var int The month of the year for exchange Rate scheduled task.
	 */
	public $exchangeRateMonth;

	/**
	 * @var int The day of month for exchange Rate scheduled task.
	 */
	public $exchangeRateDay;

	/**
	 * @var string The time for exchange Rate scheduled task.
	 */
	public $exchangeRateTime;

	/**
	 * @var int The cycle period for backup scheduled task.
	 */
	public $backupPeriod;

	/**
	 * @var string The cycle for backup scheduled task.
	 */
	public $backupCycle;

	/**
	 * @var int The month of the year for backup scheduled task.
	 */
	public $backupMonth;

	/**
	 * @var int The day of month for backup scheduled task.
	 */
	public $backupDay;

	/**
	 * @var string The time for backup scheduled task.
	 */
	public $backupTime;

	/**
	 * @var array Enable two-factor authentication.
	 */
	public $enableTwoFactorAuthentication;

	/**
	 * @var string The duration for two-factor authentication.
	 */
	public $twoFactorAuthenticationDuration;

	/**
	 * @var string The Apple Store Url.
	 */
	public $appleStoreUrl;

	/**
	 * @var string The Google Play Store Url.
	 */
	public $googlePlayUrl;

	/**
	 * @var string The Oblio Client ID.
	 */
	public $oblioClientId;

	/**
	 * @var string The Oblio Client Secret.
	 */
	public $oblioClientSecret;

	/**
	 * @var string The Text To Speech Service Account File.
	 */
	public $textToSpeechServiceAccountFile;

    /**
     * @var array Enable google translator.
     */
    public $translator = [];

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->hostInfo = Yii::$app->request->hostInfo;
		$this->timeZone = Yii::$app->formatter->defaultTimeZone;
		$this->timeFormat = Yii::$app->formatter->timeFormat;
		$this->dateFormat = Yii::$app->formatter->dateFormat;
		$this->datetimeFormat = Yii::$app->formatter->datetimeFormat;
		$this->currencyCode = Yii::$app->formatter->currencyCode;
		$this->userAccountActivation = User::ACCOUNT_ACTIVATION_AUTOMATIC;
		$this->userPasswordResetTokenExpiration = 1 * 60;
		$this->userLoginDuration = 3600 * 24 * 30;
		$this->maxFileSize = 1024 * 1024;
        $this->vatRate = 0.00;
		$this->exchangeRatePeriod = 1;
		$this->exchangeRateCycle = ScheduledTask::CYCLE_DAY;
		$this->exchangeRateTime = '14:00';
		$this->backupPeriod = 1;
		$this->backupCycle = ScheduledTask::CYCLE_MONTH;
		$this->backupDay = 1;
		$this->backupTime = '00:00';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['appName', 'timeZone', 'timeFormat', 'dateFormat', 'datetimeFormat', 'userAccountActivation', 'userPasswordResetTokenExpiration'], 'required'],
			[['appLogo'], 'file', 'extensions' => ['jpeg', 'jpg', 'png', 'gif'], 'mimeTypes' => ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'], 'maxSize' => Yii::$app->settings->get('maxFileSize'), 'maxFiles' => 1, 'skipOnEmpty' => true],
			[['appLogoAlt'], 'file', 'extensions' => ['jpeg', 'jpg', 'png', 'gif'], 'mimeTypes' => ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'], 'maxSize' => Yii::$app->settings->get('maxFileSize'), 'maxFiles' => 1, 'skipOnEmpty' => true],
			[['userAccountActivation'], 'integer'],
			[['userPasswordResetTokenExpiration'], 'integer', 'min' => 10],
			[['userLoginDuration'], 'integer', 'min' => 0],
			['maxFileSize', 'integer', 'min' => 5],
			['itemsPerPage', 'integer', 'min' => 5, 'max' => 150],
			[['enableEventLogs', 'enableSoftDelete', 'enableDocumentation', 'enableTwoFactorAuthentication'], 'boolean'],
			[['appDescription'], 'each', 'rule' => ['trim']],
			[['appName', 'timeZone', 'timeFormat', 'dateFormat', 'datetimeFormat', 'currencyCode', 'googleMapKey', 'reCaptchaSiteKey', 'reCaptchaSecretKey', 'appleStoreUrl', 'googlePlayUrl', 'oblioClientId', 'oblioClientSecret'], 'string'],
			[['appName', 'timeZone', 'timeFormat', 'dateFormat', 'datetimeFormat', 'currencyCode', 'googleMapKey', 'reCaptchaSiteKey', 'reCaptchaSecretKey', 'appleStoreUrl', 'googlePlayUrl', 'oblioClientId', 'oblioClientSecret'], 'trim'],
			[['defaultCountry'], 'exist', 'skipOnError' => true, 'targetClass' => Country::class, 'targetAttribute' => ['defaultCountry' => 'iso_alpha2']],
			[['defaultLanguage'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['defaultLanguage' => 'language_id']],

			[['exchangeRatePeriod'], 'integer'],
			[['exchangeRateCycle', 'exchangeRateTime'], 'string', 'max' => 255],
			[['exchangeRateMonth'], 'integer', 'min' => 1, 'max' => 12],
			[['exchangeRateDay'], 'integer', 'min' => 1, 'max' => 31],
			['exchangeRateMonth', 'required', 'when' => function () {
				return $this->exchangeRateCycle == ScheduledTask::CYCLE_YEAR;
			}, 'whenClient' => 'function (attribute, value) {
				return attribute.$form.find("[name*=\"[exchangeRateCycle]\"]").val() == "' . ScheduledTask::CYCLE_YEAR . '";
			}'],
			['exchangeRateDay', 'required', 'when' => function () {
				return $this->exchangeRateCycle != ScheduledTask::CYCLE_DAY;
			}, 'whenClient' => 'function (attribute, value) {
				return attribute.$form.find("[name*=\"[exchangeRateCycle]\"]").val() != "' . ScheduledTask::CYCLE_DAY . '";
			}'],
			['exchangeRateTime', 'required'],

            [['vatRate'], 'number'],

			[['backupPeriod'], 'integer'],
			[['backupCycle', 'backupTime'], 'string', 'max' => 255],
			[['backupMonth'], 'integer', 'min' => 1, 'max' => 12],
			[['backupDay'], 'integer', 'min' => 1, 'max' => 31],
			['backupMonth', 'required', 'when' => function () {
				return $this->backupCycle == ScheduledTask::CYCLE_YEAR;
			}, 'whenClient' => 'function (attribute, value) {
				return attribute.$form.find("[name*=\"[backupCycle]\"]").val() == "' . ScheduledTask::CYCLE_YEAR . '";
			}'],
			['backupDay', 'required', 'when' => function () {
				return $this->backupCycle != ScheduledTask::CYCLE_DAY;
			}, 'whenClient' => 'function (attribute, value) {
				return attribute.$form.find("[name*=\"[backupCycle]\"]").val() != "' . ScheduledTask::CYCLE_DAY . '";
			}'],
			['backupTime', 'required'],
			[['twoFactorAuthenticationDuration'], 'integer', 'min' => 0],
			[['textToSpeechServiceAccountFile'], 'file', 'extensions' => ['json'], 'mimeTypes' => static::TTS_FILE_MIME_TYPES, 'checkExtensionByMimeType' => false, 'maxSize' => Yii::$app->settings->get('maxFileSize'), 'skipOnEmpty' => true],
            [['translator'], 'safe'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'appName' => Yii::t('label', 'App Name'),
			'appDescription' => Yii::t('label', 'App Description'),
			'appLogo' => Yii::t('label', 'App Logo'),
			'appLogoAlt' => Yii::t('label', 'App Logo Alternative'),
			'itemsPerPage' => Yii::t('label', 'Items Per Page'),
			'defaultCountry' => Yii::t('label', 'Default Country'),
			'defaultLanguage' => Yii::t('label', 'Default Language'),
			'timeZone' => Yii::t('label', 'Time Zone'),
			'timeFormat' => Yii::t('label', 'Time Format'),
			'dateFormat' => Yii::t('label', 'Date Format'),
			'datetimeFormat' => Yii::t('label', 'Datetime Format'),
			'currencyCode' => Yii::t('label', 'Currency Code'),
			'userAccountActivation' => Yii::t('label', 'User Account Activation'),
			'userPasswordResetTokenExpiration' => Yii::t('label', 'User Password Reset Code Expiration'),
			'userLoginDuration' => Yii::t('label', 'User Login Duration'),
			'maxFileSize' => Yii::t('label', 'Max File Size'),
            'vatRate' => Yii::t('label', 'VAT Rate'),
			'enableEventLogs' => Yii::t('label', 'Enable Event Logs'),
			'enableSoftDelete' => Yii::t('label', 'Enable Soft Delete'),
			'enableDocumentation' => Yii::t('label', 'Enable Documentation'),
			'googleMapKey' => Yii::t('label', 'Google Map Key'),
            'reCaptchaSiteKey' => Yii::t('label', 'Google reCAPTCHA Site Key'),
            'reCaptchaSecretKey' => Yii::t('label', 'Google reCAPTCHA Secret Key'),
			'exchangeRatePeriod' => Yii::t('label', 'Period'),
			'exchangeRateCycle' => Yii::t('label', 'Cycle'),
			'exchangeRateMonth' => Yii::t('label', 'Month'),
			'exchangeRateDay' => Yii::t('label', 'Day'),
			'exchangeRateTime' => Yii::t('label', 'Time'),
			'backupPeriod' => Yii::t('label', 'Period'),
			'backupCycle' => Yii::t('label', 'Cycle'),
			'backupMonth' => Yii::t('label', 'Month'),
			'backupDay' => Yii::t('label', 'Day'),
			'backupTime' => Yii::t('label', 'Time'),
			'enableTwoFactorAuthentication' => Yii::t('label', 'Enable Two-Factor Authentication'),
			'twoFactorAuthenticationDuration' => Yii::t('label', 'Two-Factor Authentication Duration'),
			'appleStoreUrl' => Yii::t('label', 'Apple Store Url'),
			'googlePlayUrl' => Yii::t('label', 'Google Play Url'),
			'oblioClientId' => Yii::t('label', 'Oblio Client ID'),
			'oblioClientSecret' => Yii::t('label', 'Oblio Client Secret'),
			'textToSpeechServiceAccountFile' => Yii::t('label', 'Text To Speech Service Account File'),
            'translator' => Yii::t('label', 'Translator'),
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

		// A blank stored duration or size keeps the init() default instead of breaking the
		// arithmetic below.
		$numeric = ['userPasswordResetTokenExpiration', 'userLoginDuration', 'maxFileSize'];
		$defaults = $this->getAttributes($numeric);
		$this->setAttributes($this->getUnserializedValue('setting'));
		foreach ($numeric as $attribute) {
			if (!is_numeric($this->$attribute)) {
				$this->$attribute = $defaults[$attribute];
			}
		}
		$this->userPasswordResetTokenExpiration = $this->userPasswordResetTokenExpiration / 60;
		$this->userLoginDuration = $this->userLoginDuration / 24 / 3600;
		$this->maxFileSize = $this->maxFileSize / 1024;

		// Captured before the request can overwrite them; see the trait.
		$this->rememberSecrets();
	}

	/**
	 * Gets the appLogoUrl.
	 *
	 * @param bool $scheme
	 * @return string
	 */
	public function getAppLogoUrl($scheme = false)
	{
		return Url::to("@uploads/{$this->appLogo}", $scheme);
	}

	/**
	 * Gets the appLogoAltUrl.
	 *
	 * @param bool $scheme
	 * @return string
	 */
	public function getAppLogoAltUrl($scheme = false)
	{
		return Url::to("@uploads/{$this->appLogoAlt}", $scheme);
	}

	/**
	 * Saves the ScheduledTask model for exchange rate.
	 *
	 * @return bool
	 */
	protected function saveExchangeRateScheduledTask()
	{
		$scheduledTask = ScheduledTask::findOne([
			'resource' => __CLASS__ . '::' . __FUNCTION__,
			'resource_key' => $this->uuid,
			'deleted' => ScheduledTask::NO,
		]);
		if (!$scheduledTask) {
			$scheduledTask = new ScheduledTask();
		}
		$scheduledTask->cron_expression = ScheduledTask::createCronExpression($this->exchangeRateCycle, [
			'time' => $this->exchangeRateTime,
			ScheduledTask::CYCLE_DAY => $this->exchangeRateCycle == ScheduledTask::CYCLE_DAY ? $this->exchangeRatePeriod : $this->exchangeRateDay,
			ScheduledTask::CYCLE_MONTH => $this->exchangeRateCycle == ScheduledTask::CYCLE_MONTH ? $this->exchangeRatePeriod : $this->exchangeRateMonth,
		]);
		$scheduledTask->app_command = 'exchange-rate/sync';
		$scheduledTask->resource = __CLASS__ . '::' . __FUNCTION__;
		$scheduledTask->resource_key = $this->uuid;
		$scheduledTask->application = str_replace('app-', '', Yii::$app->id);
		$scheduledTask->type = ScheduledTask::TYPE_APP;
		$scheduledTask->status = ScheduledTask::STATUS_ACTIVE;

		return $scheduledTask->save();
	}

	/**
	 * Saves the ScheduledTask model for integration refresh.
	 *
	 * @return bool
	 */
	protected function saveIntegrationScheduledTask()
	{
		$scheduledTask = ScheduledTask::findOne([
			'resource' => __CLASS__ . '::' . __FUNCTION__,
			'resource_key' => $this->uuid,
			'deleted' => ScheduledTask::NO,
		]);
		if (!$scheduledTask) {
			$scheduledTask = new ScheduledTask();
		}
		$scheduledTask->cron_expression = '0 0 * * * *';
		$scheduledTask->app_command = 'integration/run';
		$scheduledTask->resource = __CLASS__ . '::' . __FUNCTION__;
		$scheduledTask->resource_key = $this->uuid;
		$scheduledTask->application = str_replace('app-', '', Yii::$app->id);
		$scheduledTask->type = ScheduledTask::TYPE_APP;
		$scheduledTask->status = ScheduledTask::STATUS_ACTIVE;

		return $scheduledTask->save();
	}

	/**
	 * Saves the ScheduledTask model for e-invoice verification.
	 *
	 * @return bool
	 */
	protected function saveEInvoiceUploadScheduledTask()
	{
		$scheduledTask = ScheduledTask::findOne([
			'resource' => __CLASS__ . '::' . __FUNCTION__,
			'resource_key' => $this->uuid,
			'deleted' => ScheduledTask::NO,
		]);
		if (!$scheduledTask) {
			$scheduledTask = new ScheduledTask();
		}
		$scheduledTask->cron_expression = '0/10 * * * * *';
		$scheduledTask->app_command = 'e-invoice/upload';
		$scheduledTask->resource = __CLASS__ . '::' . __FUNCTION__;
		$scheduledTask->resource_key = $this->uuid;
		$scheduledTask->application = str_replace('app-', '', Yii::$app->id);
		$scheduledTask->type = ScheduledTask::TYPE_APP;
		$scheduledTask->status = ScheduledTask::STATUS_ACTIVE;

		return $scheduledTask->save();
	}

	/**
	 * Saves the ScheduledTask model for e-invoice verification.
	 *
	 * @return bool
	 */
	protected function saveEInvoiceVerifyScheduledTask()
	{
		$scheduledTask = ScheduledTask::findOne([
			'resource' => __CLASS__ . '::' . __FUNCTION__,
			'resource_key' => $this->uuid,
			'deleted' => ScheduledTask::NO,
		]);
		if (!$scheduledTask) {
			$scheduledTask = new ScheduledTask();
		}
		$scheduledTask->cron_expression = '0/10 * * * * *';
		$scheduledTask->app_command = 'e-invoice/verify';
		$scheduledTask->resource = __CLASS__ . '::' . __FUNCTION__;
		$scheduledTask->resource_key = $this->uuid;
		$scheduledTask->application = str_replace('app-', '', Yii::$app->id);
		$scheduledTask->type = ScheduledTask::TYPE_APP;
		$scheduledTask->status = ScheduledTask::STATUS_ACTIVE;

		return $scheduledTask->save();
	}

	/**
	 * Saves the ScheduledTask model for backup.
	 *
	 * @return bool
	 */
	protected function saveBackupScheduledTask()
	{
		$scheduledTask = ScheduledTask::findOne([
			'resource' => __CLASS__ . '::' . __FUNCTION__,
			'resource_key' => $this->uuid,
			'deleted' => ScheduledTask::NO,
		]);
		if (!$scheduledTask) {
			$scheduledTask = new ScheduledTask();
		}

        $scheduledTask->cron_expression = ScheduledTask::createCronExpression($this->backupCycle, [
			'time' => $this->backupTime,
			ScheduledTask::CYCLE_DAY => $this->backupCycle == ScheduledTask::CYCLE_DAY ? $this->backupPeriod : $this->backupDay,
			ScheduledTask::CYCLE_MONTH => $this->backupCycle == ScheduledTask::CYCLE_MONTH ? $this->backupPeriod : $this->backupMonth,
		]);
		$scheduledTask->app_command = 'backup/run';
		$scheduledTask->resource = __CLASS__ . '::' . __FUNCTION__;
		$scheduledTask->resource_key = $this->uuid;
		$scheduledTask->application = str_replace('app-', '', Yii::$app->id);
		$scheduledTask->type = ScheduledTask::TYPE_APP;
		$scheduledTask->status = ScheduledTask::STATUS_ACTIVE;

		return $scheduledTask->save();
	}

    /**
     * Saves the ScheduledTask model for backup.
     *
     * @return bool
     */
    protected function saveBackupRemoveScheduledTask()
    {
        $scheduledTask = ScheduledTask::findOne([
            'resource' => __CLASS__ . '::' . __FUNCTION__,
            'resource_key' => $this->uuid,
            'deleted' => ScheduledTask::NO,
        ]);
        if (!$scheduledTask) {
            $scheduledTask = new ScheduledTask();
        }
        $scheduledTask->cron_expression = ScheduledTask::createCronExpression($this->backupCycle, [
            'time' => $this->backupTime,
            ScheduledTask::CYCLE_DAY => $this->backupCycle == ScheduledTask::CYCLE_DAY ? $this->backupPeriod : $this->backupDay,
            ScheduledTask::CYCLE_MONTH => $this->backupCycle == ScheduledTask::CYCLE_MONTH ? $this->backupPeriod : $this->backupMonth,
        ]);
        $scheduledTask->app_command = 'backup/remove';
        $scheduledTask->resource = __CLASS__ . '::' . __FUNCTION__;
        $scheduledTask->resource_key = $this->uuid;
        $scheduledTask->application = str_replace('app-', '', Yii::$app->id);
        $scheduledTask->type = ScheduledTask::TYPE_APP;
        $scheduledTask->status = ScheduledTask::STATUS_ACTIVE;

        return $scheduledTask->save();
    }

	/**
	 * Saves the files.
	 *
	 * @return bool
	 */
	protected function saveFiles()
	{
		try {
			$appLogo = UploadedFile::getInstance($this, 'appLogo');
			$appLogoAlt = UploadedFile::getInstance($this, 'appLogoAlt');
			if (!$appLogo && !$appLogoAlt) {
				return true;
			}

			if ($appLogo) {
				$fileName = "logo.{$appLogo->extension}";
				$filePath = Yii::getAlias("@uploads/{$fileName}");
				if (!$appLogo->saveAs($filePath)) {
					throw new \Exception();
				}
				$this->setSerializedValue('setting', [
					'appLogo' => $fileName,
				]);
			}
			if ($appLogoAlt) {
				$fileName = "logo-alt.{$appLogoAlt->extension}";
				$filePath = Yii::getAlias("@uploads/{$fileName}");
				if (!$appLogoAlt->saveAs($filePath)) {
					throw new \Exception();
				}
				$this->setSerializedValue('setting', [
					'appLogoAlt' => $fileName,
				]);
			}
			if (!$this->updateAttributes(['setting'])) {
				throw new \Exception();
			}
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Save Text To Speech Service Account File.
	 *
	 * @return bool
	 * @throws \yii\base\Exception
	 */
	protected function saveTextToSpeechServiceAccountFile()
	{
		if (!($file = UploadedFile::getInstance($this, 'textToSpeechServiceAccountFile'))) {
			return true;
		}
		// The attribute holds the stored file name, not the upload, so the rule in rules()
		// never sees it; check the upload itself before it is written to disk.
		$validator = new FileValidator([
			'extensions' => ['json'],
			'mimeTypes' => static::TTS_FILE_MIME_TYPES,
			// libmagic reports JSON as text/plain, whose extensions do not include json.
			'checkExtensionByMimeType' => false,
			'maxSize' => Yii::$app->settings->get('maxFileSize'),
		]);
		if (!$validator->validate($file, $error)) {
			$this->addError('textToSpeechServiceAccountFile', $error);
			return false;
		}
		try {
			$dirPath  = Yii::getAlias('@runtime/tts');
			$fileName = $file->name;
			FileHelper::createDirectory($dirPath);
			if (!$file->saveAs("{$dirPath}/{$fileName}")) {
				throw new \Exception();
			}
			$this->textToSpeechServiceAccountFile = $fileName;
			$this->setSerializedValue('setting', [
				'textToSpeechServiceAccountFile' => $fileName,
			]);
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Saves the setting.
	 *
	 * @return bool|\yii\db\ActiveRecord
	 */
	public function saveModel()
	{
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			$this->name = 'general';
			$this->type = static::TYPE_APP;
			$this->status = static::STATUS_ACTIVE;

//            $languages = ArrayHelper::getColumn(Yii::$app->translate->discover()['data']['languages'], 'language');
//            $defaultLanguage = Language::findOne(['language_id' => Yii::$app->language]);
//
//            foreach (Language::findAllLanguages() as $language) {
//
//                if (!empty($this->translator) && $language->language_id != Yii::$app->language && in_array($defaultLanguage->language, $languages) && in_array($language->language, $languages)) {
//                    $source = $defaultLanguage->language;
//                    $target = $language->language;
//                }
//                $this->appDescription[$language->language_id] = $this->appDescription[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->appDescription[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, $this->appDescription[Yii::$app->language])['data']['translations'][0]['translatedText'] : null);
//            }

			if (!$this->saveTextToSpeechServiceAccountFile()) {
				throw new \Exception();
			}

			$this->setSerializedValue('setting', [
				'hostInfo' => $this->hostInfo,
				'appName' => $this->appName,
				'appDescription' => $this->appDescription,
				'itemsPerPage' => $this->itemsPerPage,
				'defaultCountry' => $this->defaultCountry,
				'defaultLanguage' => $this->defaultLanguage,
				'timeZone' => $this->timeZone,
				'timeFormat' => $this->timeFormat,
				'dateFormat' => $this->dateFormat,
				'datetimeFormat' => $this->datetimeFormat,
				'currencyCode' => $this->currencyCode,
				'userAccountActivation' => $this->userAccountActivation,
				'userPasswordResetTokenExpiration' => $this->userPasswordResetTokenExpiration * 60,
				'userLoginDuration' => $this->userLoginDuration * 24 * 3600,
				'maxFileSize' => $this->maxFileSize * 1024,
				'enableEventLogs' => $this->enableEventLogs,
				'enableSoftDelete' => $this->enableSoftDelete,
				'enableDocumentation' => $this->enableDocumentation,
				'googleMapKey' => $this->googleMapKey,
                'reCaptchaSiteKey' => $this->reCaptchaSiteKey,
                'reCaptchaSecretKey' => $this->reCaptchaSecretKey,
                'vatRate' => $this->vatRate,
				'exchangeRatePeriod' => $this->exchangeRatePeriod,
				'exchangeRateCycle' => $this->exchangeRateCycle,
				'exchangeRateMonth' => $this->exchangeRateMonth,
				'exchangeRateDay' => $this->exchangeRateDay,
				'exchangeRateTime' => $this->exchangeRateTime,
				'backupPeriod' => $this->backupPeriod,
				'backupCycle' => $this->backupCycle,
				'backupMonth' => $this->backupMonth,
				'backupDay' => $this->backupDay,
				'backupTime' => $this->backupTime,
				'enableTwoFactorAuthentication' => $this->enableTwoFactorAuthentication,
				'twoFactorAuthenticationDuration' => $this->twoFactorAuthenticationDuration,
				'appleStoreUrl' => $this->appleStoreUrl,
				'googlePlayUrl' => $this->googlePlayUrl,
				'oblioClientId' => $this->oblioClientId,
				'oblioClientSecret' => $this->oblioClientSecret,
			]);

			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->saveBackupScheduledTask()) {
				throw new \Exception();
			}
            if (!$this->saveBackupRemoveScheduledTask()) {
                throw new \Exception();
            }
			if (!$this->saveFiles()) {
				throw new \Exception();
			}
			$dbTransaction->commit();
			return $this;
		} catch (\Exception $e) {
			$dbTransaction->rollBack();
			return null;
		}
	}
}
