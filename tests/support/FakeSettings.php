<?php

namespace tests\support;

use yii\base\Component;

/**
 * Test double for the tws\settings\Settings component: returns values from a plain
 * array instead of the `setting` table.
 */
class FakeSettings extends Component
{
	/**
	 * @var array category => [key => value]
	 */
	public $values = [];

	/**
	 * @param string $key
	 * @param string $category
	 * @param mixed $default
	 * @return mixed
	 */
	public function get($key, $category = 'general', $default = null)
	{
		if (array_key_exists($category, $this->values) && array_key_exists($key, $this->values[$category])) {
			return $this->values[$category][$key];
		}
		return $default;
	}

	/**
	 * @param string $category
	 * @return array
	 */
	public function getCategory($category)
	{
		return isset($this->values[$category]) ? $this->values[$category] : [];
	}

	/**
	 * @return array every category's values, keyed by category
	 */
	public function getAll()
	{
		return $this->values;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @param string $category
	 */
	public function set($key, $value, $category = 'general')
	{
		$this->values[$category][$key] = $value;
	}
}
