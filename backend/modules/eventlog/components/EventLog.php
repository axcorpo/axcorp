<?php

namespace backend\modules\eventlog\components;

use ReflectionMethod;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\db\BaseActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;

/**
 * EventLog component automatically adds log records to the database for an ActiveRecord model.
 *
 * @property \yii\db\ActiveRecord $owner The ActiveRecord owner instance.
 *
 * @author Alin Hort <alinhort@gmail.com>
 */
class EventLog extends Component
{
	const ACTION_LOGIN = 'login';
	const ACTION_LOGOUT = 'logout';
	const ACTION_CREATE = 'create';
	const ACTION_UPDATE = 'update';
	const ACTION_SOFT_DELETE = 'soft-delete';
	const ACTION_DELETE = 'delete';
	const ACTION_RESTORE = 'restore';
	const ACTION_IMPORT = 'import';
	const ACTION_RECOVER = 'recover';

	/**
	 * @var \yii\db\ActiveRecord The component model used to save logs.
	 */
	public $model;

	/**
	 * @var bool Flag that indicates if event logs should be recorded.
	 */
	public $enabled = true;

	/**
	 * @var \yii\db\ActiveRecord The owner model which must be recorded.
	 */
	public $owner;

	/**
	 * @var array|boolean The owner model relations list for which this component will save data.
	 * array = only the specified owner model relations.
	 * true = all owner model relations.
	 * false = no owner model relation.
	 */
	public $relations = true;

	/**
	 * @var array The custom data to be recorded.
	 */
	public $data = [];

	/**
	 * @var array The custom attributes to be merged with the final data.
	 */
	private $_finalDataAttributes = [];

	/**
	 * @var array The owner model attributes right after model find operation.
	 */
	private $_oldAttributes = [];

	/**
	 * @inheritdoc
	 * @throws \yii\base\InvalidConfigException
	 */
	public function init()
	{
		parent::init();

		if (!isset($this->model) || !is_subclass_of($this->model, '\yii\db\ActiveRecord')) {
			throw new InvalidConfigException('The "model" attribute must be a subclass of the ActiveRecord class.');
		}
		if (isset($this->owner) && !is_subclass_of($this->owner, '\yii\db\ActiveRecord')) {
			throw new InvalidConfigException('The "owner" attribute must be a subclass of the ActiveRecord class.');
		}
	}

	/**
	 * Initializes old attributes for the current owner model.
	 *
	 * @param \yii\db\ActiveRecord|null $owner
	 * @param array|null $relations
	 * @throws \ReflectionException
	 */
	public function beginRecord($owner = null, $relations = null)
	{
		if ($this->enabled === true) {
			if (isset($owner)) {
				$this->owner = $owner;
			}
			if (isset($relations)) {
				$this->relations = $relations;
			}
			if (!$this->owner->isNewRecord) {
				$this->setOldAttributes($this->getOwnerAttributes());
			}
		}
	}

	/**
	 * Checks if there are differences between the old and the new attributes and saves the event log to database.
	 *
	 * @param bool $forceSave Flag that indicates if the event log will be performed even there are no differences.
	 * @throws \ReflectionException
	 * @throws \yii\base\Exception
	 */
	public function endRecord($forceSave = false)
	{
		if ($this->enabled === true) {
			$oldAttributes = $this->getOldAttributes();
			$newAttributes = $this->getOwnerAttributes();

			// Merge the attributes if required
			if (!empty($this->_finalDataAttributes)) {
				$newAttributes->setAttributes($this->_finalDataAttributes);
			}

			// Serialize the attributes
			$oldAttributes = serialize($oldAttributes);
			$newAttributes = serialize($newAttributes);

			if ($forceSave === true || $oldAttributes !== $newAttributes) {
				$this->saveEventLog([
					'initial_data' => $oldAttributes,
					'final_data' => $newAttributes,
				]);
			}
		}
	}

