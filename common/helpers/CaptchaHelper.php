<?php

namespace common\helpers;

use Yii;
use yii\httpclient\Client;

/**
 * reCAPTCHA verification for the public forms.
 *
 * The check this replaces only ran when a token was present, so omitting the field
 * skipped it entirely — the protection was there for browsers and absent for the
 * scripted submissions it exists to stop. [[verify()]] fails closed instead: once a
 * site key is configured, a request without a valid token does not pass.
 */
class CaptchaHelper
{
	const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

	/**
	 * How long to wait on Google before giving up, in seconds.
	 */
	const TIMEOUT = 5;

	/**
	 * Whether reCAPTCHA is configured for this installation.
	 *
	 * @return bool
	 */
	public static function isEnabled()
	{
		return (bool) Yii::$app->settings->get('reCaptchaSiteKey', 'general');
	}

	/**
	 * Verifies a reCAPTCHA response token.
	 *
	 * @param string|null $response the token the form submitted
	 * @return bool true when reCAPTCHA is not configured, or the token checks out
	 */
	public static function verify($response)
	{
		if (!static::isEnabled()) {
			return true;
		}
		if (empty($response)) {
			return false;
		}

		try {
			$result = (new Client())
				->createRequest()
				->setMethod('POST')
				->setUrl(static::VERIFY_URL)
				->setData([
					'secret' => Yii::$app->settings->get('reCaptchaSecretKey', 'general'),
					'response' => $response,
					'remoteip' => Yii::$app->request->userIP,
				])
				->setOptions(['timeout' => static::TIMEOUT])
				->send();

			if (!$result->isOk) {
				return false;
			}

			return !empty($result->data['success']);
		} catch (\Exception $e) {
			// A verifier we cannot reach is not a reason to let the submission through.
			Yii::warning('reCAPTCHA verification failed: ' . $e->getMessage(), __METHOD__);
			return false;
		}
	}
}
