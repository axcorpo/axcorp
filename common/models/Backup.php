<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%backup}}".
 *
 * @property int $id
 * @property int $file_size
 * @property string $data
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
class Backup extends UuidActiveRecord
{
	const STATUS_RUNNING = 1;
	const STATUS_COMPLETE = 2;

	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%backup}}';
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
			'SoftDeleteBehavior' => [
				'class' => SoftDeleteBehavior::class,
				'softDeleteAttributeValues' => [
					'deleted' => static::YES,
				],
			],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['file_size', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['data'], 'string'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['status'], 'required'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'file_size' => Yii::t('label', 'File Size'),
			'data' => Yii::t('label', 'Data'),
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
	 * Gets the filePath.
	 *
	 * @return bool|string
	 */
	public function getFilePath()
	{
		$file = Yii::getAlias("@backups/{$this->id}.zip");
		if (is_file($file)) {
			return $file;
		}
		return null;
	}

	/**
	 * Gets the fileName.
	 *
	 * @return bool|string
	 */
	public function getFileName()
	{
		try {
			return (new \DateTime($this->created_at))->format('Y-m-d_H-i-s') . '.zip';
		} catch (\Exception $e) {
			return "{$this->created_at}.zip";
		}
	}

	/**
	 * Model status labels.
	 *
	 * @return array
	 */
	public static function getStatusLabels()
	{
		return [
			self::STATUS_RUNNING => [
				'label' => Yii::t('label', 'Running'),
				'color' => 'warning',
			],
			self::STATUS_COMPLETE => [
				'label' => Yii::t('label', 'Complete'),
				'color' => 'success',
			],
		];
	}
}