	/**
	 * Saves an EventLog record.
	 *
	 * @param array $attributes The model attributes.
	 * @return bool The successful or the failure of the model save operation.
	 * @throws \yii\base\Exception
	 */
	public function saveEventLog($attributes)
	{
		if ($this->enabled === true) {
			$ownerArSubclass = $this->getOwnerArSubclass($this->owner);

			// Merge defaults with the custom and the provided attributes
			$attributes = ArrayHelper::merge([
				'user_id' => Yii::$app->user->id,
				// UUID string, never the raw BINARY(16) primary key (model_key is a VARCHAR column)
				'model_key' => $this->owner instanceof \common\models\UuidActiveRecord
					? $this->owner->getUuid()
					: $this->owner->getPrimaryKey(),
				'model' => $ownerArSubclass,
				'module' => $this->getModulePath(),
				'controller' => Yii::$app->controller->id,
				'action' => Yii::$app->controller->action->id,
				'operation' => Yii::$app->controller->action->id,
				'resource' => Inflector::titleize(end(explode('\\', $ownerArSubclass)), true),
				'ip_address' => Yii::$app->request->userIP,
			], $this->data, $attributes);

			return $this->create($attributes);
		}
		return false;
	}

	/**
	 * Creates a new log record.
	 *
	 * @param $attributes
	 * @return bool
	 * @throws \yii\base\Exception
	 */
	public function create($attributes)
	{
		// Ensure that model_key attribute is always a string
		if (isset($attributes['model_key'])) {
			$attributes['model_key'] = (string) $attributes['model_key'];
		}

		// Extract the initial and final data from the attributes
		$initialData = ArrayHelper::remove($attributes, 'initial_data');
		$finalData = ArrayHelper::remove($attributes, 'final_data');

		/** @var \yii\db\ActiveRecord $model */
		$model = new $this->model;
		$model->setAttributes($attributes);

		if ($result = $model->save()) {
			$filePath = Yii::getAlias("@runtime/eventlogs/{$model->uuid}");
			FileHelper::createDirectory($filePath);

			file_put_contents("{$filePath}/initial_data.log", $initialData);
			file_put_contents("{$filePath}/final_data.log", $finalData);

			$model->initial_data = "initial_data.log";
			$model->final_data = "final_data.log";
			$model->save(false, ['initial_data', 'final_data']);
		}

		return $result;
	}

	/**
	 * Gets the array representation of the owner class attributes with/without its relations.
	 *
	 * @return array|\yii\db\ActiveRecord
	 * @throws \ReflectionException
	 */
	public function getOwnerAttributes()
	{
		$relations = [];

		if ($this->relations === true || is_array($this->relations)) {
			$ownerRelations = $this->getOwnerRelations();

			if ($this->relations === true) {
				// Get all relations
				$relations = $ownerRelations;
			} elseif (is_array($relations)) {
				// Filter out the relations that does not exist in the owner model
				$relations = array_intersect($this->relations, $ownerRelations);
			}
		}

		// Find the owner model with its relations by its primary keys
		$condition = $this->owner->getPrimaryKey(true);
		$owner = ($this->owner)::find()->with($relations)->where($condition)->one();

		// Return empty array if the owner model was not found
		if (!$owner) {
			return [];
		}

		// Clean the owner and the related models by removing all unnecessary data
		$this->cleanModel($owner);

		foreach ($owner->getRelatedRecords() as $relatedRecord) {
			if (is_array($relatedRecord)) {
				foreach ($relatedRecord as $val) {
					$this->cleanModel($val);
				}
			} else {
				$this->cleanModel($relatedRecord);
			}
		}

		// Update the current owner instance
		$this->owner = $owner;

		return $this->owner;
	}

	/**
	 * Detaches all model events and behaviors.
	 *
	 * @param $model \yii\db\ActiveRecord
	 */
	public function cleanModel($model)
	{
		if ($model) {
			$model->off(BaseActiveRecord::EVENT_INIT);
			$model->off(BaseActiveRecord::EVENT_AFTER_FIND);
			$model->off(BaseActiveRecord::EVENT_BEFORE_INSERT);
			$model->off(BaseActiveRecord::EVENT_AFTER_INSERT);
			$model->off(BaseActiveRecord::EVENT_BEFORE_UPDATE);
			$model->off(BaseActiveRecord::EVENT_AFTER_UPDATE);
			$model->off(BaseActiveRecord::EVENT_BEFORE_DELETE);
			$model->off(BaseActiveRecord::EVENT_AFTER_DELETE);
			$model->off(BaseActiveRecord::EVENT_AFTER_REFRESH);

			$model->detachBehaviors();

			$validatorsCount = count($model->getValidators());
			for ($i = 0; $i < $validatorsCount; $i++) {
				unset($model->validators[$i]);
			}
		}
	}

