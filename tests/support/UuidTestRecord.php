<?php

namespace tests\support;

use common\models\UuidActiveRecord;

/**
 * Minimal ActiveRecord over the `user` table (present in every app schema) used to
 * exercise the BINARY(16) UUID v7 layer without depending on the User model's
 * validation rules, behaviors or identity logic.
 *
 * `parent_id` keeps the `integer` rule the generated models still declare, so the
 * tests can prove [[UuidActiveRecord::createValidators()]] lifts it for BINARY(16)
 * columns.
 */
class UuidTestRecord extends UuidActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%user}}';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['parent_id', 'status', 'deleted'], 'integer'],
			[['first_name', 'last_name'], 'string', 'max' => 255],
		];
	}

	/**
	 * @return \common\models\UuidActiveQuery
	 */
	public function getParent()
	{
		return $this->hasOne(static::class, ['id' => 'parent_id']);
	}

	/**
	 * @return \common\models\UuidActiveQuery
	 */
	public function getChildren()
	{
		return $this->hasMany(static::class, ['parent_id' => 'id']);
	}
}
