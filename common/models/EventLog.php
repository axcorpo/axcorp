<?php

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\helpers\Html;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%event_log}}".
 *
 * @property int $id
 * @property int $user_id
 * @property string $model_key
 * @property string $model
 * @property string $module
 * @property string $controller
 * @property string $action
 * @property string $operation
 * @property string $resource
 * @property string $initial_data
 * @property string $final_data
 * @property string $ip_address
 * @property string $created_at
 * @property int $deleted
 *
 * @property User $user
 */
class EventLog extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%event_log}}';
	}

	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'TimestampBehavior' => [
				'class' => TimestampBehavior::class,
				'updatedAtAttribute' => false,
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
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['user_id', 'deleted'], 'integer'],
			[['initial_data', 'final_data'], 'string'],
			[['created_at'], 'safe'],
			[['created_at'], 'default'],
			[['model_key', 'model', 'module', 'controller', 'action', 'operation', 'resource', 'ip_address'], 'string', 'max' => 255],
			[['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'user_id' => Yii::t('label', 'User ID'),
			'model_key' => Yii::t('label', 'Model Key'),
			'model' => Yii::t('label', 'Model'),
			'module' => Yii::t('label', 'Module'),
			'controller' => Yii::t('label', 'Controller'),
			'action' => Yii::t('label', 'Action'),
			'operation' => Yii::t('label', 'Operation'),
			'resource' => Yii::t('label', 'Resource'),
			'initial_data' => Yii::t('label', 'Initial Data'),
			'final_data' => Yii::t('label', 'Final Data'),
			'ip_address' => Yii::t('label', 'Ip Address'),
			'created_at' => Yii::t('label', 'Created At'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUser()
	{
		return $this->hasOne(User::class, ['id' => 'user_id']);
	}

	/**
	 * Gets the actions array.
	 *
	 * @return array
	 */
	public static function getActions()
	{
		return [
			'login' => [
				'label' => Yii::t('common', 'Login'),
				'color' => 'default',
			],
			'logout' => [
				'label' => Yii::t('common', 'Logout'),
				'color' => 'default',
			],
			'signup' => [
				'label' => Yii::t('common', 'Signup'),
				'color' => 'default',
			],
			'create' => [
				'label' => Yii::t('common', 'Create'),
				'color' => 'success',
			],
			'update' => [
				'label' => Yii::t('common', 'Update'),
				'color' => 'primary',
			],
			'soft-delete' => [
				'label' => Yii::t('common', 'Soft Delete'),
				'color' => 'danger',
			],
			'delete' => [
				'label' => Yii::t('common', 'Delete'),
				'color' => 'danger',
			],
			'restore' => [
				'label' => Yii::t('common', 'Restore'),
				'color' => 'success',
			],
			'import' => [
				'label' => Yii::t('common', 'Import'),
				'color' => 'info',
			],
			'recover' => [
				'label' => Yii::t('common', 'Recover'),
				'color' => 'warning',
			],
		];
	}

	/**
	 * Gets the unserialized initial data.
	 *
	 * @return mixed
	 */
	public function getInitialData()
	{
		return @unserialize(file_get_contents(Yii::getAlias("@runtime/eventlogs/{$this->uuid}/{$this->initial_data}")));
	}

	/**
	 * Gets the unserialized final data.
	 *
	 * @return mixed
	 */
	public function getFinalData()
	{
		return @unserialize(file_get_contents(Yii::getAlias("@runtime/eventlogs/{$this->uuid}/{$this->final_data}")));
	}

	/**
	 * Gets the masked model name.
	 *
	 * @return mixed
	 */
	public function getMaskedModel()
	{
		return Yii::$app->security->maskToken($this->model);
	}

	/**
	 * Gets the formatted operation.
	 *
	 * @return mixed
	 */
	public function getFormattedOperation()
	{
		$action = self::getActions()[$this->operation];

		if (!$action) {
			return $this->operation;
		}

		return Html::tag('span', $action['label'], ['class' => 'label label-' . $action['color']]);
	}

	/**
	 * Gets the translated resource.
	 *
	 * @return mixed
	 */
	public function getTranslatedResource()
	{
		return Yii::t('label',  $this->resource);
	}

	/**
	 * Gets the resource name.
	 *
	 * @return mixed
	 */
	public function getResourceName()
	{
		$name = self::extractNameFromModel($this->getFinalData());

		if (!$name) {
			$name = self::extractNameFromModel($this->getInitialData());
		}

		return trim("({$this->model_key}) {$name}");
	}

	/**
	 * Extracts the name from an object.
	 *
	 * @param \yii\db\ActiveRecord $model
	 * @return null|string
	 */
	public static function extractNameFromModel($model)
	{
		$name = null;

		if (!isset($model) || !is_object($model)) {
			return $name;
		}

		if (isset($model->translation) && isset($model->translation->name)) {
			$name = $model->translation->name;
		} elseif (isset($model->fullName)) {
			$name = $model->fullName;
		} elseif (isset($model->name)) {
			if (is_array($model->name)) {
				$name = $model->name[Yii::$app->language];
			} else {
				$name = $model->name;
			}
		}

		return $name;
	}

	/**
	 * Finds all records grouped by resource column.
	 *
	 * @return array|\yii\db\ActiveRecord[]
	 */
	public static function findDistinctResources()
	{
		// Only `resource` is read from these records (the grid filter maps resource =>
		// translatedResource), and selecting id/model/deleted next to GROUP BY resource
		// is what MySQL rejects under only_full_group_by — its default — with a 1055.
		return static::find()
			->select([
				'resource',
			])
			->distinct()
			->where([
				'deleted' => self::NO,
			])
			->orderBy(['resource' => SORT_ASC])
			->all();
	}
}
