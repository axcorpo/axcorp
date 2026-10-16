<?php

namespace tests\unit;

use common\helpers\UuidHelper;
use tests\DatabaseTestCase;
use tests\support\UuidTestRecord;

/**
 * Queries keep accepting canonical UUID strings (from URLs, forms and the identity)
 * for BINARY(16) columns: the values are converted to bytes before the SQL is built.
 */
class UuidActiveQueryTest extends DatabaseTestCase
{
	/**
	 * @var UuidTestRecord
	 */
	private $parent;

	/**
	 * @var UuidTestRecord[]
	 */
	private $children = [];

	protected function setUp(): void
	{
		parent::setUp();

		$this->parent = new UuidTestRecord();
		$this->fillRequired($this->parent);
		$this->parent->save(false);

		for ($i = 0; $i < 3; $i++) {
			$child = new UuidTestRecord();
			$this->fillRequired($child);
			$child->parent_id = $this->parent->id;
			$child->save(false);
			$this->children[] = $child;
		}
	}

	public function testHashConditionWithUuidString()
	{
		$found = UuidTestRecord::find()->where(['id' => $this->parent->uuid])->one();

		$this->assertNotNull($found);
		$this->assertSame($this->parent->id, $found->id);
		$this->assertSame(3, (int) UuidTestRecord::find()->where(['parent_id' => $this->parent->uuid])->count());
	}

	public function testFindOneShortcutWithUuidString()
	{
		$this->assertSame($this->parent->id, UuidTestRecord::findOne($this->parent->uuid)->id);
		$this->assertCount(3, UuidTestRecord::findAll(['parent_id' => $this->parent->uuid]));
	}

	public function testOperatorConditionsWithAliasPrefixedColumns()
	{
		$all = (int) UuidTestRecord::find()->alias('u')->where(['u.parent_id' => $this->parent->uuid])->count();
		$this->assertSame(3, $all);

		$excluded = $this->children[0]->uuid;
		$rest = UuidTestRecord::find()
			->alias('u')
			->where(['u.parent_id' => $this->parent->uuid])
			->andWhere(['!=', 'u.id', $excluded])
			->all();
		$this->assertCount(2, $rest);
		$this->assertNotContains($excluded, array_map(function ($record) {
			return $record->uuid;
		}, $rest));
	}

	public function testInConditionWithMixedRepresentations()
	{
		$ids = [$this->children[0]->uuid, $this->children[1]->id];

		$found = UuidTestRecord::find()->where(['in', 'id', $ids])->all();
		$this->assertCount(2, $found);

		$found = UuidTestRecord::find()->where(['id' => $ids])->orderBy(['id' => SORT_ASC])->all();
		$this->assertCount(2, $found);
	}

	public function testNestedAndOrConditions()
	{
		$found = UuidTestRecord::find()->where([
			'or',
			['id' => $this->children[0]->uuid],
			['and', ['parent_id' => $this->parent->uuid], ['id' => $this->children[1]->uuid]],
		])->all();

		$this->assertCount(2, $found);
	}

	public function testRelationsResolveWhenTheForeignKeyHoldsAUuidString()
	{
		// A form model exposes the FK as a UUID string (see the *Form::afterFind()
		// overrides); lazy-loading the relation must still work.
		$child = UuidTestRecord::findOne($this->children[0]->uuid);
		$child->parent_id = $this->parent->uuid;

		$this->assertNotNull($child->parent);
		$this->assertSame($this->parent->id, $child->parent->id);
		$this->assertCount(3, $this->parent->children);
	}

	public function testUnknownUuidMatchesNothing()
	{
		$this->assertNull(UuidTestRecord::find()->where(['id' => UuidHelper::uuid7()])->one());
	}

	public function testIdsSortInInsertOrder()
	{
		$ordered = UuidTestRecord::find()
			->where(['parent_id' => $this->parent->uuid])
			->orderBy(['id' => SORT_ASC])
			->all();

		$this->assertSame(
			array_map(function ($record) { return $record->uuid; }, $this->children),
			array_map(function ($record) { return $record->uuid; }, $ordered),
			'UUID v7 keys are time ordered, so ORDER BY id keeps the insert order'
		);
	}
}
