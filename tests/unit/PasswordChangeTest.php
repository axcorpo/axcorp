<?php

namespace tests\unit;

use common\models\User;
use frontend\modules\account\models\PasswordForm;
use tests\DatabaseTestCase;
use Yii;

/**
 * The account page's password form: it lives apart from the personal-data
 * form, asks for the current password, and rotates the auth key on success.
 */
class PasswordChangeTest extends DatabaseTestCase
{
	const OLD_PASSWORD = 'Old-Secret-1!';
	const NEW_PASSWORD = 'New-Secret-2!';

	/**
	 * @return User
	 */
	protected function makeUser()
	{
		$id = $this->insertRow('user', [
			'email' => 'ana@test.local',
			'first_name' => 'Ana',
			'last_name' => 'Pop',
			'password_hash' => Yii::$app->security->generatePasswordHash(self::OLD_PASSWORD),
			'auth_key' => 'old-auth-key',
			'status' => User::STATUS_ACTIVE,
			'deleted' => User::NO,
		]);

		return User::findOne(['id' => $id]);
	}

	protected function makeForm(User $user, array $attributes = [])
	{
		return new PasswordForm($user, array_merge([
			'new_password' => self::NEW_PASSWORD,
			'new_password_confirm' => self::NEW_PASSWORD,
			'current_password' => self::OLD_PASSWORD,
		], $attributes));
	}

	public function testTheRightCurrentPasswordChangesItAndRotatesTheAuthKey()
	{
		$user = $this->makeUser();
		$form = $this->makeForm($user);

		$this->assertTrue($form->changePassword(), print_r($form->getErrors(), true));

		$stored = User::findOne(['id' => $user->id]);
		$this->assertTrue(Yii::$app->security->validatePassword(self::NEW_PASSWORD, $stored->password_hash));
		$this->assertFalse(Yii::$app->security->validatePassword(self::OLD_PASSWORD, $stored->password_hash));
		$this->assertNotSame('old-auth-key', $stored->auth_key);
	}

	public function testAWrongCurrentPasswordIsRefused()
	{
		$user = $this->makeUser();
		$form = $this->makeForm($user, ['current_password' => 'not-the-password']);

		$this->assertFalse($form->changePassword());
		$this->assertTrue($form->hasErrors('current_password'));

		$stored = User::findOne(['id' => $user->id]);
		$this->assertTrue(Yii::$app->security->validatePassword(self::OLD_PASSWORD, $stored->password_hash));
		$this->assertSame('old-auth-key', $stored->auth_key);
	}

	public function testTheCurrentPasswordIsRequired()
	{
		$form = $this->makeForm($this->makeUser(), ['current_password' => '']);

		$this->assertFalse($form->changePassword());
		$this->assertTrue($form->hasErrors('current_password'));
	}

	public function testAMismatchedConfirmationIsRefused()
	{
		$form = $this->makeForm($this->makeUser(), ['new_password_confirm' => 'Something-Else-3!']);

		$this->assertFalse($form->changePassword());
		$this->assertTrue($form->hasErrors('new_password_confirm'));
	}

	public function testAWeakNewPasswordIsRefused()
	{
		$form = $this->makeForm($this->makeUser(), ['new_password' => 'short', 'new_password_confirm' => 'short']);

		$this->assertFalse($form->changePassword());
		$this->assertTrue($form->hasErrors('new_password'));
	}
}
