<?php

namespace tests\unit;

use common\helpers\UuidHelper;
use common\widgets\datatable\DataTableAction;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * DataTable JSON responses: BINARY(16) values in the formatted rows are converted to
 * UUID strings, otherwise json_encode() fails on the invalid UTF-8 and the grid breaks.
 */
class DataTableUuidTest extends TestCase
{
	/**
	 * @param mixed $data
	 * @return mixed
	 */
	private function normalize($data)
	{
		$class = new ReflectionClass(DataTableAction::class);
		$method = $class->getMethod('normalizeBinaryUuids');
		$method->setAccessible(true);

		return $method->invoke($class->newInstanceWithoutConstructor(), $data);
	}

	public function testBinaryIdsBecomeUuidStringsAtEveryDepth()
	{
		$id = UuidHelper::uuid7Bytes();
		$parentId = UuidHelper::uuid7Bytes();
		$rows = [
			['id' => $id, 'group' => ['parent_id' => $parentId, 'name' => 'x'], 'action' => '<a href="#">view</a>'],
		];

		$normalized = $this->normalize($rows);

		$this->assertSame(UuidHelper::fromBytes($id), $normalized[0]['id']);
		$this->assertSame(UuidHelper::fromBytes($parentId), $normalized[0]['group']['parent_id']);
		$this->assertSame('x', $normalized[0]['group']['name']);
		$this->assertNotFalse(json_encode($normalized), 'the response must be encodable');
	}

	public function testOtherValuesAreUntouched()
	{
		$rows = [['id' => 'sixteen-chars-ok', 'count' => 16, 'price' => 1.5, 'none' => null, 'flag' => false]];

		$this->assertSame($rows, $this->normalize($rows));
	}
}
