<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%auth_assignment}}".
 *
 * @property string $item_name
 * @property string $user_id
 * @property int $created_at
 *
 * @property AuthItem $itemName
 */
class AuthAssignment extends UuidActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%auth_assignment}}';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['item_name', 'user_id'], 'required'],
			[['created_at'], 'integer'],
			[['item_name'], 'string', 'max' => 64],
			// user_id is BINARY(16): accepts the canonical UUID string (normalized to
			// raw bytes on save) or the raw 16 bytes
			[['user_id'], \common\validators\UuidValidator::class],
			[['item_name', 'user_id'], 'unique', 'targetAttribute' => ['item_name', 'user_id']],
			[['item_name'], 'exist', 'skipOnError' => true, 'targetClass' => AuthItem::class, 'targetAttribute' => ['item_name' => 'name']],
			[['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'item_name' => Yii::t('label', 'Item Name'),
			'user_id' => Yii::t('label', 'User ID'),
			'created_at' => Yii::t('label', 'Created At'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getItemName()
	{
		return $this->hasOne(AuthItem::class, ['name' => 'item_name']);
	}
}
