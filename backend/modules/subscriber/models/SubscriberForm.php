<?php

namespace backend\modules\subscriber\models;

use borales\extensions\phoneInput\PhoneInputValidator;
use common\models\Page;
use common\models\Subscriber;
use common\models\Template;
use common\models\User;
use tws\helpers\FileHelper;
use tws\helpers\StringHelper;
use tws\helpers\Url;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use yii\web\UploadedFile;

class SubscriberForm extends Subscriber
{
	/**
	 * @var UploadedFile The imageFile.
	 */
	public $imageFile;

	/**
	 * @var string The first name of the user account.
	 */
	public $first_name;

	/**
	 * @var string The middle name of the user account.
	 */
	public $middle_name;

	/**
	 * @var string The last name of the user account.
	 */
	public $last_name;

	/**
	 * @var string The gender of the user account.
	 */
	public $gender;

	/**
	 * @var string The email address of the user account.
	 */
	public $email;

	/**
	 * @var string The phone number of the user account.
	 */
	public $phone;

	/**
	 * @var bool Flag that indicates if the associated User model is a new record.
	 */
	private $_isNewUserRecord = true;


	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->status = static::STATUS_ACTIVE;
		if (Yii::$app->settings->get('userAccountActivation') == User::ACCOUNT_ACTIVATION_CONFIRMATION) {
			$this->status = static::STATUS_INACTIVE;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function attributes()
	{
		return array_merge(parent::attributes(), ['first_name', 'middle_name', 'last_name', 'gender', 'email', 'phone']);
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return ArrayHelper::merge(parent::rules(), [
			[['first_name', 'last_name', 'email', 'phone', 'gender'], 'required'],
			[['first_name', 'middle_name', 'last_name', 'email', 'phone'], 'string', 'max' => 255],
			[['first_name', 'middle_name', 'last_name', 'email', 'phone'], 'trim'],
			['phone', PhoneInputValidator::class, 'skipOnEmpty' => true],
			[['email'], 'email'],
			[['gender'], 'integer'],
			[['gender'], 'in', 'range' => [static::GENDER_MALE, static::GENDER_FEMALE]],
			[['imageFile'], 'file', 'extensions' => Yii::$app->params['image.extensions'], 'mimeTypes' => Yii::$app->params['image.mimeTypes'], 'maxSize' => Yii::$app->settings->get('maxFileSize'), 'maxFiles' => 1, 'skipOnEmpty' => true],
		]);
	}

	/**
	 * {@inheritdoc}
	 *
	 * The file rule only bites if the attribute holds the upload when validation runs;
	 * saveUserFiles() would otherwise take it straight from the request unchecked.
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
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'imageFile' => Yii::t('label', 'Image'),
			'first_name' => Yii::t('label', 'First Name'),
			'middle_name' => Yii::t('label', 'Middle Name'),
			'last_name' => Yii::t('label', 'Last Name'),
			'gender' => Yii::t('label', 'Gender'),
			'email' => Yii::t('label', 'Email'),
			'phone' => Yii::t('label', 'Phone'),
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function afterFind()
	{
		parent::afterFind();

		$this->first_name = $this->user->first_name;
		$this->middle_name = $this->user->middle_name;
		$this->last_name = $this->user->last_name;
		$this->gender = $this->user->gender;
		$this->email = $this->user->email;
		$this->phone = $this->user->phone;

		$this->_isNewUserRecord = false;
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return Model::scenarios();
	}

	/**
	 * Finds a User model by its username related attributes.
	 *
	 * @param array $attributes
	 * @return array|\yii\db\ActiveRecord|User|null
	 */
	public static function findUserByAttributes($attributes)
	{
		return User::find()
			->andWhere([
				'OR',
				['=', 'username', $attributes['username']],
				['=', 'email', $attributes['email']],
				['=', 'phone', $attributes['phone']],
			])
			->one();
	}

	/**
	 * Saves the User model.
	 *
	 * @return bool
	 */
	public function saveUser()
	{
		try {
			$user = $this->user;

			if ($existingUser = static::findUserByAttributes($this->attributes)) {
				if ($this->_isNewUserRecord) {
					// Switch to an existing user and mark the new record flag as false
					$user = $existingUser;
					$this->_isNewUserRecord = false;
				} else {
					// Make sure that the existent user is the currently updated user and the email and phone are unique
					if ($existingUser->id != $user->id) {
						if ($this->email != $user->email) {
							$this->addError('email', Yii::t('yii', '{attribute} "{value}" has already been taken.', [
								'attribute' => $this->getAttributeLabel('email'),
								'value' => $this->email,
							]));
						}
						if ($this->phone != $user->phone) {
							$this->addError('phone', Yii::t('yii', '{attribute} "{value}" has already been taken.', [
								'attribute' => $this->getAttributeLabel('phone'),
								'value' => $this->phone,
							]));
						}
						throw new \Exception();
					}
				}
				// Make the customer active since the user already exists and its account is activated
				if (empty($existingUser->signup_token)) {
					$this->status = static::STATUS_ACTIVE;
				}
			}

			if ($this->_isNewUserRecord) {
				$user = new User();
				$user->setPassword(Yii::$app->security->generateRandomString(8));
				$user->generateAuthKey();
				$user->status = User::STATUS_ACTIVE;
				if (Yii::$app->settings->get('userAccountActivation') == User::ACCOUNT_ACTIVATION_CONFIRMATION) {
					$user->signup_token = User::generateSignupToken();
					$user->status = User::STATUS_INACTIVE;
				}
			}
			$user->first_name = $this->first_name;
			$user->middle_name = $this->middle_name;
			$user->last_name = $this->last_name;
			$user->gender = $this->gender;
			$user->email = $this->email;
			$user->phone = $this->phone;
			if (!$user->save()) {
				$this->addErrors($user->getErrors());
				throw new \Exception();
			}

			// Preserve some attributes from existing user
			$this->user_id = $user->id;

			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Saves the user files.
	 *
	 * @return bool
	 */
	protected function saveUserFiles()
	{
		try {
			if (!($file = UploadedFile::getInstance($this, 'imageFile'))) {
				return true;
			}
			$user = $this->user;

			$dirPath = Yii::getAlias("@uploads/user/{$user->uuid}");
			$oldFilePath = "{$dirPath}/{$user->oldAttributes['image']}";
			$fileName = StringHelper::truncate(implode('_', array_filter([
				Inflector::slug($user->fullName),
				Yii::$app->security->generateRandomString(8),
			])), 255 - (mb_strlen($file->extension) + 1), '') . ".{$file->extension}";
			$filePath = "{$dirPath}/{$fileName}";

			FileHelper::createDirectory($dirPath);
			if (!$file->saveAs($filePath)) {
				throw new \Exception();
			}
			if (!$user->updateAttributes(['image' => $fileName])) {
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
	 * Sends welcome email.
	 *
	 * @return bool
	 */
	public function sendWelcomeEmail()
	{
		try {
			$template = Template::findDefaultByTypeAndVariant(Template::TYPE_EMAIL, Template::EMAIL_VARIANT_WELCOME);
			if (!$template || !($templateTranslation = $template->getTranslation())) {
				throw new \Exception();
			}
			$user = $this->user;
			$shortCodeValues = [
				'{{APP_NAME}}' => Yii::$app->name,
				'{{APP_URL}}' => Url::to(['/site/index'], true, '@frontend'),
				'{{APP_LOGO_URL}}' => Url::to('@uploads/' . Yii::$app->settings->get('appLogo'), true) ?: Url::to('@frontend/web/img/logo.png', true),
				'{{APP_LOGO_ALT_URL}}' => Url::to('@uploads/' . Yii::$app->settings->get('appLogoAlt'), true) ?: Url::to('@frontend/web/img/logo-alt.png', true),
				'{{FIRST_NAME}}' => $user->first_name,
				'{{MIDDLE_NAME}}' => $user->middle_name,
				'{{LAST_NAME}}' => $user->last_name,
				'{{EMAIL}}' => $user->email,
				'{{PHONE}}' => $user->phone,
			];

			return Yii::$app->mailer->compose()
				->setTo([$user->email => $user->fullName])
				->setSubject(strtr($templateTranslation->subject, $shortCodeValues))
				->setHtmlBody(strtr($templateTranslation->content, $shortCodeValues))
				->send();
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Sends account activation email.
	 *
	 * @return bool
	 */
	public function sendActivationEmail()
	{
		$user = $this->user;
		$template = Template::findDefaultByTypeAndVariant(Template::TYPE_EMAIL, Template::EMAIL_VARIANT_ACCOUNT_ACTIVATION);
		if (!$template) {
			return false;
		}
		if (!($templateTranslation = $template->getTranslation()) || !($page = Page::findPageByRoute(['/site/activate'])->getTranslation())) {
			$templateTranslation = $template->getTranslation(Yii::$app->settings->get('defaultLanguage'));
			$page = Page::findPageByRoute(['/site/activate'])->getTranslation(Yii::$app->settings->get('defaultLanguage'));
			$language = mb_substr(Yii::$app->settings->get('defaultLanguage'), 0, 2);
			if (empty($templateTranslation) || empty($page)) {
				return false;
			}
		} else {
			$language = mb_substr(Yii::$app->language, 0, 2);
		}
		$appUrl = Yii::$app->request->hostInfo;
		$activationPageUrl = implode('/', array_filter([
			$appUrl,
			$language,
			$page->slug
		]));
		$activationUrl = implode('', [
			$activationPageUrl,
			'?token=' . $user->getSignupToken()
		]);
		$shortCodeValues = [
			'{{APP_NAME}}' => Yii::$app->name,
			'{{APP_URL}}' => Url::to(['/site/index'], true, '@frontend'),
			'{{APP_LOGO_URL}}' => Url::to('@uploads/' . Yii::$app->settings->get('appLogo'), true) ?: Url::to('@frontend/web/img/logo.png', true),
			'{{APP_LOGO_ALT_URL}}' => Url::to('@uploads/' . Yii::$app->settings->get('appLogoAlt'), true) ?: Url::to('@frontend/web/img/logo-alt.png', true),
			'{{FIRST_NAME}}' => $user->first_name,
			'{{MIDDLE_NAME}}' => $user->middle_name,
			'{{LAST_NAME}}' => $user->last_name,
			'{{ACCOUNT_ACTIVATION_CODE}}' => $user->getSignupToken(),
			'{{ACCOUNT_ACTIVATION_PAGE_URL}}' => $activationPageUrl,
			'{{ACCOUNT_ACTIVATION_URL}}' => $activationUrl,
		];

		return Yii::$app->mailer->compose()
			->setTo([$this->email => $user->fullName])
			->setSubject(strtr($templateTranslation->subject, $shortCodeValues))
			->setHtmlBody(strtr($templateTranslation->content, $shortCodeValues))
			->send();
	}

	/**
	 * @inheritdoc
	 */
	public function save($runValidation = true, $attributeNames = null)
	{
		$isNewRecord = $this->getIsNewRecord();
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			if (!$this->saveUser()) {
				throw new \Exception();
			}
			if (!$this->code) {
				$this->code = static::generateUniqueCode();
			}
			if (!parent::save($runValidation, $attributeNames)) {
				throw new \Exception();
			}
			if (!$this->saveUserFiles()) {
				throw new \Exception();
			}
			if ($this->_isNewUserRecord) {
				if (Yii::$app->settings->get('userAccountActivation') == User::ACCOUNT_ACTIVATION_CONFIRMATION) {
					$this->sendWelcomeEmail();
					$emailSent = $this->sendActivationEmail();
					if (!$emailSent) {
						$this->addError('', Yii::t('common', 'Cannot send the account confirmation message.'));
						throw new \Exception();
					}
				}
			}

			$dbTransaction->commit();
			return true;
		} catch(\Exception $e) {
			if ($isNewRecord) {
				$this->setIsNewRecord(true);
				$this->id = null;
			}
			$dbTransaction->rollBack();
			return false;
		}
	}
}
