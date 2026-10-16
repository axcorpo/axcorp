<?php

namespace common\widgets\datatable;

use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\web\JsExpression;

class CheckboxColumn extends BaseDataTableColumn
{
	/**
	 * @inheritdoc
	 */
	public $className = 'checkbox-column col-autowidth text-center';

	/**
	 * @inheritdoc
	 */
	public $searchable = false;

	/**
	 * @inheritdoc
	 */
	public $orderable = false;

	/**
	 * @var array Checkbox configuration.
	 */
	public $checkbox = [
		'name' => 'selection[]',
		'valueAttribute' => 'id',
	];

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		// Call the parent
		parent::init();
		// Set the header checkbox
		$this->title = $this->getCheckbox(1, '_all');
		// Create a custom renderer
		$this->render = $this->buildRender();
	}

	/**
	 * Gets a custom checkbox HTML tag.
	 *
	 * @param $value
	 * @param $suffix
	 * @return string
	 */
	protected function getCheckbox($value = 1, $suffix = null)
	{
		$checkboxOptions = array_merge([
			'value' => $value,
			'label' => '',
			'labelOptions' => [
				'class' => 'mt-checkbox mt-checkbox-outline',
			],
		], $this->checkbox);
		ArrayHelper::remove($checkboxOptions, 'valueAttribute');
		// Get the checkbox name from configuration
		$name = ArrayHelper::remove($checkboxOptions, 'name');
		// Append the suffix for checkbox name
		if (!is_null($suffix)) {
			// Get the name as array parts
			$parts = array_filter(explode('|', preg_replace('/\[|\]/', '|', $name)));
			if (count($parts) === 1) {
				$name = $parts[0] . $suffix;
			} else {
				$name = '';
				foreach ($parts as $key => $part) {
					if ($key == 0) {
						$name .= $part;
					} elseif ($key == 1) {
						$name .= '[' . ($part . $suffix) . ']';
					} else {
						$name .= "[{$part}]";
					}
				}
			}
		}
		// Return a custom checkbox
		return Html::checkbox($name, false, $checkboxOptions);
	}

	/**
	 * Builds a custom renderer.
	 *
	 * @return string
	 */
	protected function buildRender()
	{
		if (!empty($this->render)) {
			return $this->render;
		}

		return new JsExpression("function (data, type, row, meta) {
			return '" . $this->getCheckbox('__VALUE__') . "'.replace('__VALUE__', row['" . $this->checkbox['valueAttribute'] . "']);
		}");
	}
}