	/**
	 * Gets all the relations for the owner class.
	 *
	 * @return array
	 * @throws \ReflectionException
	 */
	public function getOwnerRelations()
	{
		$ownerClass = $this->getOwnerArSubclass($this->owner);
		$reflector = new \ReflectionClass($ownerClass);
		$baseClassMethods = get_class_methods('yii\db\ActiveRecord');
		$stack = [];

		foreach ($reflector->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if (
				$method->isStatic() ||
				in_array($method->name, $baseClassMethods) ||
				substr($method->name, 0, 3) !== 'get' ||
				$method->class !== $ownerClass
			) {
				continue;
			}

			$methodString = self::methodToString($ownerClass, $method->name);

			if (strpos($methodString, '->hasOne') !== false || strpos($methodString, '->hasMany') !== false) {
				try {
					$relation = call_user_func([$this->owner, $method->name]);

					if ($relation instanceof \yii\db\ActiveQuery) {
						$stack[] = lcfirst(str_replace('get', '', $method->name));
					}
				} catch (\Exception $e) {
					continue;
				}
			}
		}

		return $stack;
	}

	/**
	 * Gets the owner model ActiveRecord subclass.
	 *
	 * @param $model
	 * @return string
	 */
	public function getOwnerArSubclass($model)
	{
		$model = is_string($model) ? $model : get_class($model);

		if (strpos($model, 'common\models') !== false) {
			return $model;
		}

		return $this->getOwnerArSubclass(get_parent_class($model));
	}

	/**
	 * Returns the string representation of a class method.
	 *
	 * @param $class
	 * @param $methodName
	 * @return string
	 */
	public static function methodToString($class, $methodName)
	{
		try {
			$func = new \ReflectionMethod($class, $methodName);

			$filename = $func->getFileName();
			$start_line = $func->getStartLine();
			$end_line = $func->getEndLine()-1;
			$length = $end_line - $start_line;
			$source = file($filename);

			return implode('', array_slice($source, $start_line, $length));
		} catch (\Exception $e) {
			return '';
		}
	}

	/**
	 * Gets the module path.
	 * This method handles only one level deep nested app modules (eg. app-module/parent-module/child-module.
	 * TODO: Needs recursion to handle unlimited deep nested app modules.
	 *
	 * @return string
	 */
	public function getModulePath()
	{
		$currentModule = Yii::$app->controller->module;
		$parentModule = $currentModule->module;
		$modulePath = [$currentModule->id];

		if (!($parentModule instanceof \yii\web\Application)) {
			array_unshift($modulePath, $parentModule->id);
		}

		return implode('/', $modulePath);
	}

	/**
	 * @return array
	 */
	public function getOldAttributes()
	{
		return $this->_oldAttributes;
	}

	/**
	 * @param array $oldAttributes
	 * @return EventLog
	 */
	public function setOldAttributes($oldAttributes)
	{
		$this->_oldAttributes = $oldAttributes;

		return $this;
	}

	/**
	 * @param \yii\db\ActiveRecord $owner
	 * @return EventLog
	 */
	public function setOwner($owner)
	{
		$this->owner = $owner;

		return $this;
	}

	/**
	 * @param array|boolean $relations
	 * @return EventLog
	 */
	public function setRelations($relations)
	{
		$this->relations = $relations;

		return $this;
	}

	/**
	 * @param array $data
	 * @return EventLog
	 */
	public function setData($data)
	{
		$this->data = $data;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getFinalDataAttributes()
	{
		return $this->_finalDataAttributes;
	}

	/**
	 * @param array $finalDataAttributes
	 * @return EventLog
	 */
	public function setFinalDataAttributes($finalDataAttributes)
	{
		$this->_finalDataAttributes = $finalDataAttributes;

		return $this;
	}
}
