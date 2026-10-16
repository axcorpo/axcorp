<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%auth_rule}}".
 *
 * @property string $name
 * @property resource $data
 * @property int $created_at
 * @property int $updated_at
 *
 * @property AuthItem[] $authItems
 */
class AuthRule extends UuidActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%auth_rule}}';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['name'], 'required'],
			[['data'], 'string'],
			[['created_at', 'updated_at'], 'integer'],
			[['name'], 'string', 'max' => 64],
			[['name'], 'unique'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'name' => Yii::t('label', 'Name'),
			'data' => Yii::t('label', 'Data'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getAuthItems()
	{
		return $this->hasMany(AuthItem::class, ['rule_name' => 'name']);
	}
}
