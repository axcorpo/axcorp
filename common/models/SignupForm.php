<?php

namespace common\models;

use common\components\AffiliateTracker;
use common\validators\PasswordValidator;
use common\helpers\CaptchaHelper;
use borales\extensions\phoneInput\PhoneInputValidator;
use Yii;
use yii\base\Model;
use tws\helpers\Url;

/**
 * Signup form.
 *
 * Creates the User record and its Subscriber row. The company, subscription
 * and workspace used to be provisioned here as well; they are now left to the
 * flows that actually collect that data.
 */
class SignupForm extends Model
{
    /**
     * @var string The email address.
     */
    public $email;

    /**
     * @var string The phone number.
     */
    public $phone;

    /**
     * @var string The password.
     */
    public $password;

    /**
     * @var string The password confirmation.
     */
    public $password_confirm;

    /**
     * @var string The first name.
     */
    public $first_name;

    /**
     * @var string The middle name.
     */
    public $middle_name;

    /**
     * @var string The last name.
     */
    public $last_name;

    /**
     * @var bool Accept TOS flag.
     */
    public $acceptTerms;

    /**
     * @var string The honeypot field.
     */
    public $workEmail;

    /**
     * @var string The reCAPTCHA response token.
     */
    public $captchaResponse;

    /**
     * @var User The User model.
     */
    private $_user;

    /**
     * @var Subscriber The Subscriber model.
     */
    private $_subscriber;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['first_name', 'last_name', 'email', 'phone', 'password', 'password_confirm', 'acceptTerms'], 'required'],
            [['first_name', 'middle_name', 'last_name', 'email', 'phone'], 'string', 'max' => 255],
            [['first_name', 'middle_name', 'last_name', 'email', 'phone'], 'trim'],
            ['phone', PhoneInputValidator::class, 'skipOnEmpty' => true],
            [['password'], PasswordValidator::class],
            ['password_confirm', 'compare', 'compareAttribute' => 'password', 'message' => Yii::t('common', 'Passwords don\'t match.')],
            [['email'], 'unique', 'targetClass' => 'common\models\User'],
            ['acceptTerms', 'boolean'],
            ['workEmail', 'safe'],
            ['captchaResponse', 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'first_name' => Yii::t('label', 'First Name'),
            'middle_name' => Yii::t('label', 'Middle Name'),
            'last_name' => Yii::t('label', 'Last Name'),
            'email' => Yii::t('label', 'Email'),
            'phone' => Yii::t('label', 'Phone'),
            'password' => Yii::t('label', 'Password'),
            'password_confirm' => Yii::t('label', 'Confirm Password'),
            'acceptTerms' => Yii::t('common', 'Terms and Conditions'),
        ];
    }

    /**
     * Gets the User model.
     *
     * @return User
     */
    public function getUser()
    {
        return $this->_user;
    }

    /**
     * Saves the Subscriber model.
     *
     * @return bool
     */
    protected function saveSubscriber()
    {
        try {
            $user = $this->getUser();

            $subscriber = new Subscriber();
            $subscriber->user_id = $user->id;
            $subscriber->code = Subscriber::generateUniqueCode();
            $subscriber->status = Subscriber::STATUS_ACTIVE;
            if (Yii::$app->settings->get('userAccountActivation') == User::ACCOUNT_ACTIVATION_CONFIRMATION) {
                $subscriber->status = Subscriber::STATUS_INACTIVE;
            }
            // The affiliate who brought the visitor (?aff=), set once at creation.
            AffiliateTracker::assign($subscriber);
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
     * Sends account activation email.
     *
     * @return bool
     */
    public function sendActivationEmail()
    {
        $user = $this->getUser();
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
     * Signs user up.
     *
     * @return bool
     */
    public function signup()
    {
        if (!empty($this->workEmail)) {
            return false;
        }
        if (!CaptchaHelper::verify($this->captchaResponse)) {
            $this->addError('captchaResponse', Yii::t('common', 'The captcha verification failed. Please try again.'));
            return false;
        }
        if (!$this->validate()) {
            return false;
        }

        $dbTransaction = Yii::$app->getDb()->beginTransaction();
        try {
            $user = new User();
            $user->email = $this->email;
            $user->phone = $this->phone;
            $user->first_name = $this->first_name;
            $user->middle_name = $this->middle_name;
            $user->last_name = $this->last_name;
            $user->setPassword($this->password);
            $user->generateAuthKey();
            $user->status = User::STATUS_ACTIVE;
            if (Yii::$app->settings->get('userAccountActivation') == User::ACCOUNT_ACTIVATION_CONFIRMATION) {
                $user->signup_token = User::generateSignupToken();
                $user->status = User::STATUS_INACTIVE;
            }
            if (!$user->save(false)) {
                $this->addErrors($user->getErrors());
                throw new \Exception();
            }
            $this->_user = $user;

            if (!$this->saveSubscriber()) {
                throw new \Exception();
            }

            $dbTransaction->commit();
            return true;
        } catch (\Exception $e) {
            $dbTransaction->rollBack();
            return false;
        }
    }
}
