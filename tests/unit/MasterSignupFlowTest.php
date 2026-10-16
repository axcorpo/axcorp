<?php

namespace tests\unit;

use common\models\SignupForm;
use common\models\Subscriber;
use common\models\User;
use tests\DatabaseTestCase;
use tests\support\FakeSettings;
use Yii;
use yii\db\Query;

/**
 * Integration coverage for the public signup flow.
 *
 * SignupForm::signup() creates the User record and its Subscriber row, in one
 * transaction. No company, subscription or workspace is provisioned at signup.
 */
class MasterSignupFlowTest extends DatabaseTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		// signup() consults the settings component (captcha keys, account
		// activation mode); the empty fake keeps both paths off.
		Yii::$app->set('settings', new FakeSettings());
	}

	protected function tearDown(): void
	{
		Yii::$app->clear('settings');
		parent::tearDown();
	}

	protected function makeForm(array $overrides = [])
	{
		return new SignupForm(array_merge([
			'first_name' => 'Ana',
			'last_name' => 'Pop',
			'email' => 'ana@test.local',
			'phone' => '+40721234567',
			'password' => 'Secret-123!x',
			'password_confirm' => 'Secret-123!x',
			'acceptTerms' => 1,
		], $overrides));
	}

	public function testSignupCreatesAnActiveUserWithASubscriber()
	{
		$form = $this->makeForm();
		$this->assertTrue($form->signup(), print_r($form->getErrors(), true));

		$user = User::findOne(['email' => 'ana@test.local']);
		$this->assertNotNull($user);
		$this->assertSame($user->id, $form->getUser()->id);
		$this->assertSame(User::STATUS_ACTIVE, (int) $user->status);
		$this->assertSame('Ana', $user->first_name);
		$this->assertSame('Pop', $user->last_name);
		$this->assertNotEmpty($user->auth_key);
		$this->assertTrue(Yii::$app->security->validatePassword('Secret-123!x', $user->password_hash));

		// Automatic activation: the subscriber is usable straight away.
		$subscriber = Subscriber::findOne(['user_id' => $user->id]);
		$this->assertNotNull($subscriber);
		$this->assertSame(Subscriber::STATUS_ACTIVE, (int) $subscriber->status);
		$this->assertNotEmpty($subscriber->code);

		foreach (['company', 'subscription', 'workspace', 'workspace_has_user'] as $table) {
			$this->assertSame(0, (int) (new Query())->from($table)->count(), "signup must not write to {$table}");
		}
	}

	public function testConfirmationModeLeavesTheUserAndSubscriberInactive()
	{
		Yii::$app->set('settings', new FakeSettings([
			'values' => ['general' => ['userAccountActivation' => User::ACCOUNT_ACTIVATION_CONFIRMATION]],
		]));

		$form = $this->makeForm();
		$this->assertTrue($form->signup(), print_r($form->getErrors(), true));

		$user = User::findOne(['email' => 'ana@test.local']);
		$this->assertSame(User::STATUS_INACTIVE, (int) $user->status);
		$this->assertNotEmpty($user->signup_token);

		// The subscriber waits for the activation link too.
		$subscriber = Subscriber::findOne(['user_id' => $user->id]);
		$this->assertSame(Subscriber::STATUS_INACTIVE, (int) $subscriber->status);
	}

	public function testHoneypotSilentlyBlocksTheSignup()
	{
		$form = $this->makeForm(['workEmail' => 'bot@spam.tld']);

		$this->assertFalse($form->signup());
		$this->assertSame(0, (int) (new Query())->from('user')->count());
	}

	public function testDuplicateEmailFailsWithoutWriting()
	{
		$this->insertRow('user', [
			'email' => 'ana@test.local',
			'first_name' => 'Existing',
			'last_name' => 'User',
			'status' => 1,
			'deleted' => 0,
		]);

		$form = $this->makeForm();

		$this->assertFalse($form->signup());
		$this->assertTrue($form->hasErrors('email'));
		$this->assertSame(1, (int) (new Query())->from('user')->count());
		$this->assertSame(0, (int) (new Query())->from('subscriber')->count());
	}

	public function testAWeakPasswordIsRejected()
	{
		$form = $this->makeForm(['password' => 'secret', 'password_confirm' => 'secret']);

		$this->assertFalse($form->signup());
		$this->assertTrue($form->hasErrors('password'));
		$this->assertSame(0, (int) (new Query())->from('user')->count());
	}
}
