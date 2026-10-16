<?php

namespace tests\unit;

use common\helpers\CaptchaHelper;
use tests\WebTestCase;
use Yii;

/**
 * The half of the captcha check that was missing.
 *
 * The old code verified the token inside `if (!empty($this->captchaResponse))`, so a
 * client that simply left the field out skipped verification entirely — the control
 * held for browsers and was absent for the scripted submissions it exists to stop.
 * These cases pin the fail-closed behaviour; the network round trip itself is not
 * exercised here, only the decisions taken around it.
 */
class CaptchaHelperTest extends WebTestCase
{
	public function testVerificationIsSkippedWhenNoSiteKeyIsConfigured()
	{
		Yii::$app->settings->set('reCaptchaSiteKey', '', 'general');

		$this->assertFalse(CaptchaHelper::isEnabled());
		$this->assertTrue(
			CaptchaHelper::verify(null),
			'an installation without reCAPTCHA configured must keep working'
		);
	}

	/**
	 * @dataProvider missingTokenProvider
	 * @param mixed $token
	 */
	public function testAMissingTokenIsRejectedOnceConfigured($token)
	{
		Yii::$app->settings->set('reCaptchaSiteKey', 'test-site-key', 'general');
		Yii::$app->settings->set('reCaptchaSecretKey', 'test-secret-key', 'general');

		$this->assertFalse(
			CaptchaHelper::verify($token),
			'omitting the field used to skip the check altogether'
		);
	}

	/**
	 * @return array
	 */
	public function missingTokenProvider()
	{
		return [
			'absent' => [null],
			'empty string' => [''],
			'zero' => ['0'],
		];
	}
}
