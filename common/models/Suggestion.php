<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%suggestion}}".
 *
 * @property int $id
 * @property string $series
 * @property int $number
 * @property string $title
 * @property string $content
 * @property string $attachment
 * @property int $type
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property SuggestionComment[] $suggestionComments
 * @property User $creator
 * @property User $updater
 */
class Suggestion extends UuidActiveRecord
{
	const STATUS_REJECTED = 0;
	const STATUS_APPROVED = 1;
	const STATUS_PENDING = 2;

	const TYPE_RESOURCE = 1;

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%suggestion}}';
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
				'skipUpdateOnClean' => false,
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
			[['number', 'type', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['title', 'status'], 'required'],
			[['content'], 'string'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['series', 'title', 'attachment'], 'string', 'max' => 255],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'series' => Yii::t('label', 'Series'),
			'number' => Yii::t('label', 'Number'),
			'title' => Yii::t('label', 'Title'),
			'content' => Yii::t('label', 'Content'),
			'attachment' => Yii::t('label', 'Attachment'),
			'type' => Yii::t('label', 'Type'),
			'created_by' => Yii::t('label', 'Created By'),
			'updated_by' => Yii::t('label', 'Updated By'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
			'status' => Yii::t('label', 'Status'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @inheritdoc
	 */
	public function beforeSave($insert)
	{
		if ($this->getIsNewRecord()) {
			if (empty($this->series)) {
				$this->series = 'SUGG';
			}
			if (empty($this->number)) {
				$this->number = static::find()->where(['series' => $this->series])->max('number') + 1;
			}
		}
		return parent::beforeSave($insert);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSuggestionComments()
	{
		return $this->hasMany(SuggestionComment::class, ['suggestion_id' => 'id']);
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
	 * Gets the zero filled documentSeriesNumber.
	 *
	 * @return string
	 */
	public function getDocumentSeriesNumber()
	{
		return trim($this->series . ' ' . str_pad($this->number, 5, 0, STR_PAD_LEFT));
	}

	/**
	 * Model status labels.
	 *
	 * @return array
	 */
	public static function getStatusLabels()
	{
		return [
			self::STATUS_REJECTED => [
				'label' => Yii::t('label', 'Rejected'),
				'color' => 'danger',
			],
			self::STATUS_APPROVED => [
				'label' => Yii::t('label', 'Approved'),
				'color' => 'success',
			],
			self::STATUS_PENDING => [
				'label' => Yii::t('label', 'Pending'),
				'color' => 'warning',
			],
		];
	}

	/**
	 * Model type labels.
	 *
	 * @return array
	 */
	public static function getTypeLabels()
	{
		return [
			self::TYPE_RESOURCE => Yii::t('label', 'Resource'),
		];
	}
}
