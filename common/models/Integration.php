<?php

namespace common\models;

use tws\behaviors\DateTimeBehavior;
use tws\behaviors\DefaultBehavior;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%integration}}".
 *
 * @property int $id
 * @property string $name
 * @property string $data
 * @property string $expire_at
 * @property int $type
 * @property int $sandbox
 * @property int $default
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property User $creator
 * @property User $updater
 */
class Integration extends UuidActiveRecord
{

	const TYPE_SPV = 1;

	/**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%integration}}';
    }

	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'BlameableBehavior' => [
				'class' => BlameableBehavior::class,
			],
			'TimestampBehavior' => [
				'class' => TimestampBehavior::class,
				'value' => (new \DateTime)->format('Y-m-d H:i:s'),
			],
			'DateTimeBehavior' => [
				'class' => DateTimeBehavior::class,
				'attributes' => ['expire_at'],
			],
			'DefaultBehavior' => [
				'class' => DefaultBehavior::class,
				'groupAttributes' => ['type'],
			],
			'SoftDeleteBehavior' => [
				'class' => SoftDeleteBehavior::class,
				'softDeleteAttributeValues' => [
					'deleted' => static::YES,
				],
			],
		];
	}

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'status'], 'required'],
            [['data'], 'string'],
            [['expire_at', 'created_at', 'updated_at'], 'safe'],
            [['type', 'sandbox', 'default', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
            [['name'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('label', 'ID'),
            'name' => Yii::t('label', 'Name'),
            'data' => Yii::t('label', 'Data'),
            'expire_at' => Yii::t('label', 'Expire At'),
            'type' => Yii::t('label', 'Type'),
            'sandbox' => Yii::t('label', 'Sandbox'),
            'default' => Yii::t('label', 'Default'),
            'created_by' => Yii::t('label', 'Created By'),
            'updated_by' => Yii::t('label', 'Updated By'),
            'created_at' => Yii::t('label', 'Created At'),
            'updated_at' => Yii::t('label', 'Updated At'),
            'status' => Yii::t('label', 'Status'),
            'deleted' => Yii::t('label', 'Deleted'),
        ];
    }

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCreator()
	{
		return $this->hasOne(User::class, ['id' => 'created_by']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUpdater()
	{
		return $this->hasOne(User::class, ['id' => 'updated_by']);
	}

	/**
	 * Model type labels.
	 *
	 * @return array
	 */
	public static function getTypeLabels()
	{
		return [
			static::TYPE_SPV => Yii::t('label', 'SPV'),
		];
	}
}
