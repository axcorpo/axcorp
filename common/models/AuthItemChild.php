<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%auth_item_child}}".
 *
 * @property string $parent
 * @property string $child
 *
 * @property AuthItem $parent0
 * @property AuthItem $child0
 */
class AuthItemChild extends UuidActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%auth_item_child}}';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['parent', 'child'], 'required'],
			[['parent', 'child'], 'string', 'max' => 64],
			[['parent', 'child'], 'unique', 'targetAttribute' => ['parent', 'child']],
			[['parent'], 'exist', 'skipOnError' => true, 'targetClass' => AuthItem::class, 'targetAttribute' => ['parent' => 'name']],
			[['child'], 'exist', 'skipOnError' => true, 'targetClass' => AuthItem::class, 'targetAttribute' => ['child' => 'name']],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'parent' => Yii::t('label', 'Parent'),
			'child' => Yii::t('label', 'Child'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getParent0()
	{
		return $this->hasOne(AuthItem::class, ['name' => 'parent']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getChild0()
	{
		return $this->hasOne(AuthItem::class, ['name' => 'child']);
	}
}
