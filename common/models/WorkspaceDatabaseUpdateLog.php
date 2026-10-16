<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "{{%workspace_database_update_log}}".
 *
 * Each row records the execution of a single query against a single workspace
 * database, performed through the Workspace database update form.
 *
 * @property int $id
 * @property string $batch_id
 * @property int $workspace_id
 * @property string $workspace_code
 * @property string $workspace_url
 * @property string $query
 * @property int $status
 * @property string|null $error
 * @property int|null $created_by
 * @property string $created_at
 *
 * @property Workspace $workspace
 * @property User $creator
 */
class WorkspaceDatabaseUpdateLog extends UuidActiveRecord
{
	const RESULT_ERROR = 0;
	const RESULT_SUCCESS = 1;

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%workspace_database_update_log}}';
	}

	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'BlameableBehavior' => [
				'class' => BlameableBehavior::class,
				'updatedByAttribute' => false,
			],
			'TimestampBehavior' => [
				'class' => TimestampBehavior::class,
				'updatedAtAttribute' => false,
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
			[['batch_id', 'workspace_id', 'workspace_code', 'workspace_url', 'query', 'status'], 'required'],
			[['workspace_id', 'status', 'created_by'], 'integer'],
			[['query', 'error'], 'string'],
			[['created_at'], 'safe'],
			[['batch_id'], 'string', 'max' => 32],
			[['workspace_code', 'workspace_url'], 'string', 'max' => 255],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'batch_id' => Yii::t('label', 'Batch'),
			'workspace_id' => Yii::t('label', 'Workspace ID'),
			'workspace_code' => Yii::t('label', 'Code'),
			'workspace_url' => Yii::t('label', 'Url'),
			'query' => Yii::t('label', 'Query'),
			'status' => Yii::t('label', 'Status'),
			'error' => Yii::t('label', 'Error'),
			'created_by' => Yii::t('label', 'Created By'),
			'created_at' => Yii::t('label', 'Created At'),
		];
	}

	/**
	 * Result status labels.
	 *
	 * @return array
	 */
	public static function getResultLabels()
	{
		return [
			static::RESULT_ERROR => [
				'label' => Yii::t('label', 'Error'),
				'color' => 'danger',
			],
			static::RESULT_SUCCESS => [
				'label' => Yii::t('label', 'Success'),
				'color' => 'success',
			],
		];
	}

	/**
	 * Gets if the query executed successfully on this workspace.
	 *
	 * @return bool
	 */
	public function getIsSuccessful()
	{
		return (int) $this->status === static::RESULT_SUCCESS;
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getWorkspace()
	{
		return $this->hasOne(Workspace::class, ['id' => 'workspace_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getCreator()
	{
		return $this->hasOne(User::class, ['id' => 'created_by']);
	}
}
