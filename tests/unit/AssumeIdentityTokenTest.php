<?php

namespace tests\unit;

use common\models\AssumeIdentityForm;
use tests\WebTestCase;
use Yii;

/**
 * The "login as" token the frontend assume-identity action accepts.
 *
 * It used to be Yii::$app->security->maskToken() of the user id, which is an encoding,
 * not a signature: anyone could mask any id and, with no captcha configured, log in as
 * that account. These cases hold the replacement to being signed, expiring and off when
 * no key is configured.
 */
class AssumeIdentityTokenTest extends WebTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		Yii::$app->params['assumeIdentity.key'] = 'test-assume-identity-key';
	}

	public function testAGeneratedTokenResolvesToItsUser()
	{
		$token = AssumeIdentityForm::generateToken('42');

		$this->assertNotEmpty($token);
		$this->assertSame('42', AssumeIdentityForm::resolveToken($token));
	}

	public function testTheOldMaskedIdIsRejected()
	{
		$this->assertNull(
			AssumeIdentityForm::resolveToken(Yii::$app->security->maskToken('42')),
			'a masked id can be produced by anyone and must not authorise a login'
		);
	}

	public function testATamperedTokenIsRejected()
	{
		$token = AssumeIdentityForm::generateToken('42');
		// Same signature, different user id in the payload.
		$forged = substr($token, 0, -strlen('42.' . (time() + AssumeIdentityForm::TOKEN_TTL)))
			. '43.' . (time() + AssumeIdentityForm::TOKEN_TTL);

		$this->assertNull(AssumeIdentityForm::resolveToken($forged));
	}

	public function testATokenSignedWithAnotherKeyIsRejected()
	{
		$token = Yii::$app->security->hashData('42.' . (time() + 60), 'some-other-key');

		$this->assertNull(AssumeIdentityForm::resolveToken($token));
	}

	public function testAnExpiredTokenIsRejected()
	{
		$token = Yii::$app->security->hashData('42.' . (time() - 1), 'test-assume-identity-key');

		$this->assertNull(AssumeIdentityForm::resolveToken($token));
	}

	public function testWithoutAKeyTheFeatureIsOff()
	{
		$token = AssumeIdentityForm::generateToken('42');
		Yii::$app->params['assumeIdentity.key'] = '';

		$this->assertNull(AssumeIdentityForm::generateToken('42'));
		$this->assertNull(AssumeIdentityForm::resolveToken($token));
	}

	/**
	 * @dataProvider junkProvider
	 * @param mixed $token
	 */
	public function testJunkIsRejected($token)
	{
		$this->assertNull(AssumeIdentityForm::resolveToken($token));
	}

	/**
	 * @return array
	 */
	public function junkProvider()
	{
		return [
			'null' => [null],
			'empty' => [''],
			'array' => [['42']],
			'plain id' => ['42'],
		];
	}

	public function testATokenForARealUserIdIsPrintableAndResolves()
	{
		// User ids are UUIDs stored as BINARY(16); the backend view passes the canonical
		// string, so the token can be shown on the page and pasted into a URL.
		$uuid = '019f0000-0000-7000-8000-000000000042';
		$token = AssumeIdentityForm::generateToken($uuid);

		$this->assertMatchesRegularExpression('/^[!-~]+$/', $token);
		$this->assertSame($uuid, AssumeIdentityForm::resolveToken($token));
	}
}
