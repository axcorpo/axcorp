<?php

namespace common\models;

use common\helpers\CaptchaHelper;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

/**
 * Login form
 */
class AssumeIdentityForm extends Model
{
	/**
	 * @var int Seconds an impersonation token stays valid after it is generated.
	 */
	const TOKEN_TTL = 900;

	public $id;

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
			[['id'], 'required'],
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
	 * Builds a signed, expiring impersonation token for a user id — the "secret" shown
	 * in the backend user view. Unlike maskToken() it cannot be produced from a known id:
	 * it is HMAC-signed with a key only the server holds, and it expires after
	 * {@see TOKEN_TTL}, bounding replay.
	 *
	 * @param int|string $id id of the user to impersonate
	 * @return string|null null when no signing key is configured
	 */
	public static function generateToken($id)
	{
		if (($key = static::tokenKey()) === null) {
			return null;
		}
		$payload = $id . '.' . (time() + static::TOKEN_TTL);
		return Yii::$app->security->hashData($payload, $key);
	}

	/**
	 * Validates an impersonation token and returns the user id it authorises, or null
	 * if the signature is invalid (forged/tampered) or the token has expired.
	 *
	 * @param string $token
	 * @return string|null
	 */
	public static function resolveToken($token)
	{
		if (!is_string($token) || $token === '' || ($key = static::tokenKey()) === null) {
			return null;
		}
		$payload = Yii::$app->security->validateData($token, $key);
		if ($payload === false || strpos($payload, '.') === false) {
			return null;
		}
		[$id, $expiry] = explode('.', $payload, 2);
		if ($id === '' || !ctype_digit($expiry) || (int) $expiry < time()) {
			return null;
		}
		return $id;
	}

	/**
	 * The signing key. Backend signs and frontend verifies, and the two apps have
	 * different cookieValidationKeys, so it is a shared param (params-local.php).
	 * Without one the feature is off rather than signed with a guessable key.
	 *
	 * @return string|null
	 */
	protected static function tokenKey()
	{
		$key = Yii::$app->params['assumeIdentity.key'] ?? '';
		return is_string($key) && $key !== '' ? $key : null;
	}

	/**
	 * Finds user by email or phone.
	 *
	 * @return User|null
	 */
	protected function getUser()
	{
		if ($this->_user === null) {
			$this->_user = User::findIdentity($this->id);
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
		if ($this->validate() && $this->getUser() !== null) {
			return Yii::$app->user->login($this->getUser(), $this->rememberMe ? (int) Yii::$app->settings->get('userLoginDuration') : 0);
		} else {
			return false;
		}
	}
}
