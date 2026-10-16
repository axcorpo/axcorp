<?php

namespace tests\unit;

use common\traits\SecretSettingTrait;
use tests\WebTestCase;
use yii\base\Model;

/**
 * A stand-in for the settings forms, carrying one secret and one ordinary field.
 */
class SecretSettingFixture extends Model
{
	use SecretSettingTrait;

	public $host;
	public $password;

	public function rules()
	{
		return [
			[['host', 'password'], 'required'],
			[['host', 'password'], 'string'],
		];
	}

	public function secretAttributes()
	{
		return ['password'];
	}
}

/**
 * Keeping stored secrets out of the rendered form without losing them on save.
 *
 * The views now render these fields empty so the SMTP password and the API keys stop
 * travelling to the browser in the markup. On its own that would blank the stored value
 * the first time somebody saved the page for an unrelated reason, so an empty submission
 * has to mean "leave it as it was" — these cases hold the two halves together.
 */
class SecretSettingTest extends WebTestCase
{
	/**
	 * @param string $stored
	 * @return SecretSettingFixture
	 */
	private function withStoredSecret($stored = 'the-smtp-password')
	{
		$model = new SecretSettingFixture();
		$model->host = 'smtp.example.test';
		$model->password = $stored;
		// What afterFind() does once the stored values are populated.
		$model->rememberSecrets();

		return $model;
	}

	public function testAnEmptySubmissionKeepsTheStoredSecret()
	{
		$model = $this->withStoredSecret();
		$model->password = '';

		$this->assertTrue($model->validate());
		$this->assertSame('the-smtp-password', $model->password);
	}

	public function testAnEmptySubmissionDoesNotTripTheRequiredRule()
	{
		$model = $this->withStoredSecret();
		$model->password = '';

		$model->validate();

		$this->assertFalse(
			$model->hasErrors('password'),
			'the restore runs before validation, so required does not fire on an untouched field'
		);
	}

	public function testANewValueReplacesTheStoredOne()
	{
		$model = $this->withStoredSecret();
		$model->password = 'a-new-password';

		$this->assertTrue($model->validate());
		$this->assertSame('a-new-password', $model->password);
	}

	public function testOrdinaryFieldsAreLeftAlone()
	{
		$model = $this->withStoredSecret();
		$model->host = '';

		$this->assertFalse($model->validate(), 'host is required and was actually cleared');
		$this->assertTrue($model->hasErrors('host'));
	}

	public function testNothingIsRestoredWhenThereWasNoStoredSecret()
	{
		$model = new SecretSettingFixture();
		$model->host = 'smtp.example.test';
		$model->password = null;
		$model->rememberSecrets();

		$model->password = '';
		$model->validate();

		$this->assertTrue(
			$model->hasErrors('password'),
			'a first-time save with an empty secret is a genuine validation failure'
		);
	}

	public function testTheStoredSecretIsNotAnAttributeSoLoadCannotReachIt()
	{
		$model = $this->withStoredSecret();

		$this->assertNotContains(
			'_storedSecrets',
			$model->attributes(),
			'the captured copy must be out of reach of load()'
		);
	}
}
