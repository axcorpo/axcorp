<?php

namespace common\widgets\datatable;

use yii\helpers\Html;
use yii\web\JsExpression;

class RadioColumn extends BaseDataTableColumn
{
	/**
	 * @inheritdoc
	 */
	public $className = 'radio-column col-autowidth text-center';

	/**
	 * @inheritdoc
	 */
	public $searchable = false;

	/**
	 * @inheritdoc
	 */
	public $orderable = false;

	/**
	 * @var array Radio configuration.
	 */
	public $radio = [
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
		// Create a custom renderer
		$this->render = $this->buildRender();
	}

	/**
	 * Gets a custom radio HTML tag.
	 *
	 * @param $value
	 * @param $suffix
	 * @return string
	 */
	protected function getRadio($value = 1, $suffix = null)
	{
		// Get the radio name from configuration
		$name = $this->radio['name'];
		// Append the suffix for radio name
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
		// Return a custom radio
		return Html::radio($name, false, [
			'label' => '',
			'labelOptions' => [
				'class' => 'mt-radio mt-radio-outline',
			],
			'value' => $value,
		]);
	}

	/**
	 * Builds a custom renderer.
	 *
	 * @return JsExpression
	 */
	protected function buildRender()
	{
		return new JsExpression("function (data, type, row, meta) {
			return '" . $this->getRadio('__VALUE__') . "'.replace('__VALUE__', row['" . $this->radio['valueAttribute'] . "']);
		}");
	}
}