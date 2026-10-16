<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "{{%subscriber_has_status}}".
 *
 * @property int $subscriber_id
 * @property int $status_id
 * @property string $notes
 * @property string $observations
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property Subscriber $input
 * @property SubscriberStatus $subscriberStatus
 * @property User $creator
 * @property User $updater
 */
class SubscriberHasStatus extends UuidActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%subscriber_has_status}}';
    }

	/**
	 * @inheritdoc
	 * @throws \Exception
	 */
	public function behaviors()
	{
		return [
			[
				'class' => BlameableBehavior::class,
			],
			[
				'class' => TimestampBehavior::class,
				'value' => (new \DateTime)->format('Y-m-d H:i:s'),
			],
		];
	}

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['subscriber_id', 'status_id', 'status'], 'required'],
            [['subscriber_id', 'status_id', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
            [['notes', 'observations'], 'string'],
            [['created_at', 'updated_at'], 'safe'],
            [['subscriber_id', 'status_id'], 'unique', 'targetAttribute' => ['subscriber_id', 'status_id']],
            [['subscriber_id'], 'exist', 'skipOnError' => true, 'targetClass' => Subscriber::class, 'targetAttribute' => ['subscriber_id' => 'id']],
            [['status_id'], 'exist', 'skipOnError' => true, 'targetClass' => SubscriberStatus::class, 'targetAttribute' => ['status_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'subscriber_id' => Yii::t('label', 'Input ID'),
            'status_id' => Yii::t('label', 'Repair Status ID'),
            'notes' => Yii::t('label', 'Notes'),
            'observations' => Yii::t('label', 'Observations'),
            'created_by' => Yii::t('label', 'Created By'),
            'updated_by' => Yii::t('label', 'Updated By'),
            'created_at' => Yii::t('label', 'Created At'),
            'updated_at' => Yii::t('label', 'Updated At'),
            'status' => Yii::t('label', 'Status'),
            'deleted' => Yii::t('label', 'Deleted'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getInput()
    {
        return $this->hasOne(Subscriber::class, ['id' => 'subscriber_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSubscriberStatus()
    {
        return $this->hasOne(SubscriberStatus::class, ['id' => 'status_id']);
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
}
