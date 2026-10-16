<?php

namespace common\widgets\datatable;

use Closure;
use yii\helpers\Json;
use yii\web\JsExpression;

class FieldColumn extends BaseDataTableColumn
{
	/**
	 * @inheritdoc
	 */
	public $className = 'field-column';

	/**
	 * @var string|array The cell content.
	 */
	public $content = '';

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->render = $this->buildRender();
	}

	/**
	 * Renders the content.
	 *
	 * @return null|string
	 * @throws \Exception
	 */
	protected function renderContent()
	{
		if ($this->content instanceof Closure) {
			return call_user_func($this->content);
		} elseif (is_array($this->content)) {
			return implode('', $this->content);
		}
		return $this->content;
	}

	/**
	 * Builds a custom renderer.
	 *
	 * @return JsExpression|string
	 * @throws \Exception
	 */
	protected function buildRender()
	{
		// If a custom render is set
		if (!empty($this->render)) {
			return $this->render;
		}
		// Create a new render function
		return new JsExpression("function (data, type, row, meta) {
			return " . Json::encode($this->renderContent()) . ";
		}");
	}
}