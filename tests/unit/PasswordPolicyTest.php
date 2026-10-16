<?php

namespace tests\unit;

use common\validators\PasswordValidator;
use tests\WebTestCase;
use yii\base\DynamicModel;

/**
 * The password policy that replaced `['string', 'min' => 6]`.
 *
 * Six characters of anything is what the assessment found, and the API's user form had
 * no length rule at all. The policy now lives in one validator so the signup, reset,
 * profile and API paths cannot drift apart again.
 */
class PasswordPolicyTest extends WebTestCase
{
	/**
	 * @param string $password
	 * @return DynamicModel
	 */
	private function check($password)
	{
		$model = new DynamicModel(['password' => $password]);
		$model->addRule(['password'], PasswordValidator::class);
		$model->validate();

		return $model;
	}

	/**
	 * @dataProvider rejectedProvider
	 * @param string $password
	 * @param string $why
	 */
	public function testWeakPasswordsAreRejected($password, $why)
	{
		$this->assertTrue($this->check($password)->hasErrors('password'), $why);
	}

	/**
	 * @return array
	 */
	public function rejectedProvider()
	{
		return [
			'the old six-character minimum' => ['Ab1!xy', 'six characters is what the assessment flagged'],
			'no uppercase' => ['longenough1!', 'missing an uppercase letter'],
			'no lowercase' => ['LONGENOUGH1!', 'missing a lowercase letter'],
			'no digit' => ['LongEnoughAb!', 'missing a number'],
			'no special character' => ['LongEnough123', 'missing a special character'],
			'all one class' => ['aaaaaaaaaaaa', 'a single character class'],
		];
	}

	public function testAStrongPasswordIsAccepted()
	{
		$model = $this->check('Correct-Horse-9');

		$this->assertFalse($model->hasErrors('password'), implode(', ', $model->getErrors('password')));
	}

	public function testLengthIsCountedInCharactersNotBytes()
	{
		// Ten characters, but more than ten bytes in UTF-8. A byte-counting check would
		// have let a shorter password through here.
		$model = $this->check('Paróle-Tăre9');

		$this->assertFalse($model->hasErrors('password'), implode(', ', $model->getErrors('password')));
	}

	public function testThereIsNoUpperLengthLimit()
	{
		$model = $this->check(str_repeat('Aa1!', 64));

		$this->assertFalse(
			$model->hasErrors('password'),
			'capping the length only shrinks the search space; the hash is fixed-length anyway'
		);
	}

	public function testEveryFailureStatesTheWholePolicyInOneMessage()
	{
		// One sentence with every requirement, not one error per missing class that the
		// user only discovers after fixing the previous one.
		$tooShort = $this->check('abc')->getErrors('password');
		$this->assertCount(1, $tooShort);
		foreach (['longenough1!', 'LONGENOUGH1!', 'LongEnoughAb!', 'LongEnough123'] as $password) {
			$this->assertSame($tooShort, $this->check($password)->getErrors('password'), $password);
		}
	}

	public function testAnEmptyValueIsLeftToTheRequiredRule()
	{
		// The forms make the password optional on edit, so an untouched field must not
		// fail the policy -- `required` is what decides whether it had to be there.
		$this->assertFalse($this->check('')->hasErrors('password'));
	}
}
