<?php

namespace common\widgets\datatable;

use yii\web\JsExpression;

class SerialColumn extends BaseDataTableColumn
{
	/**
	 * @inheritdoc
	 */
	public $title = '#';

	/**
	 * @inheritdoc
	 */
	public $className = 'serial-column col-autowidth text-center';

	/**
	 * @inheritdoc
	 */
	public $searchable = false;

	/**
	 * @inheritdoc
	 */
	public $orderable = false;

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
	 * Builds a custom renderer.
	 *
	 * @return JsExpression
	 */
	protected function buildRender()
	{
		return new JsExpression("function (data, type, row, meta) {
			return (meta.row + 1);
		}");
	}
}