<?php

namespace tests\unit;

use common\helpers\UuidHelper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The UUID v7 helper behind every BINARY(16) primary key: generation, the two
 * representations (canonical string for URLs/APIs, raw 16 bytes for the database)
 * and the conversions between them.
 */
class UuidHelperTest extends TestCase
{
	public function testGeneratedUuidIsCanonicalVersion7()
	{
		$uuid = UuidHelper::uuid7();

		$this->assertMatchesRegularExpression(UuidHelper::PATTERN, $uuid);
		$this->assertSame(strtolower($uuid), $uuid, 'the canonical form is lowercase');
		$this->assertSame('7', $uuid[14], 'the version nibble must be 7');
		$this->assertContains($uuid[19], ['8', '9', 'a', 'b'], 'RFC 9562 variant');
	}

	public function testGeneratedBytesAreSixteenBytesOfVersion7()
	{
		$bytes = UuidHelper::uuid7Bytes();

		$this->assertSame(16, strlen($bytes));
		$this->assertSame(0x70, ord($bytes[6]) & 0xF0, 'version 7 in the high nibble of byte 6');
		$this->assertSame(0x80, ord($bytes[8]) & 0xC0, 'RFC variant in the top bits of byte 8');
	}

	public function testUuidsAreUniqueAndTimeOrdered()
	{
		$uuids = [];
		for ($i = 0; $i < 500; $i++) {
			$uuids[] = UuidHelper::uuid7();
		}

		$this->assertCount(500, array_unique($uuids));

		$sorted = $uuids;
		sort($sorted, SORT_STRING);
		$this->assertSame($sorted, $uuids, 'ids generated in sequence must sort in generation order (ORDER BY id keeps insert order)');
	}

	public function testBytesAndStringRoundTrip()
	{
		$bytes = UuidHelper::uuid7Bytes();
		$string = UuidHelper::fromBytes($bytes);

		$this->assertMatchesRegularExpression(UuidHelper::PATTERN, $string);
		$this->assertSame($bytes, UuidHelper::toBytes($string));
		$this->assertSame($string, UuidHelper::fromBytes(UuidHelper::toBytes($string)));
	}

	public function testKnownValueConvertsBothWays()
	{
		$string = '0190f4d6-8d4a-7a2c-9f35-3d4c1e7a9b12';

		$this->assertSame(hex2bin('0190f4d68d4a7a2c9f353d4c1e7a9b12'), UuidHelper::toBytes($string));
		$this->assertSame($string, UuidHelper::fromBytes(hex2bin('0190f4d68d4a7a2c9f353d4c1e7a9b12')));
	}

	public function testConversionsAreLenientAboutTheInputRepresentation()
	{
		$bytes = UuidHelper::uuid7Bytes();
		$string = UuidHelper::fromBytes($bytes);

		// Raw bytes pass through toBytes(), a string passes through fromBytes()
		$this->assertSame($bytes, UuidHelper::toBytes($bytes));
		$this->assertSame($string, UuidHelper::fromBytes($string));
		// Upper-case input is accepted and normalized
		$this->assertSame($bytes, UuidHelper::toBytes(strtoupper($string)));
		$this->assertSame($string, UuidHelper::fromBytes(strtoupper($string)));
	}

	public function testInvalidStringIsRejectedByToBytes()
	{
		$this->expectException(InvalidArgumentException::class);
		UuidHelper::toBytes('not-a-uuid');
	}

	public function testIntegerIdIsRejectedByToBytes()
	{
		// The pre-UUID integer ids must never be silently accepted
		$this->expectException(InvalidArgumentException::class);
		UuidHelper::toBytes(42);
	}

	public function testWrongLengthBinaryIsRejectedByFromBytes()
	{
		$this->expectException(InvalidArgumentException::class);
		UuidHelper::fromBytes('short');
	}

	public function testIsValidAndIsBytes()
	{
		$bytes = UuidHelper::uuid7Bytes();
		$string = UuidHelper::fromBytes($bytes);

		$this->assertTrue(UuidHelper::isValid($string));
		$this->assertTrue(UuidHelper::isValid(strtoupper($string)));
		$this->assertFalse(UuidHelper::isValid($bytes));
		$this->assertFalse(UuidHelper::isValid('42'));
		$this->assertFalse(UuidHelper::isValid(42));
		$this->assertFalse(UuidHelper::isValid(null));

		$this->assertTrue(UuidHelper::isBytes($bytes));
		$this->assertTrue(UuidHelper::isBytes('abcdefghijklmnop'), 'isBytes() only checks the length');
		$this->assertFalse(UuidHelper::isBytes($string));
		$this->assertFalse(UuidHelper::isBytes(42));
	}
}
