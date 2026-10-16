<?php

namespace common\models;

/**
 * This is the base model class for the common ActiveQuery models.
 */
class CommonActiveQuery extends \yii\db\ActiveQuery
{
	/**
	 * @var string The model table alias.
	 */
	private $_tableAlias;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
	}

	/**
	 * Gets the table alias of the model.
	 *
	 * @return string
	 */
	public function getTableAlias()
	{
		if (!$this->_tableAlias) {
			$this->_tableAlias = $this->getTableNameAndAlias()[1];
		}
		return $this->_tableAlias;
	}

	/**
	 * Adds default condition.
	 *
	 * @param bool $state
	 * @return self
	 */
	public function default($state = true)
	{
		return $this->andWhere(["{$this->getTableAlias()}.default" => $state === true ? 1 : 0]);
	}

	/**
	 * Adds status condition.
	 *
	 * @param bool $state
	 * @return self
	 */
	public function active($state = true)
	{
		return $this->andWhere(["{$this->getTableAlias()}.status" => $state === true ? 1 : 0]);
	}

	/**
	 * Adds deleted condition.
	 *
	 * @param bool $state
	 * @return self
	 */
	public function deleted($state = true)
	{
		return $this->andWhere(["{$this->getTableAlias()}.deleted" => $state === true ? 1 : 0]);
	}

	/**
	 * Adds descending order.
	 *
	 * @param string $attribute
	 * @return self
	 */
	public function recent($attribute = 'created_at')
	{
		return $this->addOrderBy(["{$this->getTableAlias()}.{$attribute}" => SORT_DESC]);
	}
}
