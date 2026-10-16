<?php

namespace tests\unit;

use common\helpers\UuidHelper;
use common\models\User;
use tests\DatabaseTestCase;
use tests\support\UuidTestRecord;

/**
 * The ActiveRecord base class for BINARY(16) UUID v7 primary keys: id generation on
 * insert, the string/bytes conversions at the model boundary and the validation
 * rules lifted for the converted columns.
 */
class UuidActiveRecordTest extends DatabaseTestCase
{
	/**
	 * @return UuidTestRecord an unsaved record with every NOT NULL column filled
	 */
	private function newRecord()
	{
		$record = new UuidTestRecord();
		$this->fillRequired($record);

		return $record;
	}

	public function testTableIsRecognizedAsUuidKeyed()
	{
		$this->assertTrue(UuidTestRecord::hasUuidPrimaryKey());
	}

	public function testInsertGeneratesAVersion7BinaryPrimaryKey()
	{
		$record = $this->newRecord();

		$this->assertTrue($record->save(false), print_r($record->errors, true));
		$this->assertSame(16, strlen($record->id));
		$this->assertSame('7', $record->uuid[14]);
		$this->assertMatchesRegularExpression(UuidHelper::PATTERN, $record->uuid);

		$stored = UuidTestRecord::findOne(['id' => $record->id]);
		$this->assertNotNull($stored);
		$this->assertSame($record->uuid, $stored->uuid);
	}

	public function testExplicitIdIsKept()
	{
		$id = UuidHelper::uuid7Bytes();
		$record = $this->newRecord();
		$record->id = $id;

		$this->assertTrue($record->save(false));
		$this->assertSame($id, $record->id);
	}

	public function testUuidStringsAssignedToBinaryColumnsAreStoredAsBytes()
	{
		$parent = $this->newRecord();
		$parent->save(false);

		$child = $this->newRecord();
		$child->parent_id = $parent->uuid; // what a form dropdown posts

		$this->assertTrue($child->save(false));
		$this->assertSame($parent->id, $child->parent_id);
		$this->assertSame($parent->id, UuidTestRecord::findOne(['id' => $child->id])->parent_id);
	}

	public function testEmptyOptionalForeignKeyIsStoredAsNull()
	{
		$record = $this->newRecord();
		$record->parent_id = ''; // an empty dropdown

		$this->assertTrue($record->save(false));
		$this->assertNull($record->parent_id);
	}

	public function testIntegerRuleIsLiftedForBinaryColumnsOnly()
	{
		$parent = $this->newRecord();
		$parent->save(false);

		$record = $this->newRecord();
		$record->parent_id = $parent->uuid;
		$record->status = 1;
		$this->assertTrue($record->validate(), print_r($record->errors, true));

		$record->parent_id = $parent->id; // raw bytes, as loaded from the database
		$this->assertTrue($record->validate(), print_r($record->errors, true));

		$record->parent_id = '12'; // the old integer ids are no longer valid references
		$this->assertFalse($record->validate());
		$this->assertArrayHasKey('parent_id', $record->errors);

		$record->parent_id = null;
		$record->status = 'active'; // non-binary columns keep their integer rule
		$this->assertFalse($record->validate());
		$this->assertArrayHasKey('status', $record->errors);
	}

	public function testFindOneByUuidAcceptsBothRepresentations()
	{
		$record = $this->newRecord();
		$record->save(false);

		$this->assertSame($record->id, UuidTestRecord::findOneByUuid($record->uuid)->id);
		$this->assertSame($record->id, UuidTestRecord::findOneByUuid($record->id)->id);
		$this->assertNull(UuidTestRecord::findOneByUuid('42'));
		$this->assertNull(UuidTestRecord::findOneByUuid(UuidHelper::uuid7()));
		$this->assertNull(UuidTestRecord::findOneByUuid(['id' => $record->uuid]));
	}

	public function testUuidAccessors()
	{
		$parent = $this->newRecord();
		$parent->save(false);
		$child = $this->newRecord();
		$child->parent_id = $parent->id;
		$child->save(false);

		$this->assertSame(UuidHelper::fromBytes($child->id), $child->uuid);
		$this->assertSame($parent->uuid, $child->uuidOf('parent_id'));
		$this->assertSame('', $this->newRecord()->uuidOf('parent_id'));
		$this->assertNull((new UuidTestRecord())->uuid);
	}

	public function testFieldsExposeCanonicalStringsNeverRawBytes()
	{
		$parent = $this->newRecord();
		$parent->save(false);
		$child = $this->newRecord();
		$child->parent_id = $parent->id;
		$child->save(false);

		$array = $child->toArray();

		$this->assertSame($child->uuid, $array['id']);
		$this->assertSame($parent->uuid, $array['parent_id']);
		$this->assertNull($parent->toArray()['parent_id']);
		$this->assertNotFalse(json_encode($array));
	}

	public function testUserIdentityExposesTheUuidString()
	{
		$id = $this->insertRow('user', ['status' => User::STATUS_ACTIVE, 'deleted' => 0]);
		$user = User::findOne(['id' => $id]);

		$this->assertNotNull($user);
		$this->assertSame(UuidHelper::fromBytes($id), $user->getId(), 'sessions, RBAC and blameable columns must never see raw bytes');
		$this->assertSame($user->getId(), $user->uuid);
	}
}
