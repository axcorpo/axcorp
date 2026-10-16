<?php

namespace tests\unit;

use common\components\UuidUrlManager;
use common\helpers\UuidHelper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The UrlManager keeps the many `['view', 'id' => $model->id]` call sites working:
 * raw BINARY(16) values become canonical UUID strings in generated URLs.
 */
class UuidUrlManagerTest extends TestCase
{
	/**
	 * @param mixed $params
	 * @return mixed
	 */
	private function normalize($params)
	{
		$class = new ReflectionClass(UuidUrlManager::class);
		$method = $class->getMethod('normalizeUuidParams');
		$method->setAccessible(true);

		// The parent UrlManager wants a request component on init; the conversion
		// itself is pure, so bypass the constructor.
		return $method->invoke($class->newInstanceWithoutConstructor(), $params);
	}

	public function testRawBytesBecomeTheCanonicalString()
	{
		$bytes = UuidHelper::uuid7Bytes();

		$this->assertSame(['view', 'id' => UuidHelper::fromBytes($bytes)], $this->normalize(['view', 'id' => $bytes]));
	}

	public function testNestedParametersAreConverted()
	{
		$bytes = UuidHelper::uuid7Bytes();
		$params = ['index', 'filter' => ['ids' => [$bytes, $bytes], 'page' => 2]];

		$this->assertSame(
			['index', 'filter' => ['ids' => [UuidHelper::fromBytes($bytes), UuidHelper::fromBytes($bytes)], 'page' => 2]],
			$this->normalize($params)
		);
	}

	public function testOrdinaryValuesPassThrough()
	{
		$uuid = UuidHelper::uuid7();
		$params = [
			'view',
			'id' => $uuid,
			'slug' => 'sixteen-chars-ok',   // 16 bytes of valid UTF-8 is a real parameter, not a UUID
			'page' => 3,
			'flag' => true,
			'nothing' => null,
			'name' => 'Ștefan',
		];

		$this->assertSame($params, $this->normalize($params));
	}
}
