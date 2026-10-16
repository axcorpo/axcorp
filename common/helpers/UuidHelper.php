<?php

namespace common\helpers;

use InvalidArgumentException;

/**
 * Helper for working with UUID v7 primary keys stored as BINARY(16).
 *
 * The canonical representation exposed to users/APIs/URLs is the standard
 * RFC 9562 string form (e.g. `0190f4d6-8d4a-7a2c-9f35-3d4c1e7a9b12`), while
 * the database stores the raw 16 bytes.
 *
 * Generation is done in PHP (never with MySQL's `UUID()`, which produces v1):
 *  - `ramsey/uuid` (`Uuid::uuid7()`) is used when available (PHP >= 8.0, ramsey/uuid >= 4.4);
 *  - otherwise a pure-PHP RFC 9562 compliant UUID v7 generator is used, with a
 *    monotonic counter so ids generated in the same millisecond still sort in order.
 */
class UuidHelper
{
	/**
	 * Canonical UUID string pattern (any RFC 9562 version).
	 */
	const PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

	/**
	 * @var int Last millisecond timestamp used by the fallback generator.
	 */
	private static $lastMilliseconds = 0;

	/**
	 * @var int 12-bit monotonic sequence (rand_a field) within the same millisecond.
	 */
	private static $sequence = 0;

	/**
	 * Generates a new UUID v7 in canonical string form.
	 *
	 * @return string e.g. `0190f4d6-8d4a-7a2c-9f35-3d4c1e7a9b12`
	 */
	public static function uuid7()
	{
		if (self::hasRamseyUuid7()) {
			return \Ramsey\Uuid\Uuid::uuid7()->toString();
		}
		return self::fromBytes(self::uuid7Bytes());
	}

	/**
	 * Generates a new UUID v7 as raw 16 bytes, ready to be stored in a BINARY(16) column.
	 *
	 * @return string 16 raw bytes
	 */
	public static function uuid7Bytes()
	{
		if (self::hasRamseyUuid7()) {
			return \Ramsey\Uuid\Uuid::uuid7()->getBytes();
		}

		$milliseconds = (int) floor(microtime(true) * 1000);

		// Keep ids generated within the same millisecond monotonic via the 12-bit rand_a field
		if ($milliseconds === self::$lastMilliseconds) {
			self::$sequence++;
			if (self::$sequence > 0x0FFF) {
				// Sequence exhausted: borrow the next millisecond
				$milliseconds++;
				self::$lastMilliseconds = $milliseconds;
				self::$sequence = random_int(0, 0x07FF);
			}
		} else {
			self::$lastMilliseconds = $milliseconds;
			// Start below the middle of the range so plenty of increments remain
			self::$sequence = random_int(0, 0x07FF);
		}

		// 48-bit big-endian Unix timestamp in milliseconds
		$bytes = hex2bin(str_pad(dechex($milliseconds), 12, '0', STR_PAD_LEFT));
		// 16-bit version + rand_a (monotonic sequence)
		$bytes .= chr(0x70 | ((self::$sequence >> 8) & 0x0F)) . chr(self::$sequence & 0xFF);
		// 64-bit variant + rand_b
		$random = random_bytes(8);
		$random[0] = chr((ord($random[0]) & 0x3F) | 0x80);

		return $bytes . $random;
	}

	/**
	 * Checks if ramsey/uuid with UUID v7 support can be used on this PHP runtime.
	 * The installed ramsey/uuid 4.9 uses PHP 8 syntax, so even autoloading it on
	 * PHP 7.x would fail — hence the PHP version guard before `class_exists()`.
	 *
	 * @return bool
	 */
	protected static function hasRamseyUuid7()
	{
		return PHP_VERSION_ID >= 80000
			&& class_exists('\Ramsey\Uuid\Uuid')
			&& method_exists('\Ramsey\Uuid\Uuid', 'uuid7');
	}

	/**
	 * Converts a canonical UUID string to its raw 16-byte representation.
	 *
	 * @param string $uuid Canonical UUID string. 16 raw bytes are accepted and returned unchanged.
	 * @return string 16 raw bytes
	 * @throws InvalidArgumentException when the value is neither a UUID string nor 16 raw bytes.
	 */
	public static function toBytes($uuid)
	{
		if (self::isBytes($uuid)) {
			return $uuid;
		}
		if (!is_string($uuid) || !preg_match(self::PATTERN, $uuid)) {
			throw new InvalidArgumentException('Invalid UUID string: ' . (is_string($uuid) ? $uuid : gettype($uuid)));
		}
		return hex2bin(str_replace('-', '', strtolower($uuid)));
	}

	/**
	 * Converts raw 16 bytes to the canonical UUID string form.
	 *
	 * @param string $bytes 16 raw bytes. A canonical UUID string is accepted and returned lowercased.
	 * @return string e.g. `0190f4d6-8d4a-7a2c-9f35-3d4c1e7a9b12`
	 * @throws InvalidArgumentException when the value is neither 16 raw bytes nor a UUID string.
	 */
	public static function fromBytes($bytes)
	{
		if (is_string($bytes) && preg_match(self::PATTERN, $bytes)) {
			return strtolower($bytes);
		}
		if (!self::isBytes($bytes)) {
			throw new InvalidArgumentException('Invalid UUID binary value.');
		}
		$hex = bin2hex($bytes);
		return sprintf(
			'%s-%s-%s-%s-%s',
			substr($hex, 0, 8),
			substr($hex, 8, 4),
			substr($hex, 12, 4),
			substr($hex, 16, 4),
			substr($hex, 20, 12)
		);
	}

	/**
	 * Checks if the value is a canonical UUID string.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public static function isValid($value)
	{
		return is_string($value) && preg_match(self::PATTERN, $value) === 1;
	}

	/**
	 * Checks if the value looks like a raw 16-byte UUID.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public static function isBytes($value)
	{
		return is_string($value) && strlen($value) === 16;
	}
}
