<?php

namespace common\models;

use common\helpers\CaptchaHelper;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

/**
 * Login form
 */
class AuthenticateByTokenForm extends Model
{
	public $auth_key;

	/**
	 * @var bool Flag that indicates if login credentials should be remembered for a period of time.
	 */
	public $rememberMe = true;

	/**
	 * @var string The honeypot field.
	 */
	public $workEmail;

    /**
     * @var string The honeypot field.
     */
    public $captchaResponse;

	/**
	 * @var User The user model.
	 */
	private $_user;

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['auth_key'], 'required'],
			['rememberMe', 'boolean'],
            ['workEmail', 'safe'],
            ['captchaResponse', 'safe'],
        ];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
		]);
	}

	/**
	 * Finds user by email or phone.
	 *
	 * @return User|null
	 */
	protected function getUser()
	{
		if ($this->_user === null) {
			$this->_user = User::findIdentityByAccessToken($this->auth_key);
		}
		return $this->_user;
	}

	/**
	 * Logs in a user using the provided username and password.
	 *
	 * @return bool whether the user is logged in successfully
	 */
	public function login()
	{
        if (!empty($this->workEmail)) {
            return false;
        }
        if (!CaptchaHelper::verify($this->captchaResponse)) {
            $this->addError('captchaResponse', Yii::t('common', 'The captcha verification failed. Please try again.'));
            return false;
        }
		if ($this->validate() && $this->getUser()) {
			return Yii::$app->user->login($this->getUser(), $this->rememberMe ? (int) Yii::$app->settings->get('userLoginDuration') : 0);
		} else {
			return false;
		}
	}
}
