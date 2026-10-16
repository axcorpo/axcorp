<?php

namespace tests\unit;

use common\helpers\UuidHelper;
use common\validators\UuidValidator;
use PHPUnit\Framework\TestCase;
use yii\base\DynamicModel;

/**
 * The validator that replaces `integer` on BINARY(16) id/foreign key attributes.
 */
class UuidValidatorTest extends TestCase
{
	/**
	 * @param mixed $value
	 * @param array $options
	 * @return DynamicModel
	 */
	private function check($value, array $options = [])
	{
		$model = new DynamicModel(['key' => $value]);
		$model->addRule(['key'], UuidValidator::class, $options);
		$model->validate();

		return $model;
	}

	public function testCanonicalStringIsAccepted()
	{
		$this->assertFalse($this->check(UuidHelper::uuid7())->hasErrors('key'));
		$this->assertFalse($this->check(strtoupper(UuidHelper::uuid7()))->hasErrors('key'));
	}

	public function testRawBytesAreAcceptedByDefault()
	{
		// Attributes loaded from the database hold the raw representation
		$this->assertFalse($this->check(UuidHelper::uuid7Bytes())->hasErrors('key'));
	}

	public function testRawBytesCanBeRefused()
	{
		$this->assertTrue($this->check(UuidHelper::uuid7Bytes(), ['allowBinary' => false])->hasErrors('key'));
	}

	/**
	 * @dataProvider rejectedProvider
	 * @param mixed $value
	 */
	public function testOtherValuesAreRejected($value)
	{
		$model = $this->check($value);

		$this->assertTrue($model->hasErrors('key'));
		$this->assertSame('Key is invalid.', $model->getFirstError('key'));
	}

	/**
	 * @return array
	 */
	public function rejectedProvider()
	{
		return [
			'legacy integer id' => [42],
			'numeric string' => ['42'],
			'random text' => ['0190f4d6-8d4a-7a2c-9f35-3d4c1e7a9b1z'],
			'hex without dashes' => ['0190f4d68d4a7a2c9f353d4c1e7a9b12'],
			'array' => [['0190f4d6-8d4a-7a2c-9f35-3d4c1e7a9b12']],
		];
	}

	public function testEmptyValueIsLeftToTheRequiredRule()
	{
		$this->assertFalse($this->check('')->hasErrors('key'));
		$this->assertFalse($this->check(null)->hasErrors('key'));
	}
}
