<?php

namespace common\behaviors;

use yii\base\Behavior;
use yii\base\ModelEvent;
use yii\db\BaseActiveRecord;

/**
 * This behavior automatically updates attributes of indirect related models.
 *
 * @property BaseActiveRecord $owner owner ActiveRecord instance.
 *
 * @author Tree Web Solutions Team <treewebsolutions.com@gmail.com>
 * @todo this behavior is experimental. Needs more use case scenarios and more tests.
 */
class SyncRelatedModelBehavior extends Behavior
{
	/**
	 * @var array The list of related ActiveRecord model class names.
	 */
	public $models = [];

	/**
	 * @var array The owner old attributes.
	 */
	private $_ownerOldAttributes;

	/**
	 * @inheritdoc
	 */
	public function events()
	{
		return [
			BaseActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
			BaseActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
			BaseActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
			BaseActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
			BaseActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
			BaseActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
		];
	}

	/**
	 * Creates array of group attributes with their values.
	 *
	 * @param array $attributes
	 * @return array The attribute conditions.
	 */
	protected function getOwnerAttributes($attributes)
	{
		$stack = [];

		if (!empty($attributes)) {
			foreach ($attributes as $attribute) {
				if ($this->_ownerOldAttributes[$attribute]) {
					$stack[$attribute] = $this->_ownerOldAttributes[$attribute];
				} else {
					$stack[$attribute] = $this->owner->$attribute;
				}
			}
		}

		return $stack;
	}

	/**
	 * Prepares the related model attributes.
	 *
	 * @param array $attributes
	 * @return array
	 */
	protected function prepareRelatedModelAttributes($attributes)
	{
		$stack = [];

		if (!empty($attributes)) {
			foreach ($attributes as $k => $v) {
				if ($this->owner->hasAttribute($v)) {
					$stack[$v] = $this->owner->$v;
				} else {
					$stack[$k] = $v;
				}
			}
		}

		return $stack;
	}

	/**
	 * Gets the ActiveRecord instance of a model class.
	 *
	 * @param string $modelClass
	 * @param string|array|\Closure $filterBy
	 * @return \yii\db\ActiveRecord
	 */
	protected function getRelatedModelInstance($modelClass, $filterBy)
	{
		/** @var \yii\db\ActiveRecord $modelClass */
		$query = $modelClass::find();

		if ($filterBy instanceof \Closure) {
			call_user_func($filterBy, $query);
		} else {
			$query->andWhere($this->getOwnerAttributes((array) $filterBy));
		}

		if (!($model = $query->one())) {
			$model = new $modelClass;
		}

		return $model;
	}

	/**
	 * Handles beforeSave event.
	 *
	 * @param ModelEvent $event
	 */
	public function beforeSave($event)
	{
		$this->_ownerOldAttributes = $this->owner->oldAttributes;
	}

	/**
	 * Handles beforeDelete event.
	 *
	 * @param ModelEvent $event
	 */
	public function beforeDelete($event)
	{
		$this->_ownerOldAttributes = $this->owner->oldAttributes;
	}

	/**
	 * Handles afterSave event.
	 *
	 * @param ModelEvent $event
	 */
	public function afterSave($event)
	{
		try {
			foreach ($this->models as $k => $v) {
				$model = $this->getRelatedModelInstance($k, $v['filterBy'] ?: $v['afterSave']['attributes']);
				$model->setAttributes($this->prepareRelatedModelAttributes($v['afterSave']['attributes']));
				$model->save();
			}
		} catch (\Exception $e) {
		}
	}

	/**
	 * Handles afterDelete event.
	 *
	 * @param ModelEvent $event
	 */
	public function afterDelete($event)
	{
		try {
			foreach ($this->models as $k => $v) {
				$model = $this->getRelatedModelInstance($k, $v['filterBy'] ?: $v['afterDelete']['attributes']);
				$model->setAttributes($this->prepareRelatedModelAttributes($v['afterDelete']['attributes']));
				$model->save();
			}
		} catch (\Exception $e) {
		}
	}
}
