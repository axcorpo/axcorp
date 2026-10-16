<?php

namespace common\widgets;

use yii\helpers\Html;

class FloatingActiveField extends \yii\widgets\ActiveField
{
	/**
	 * @inheritdoc
	 */
	public $template = "{input}\n{label}\n{hint}\n{error}";

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		// Get proper attribute name when attribute name is tabular.
		$attributeName = Html::getAttributeName($this->attribute);

		if (isset($this->model->$attributeName)) {
			Html::addCssClass($this->inputOptions, 'has-value');
		}

		parent::init();
	}
}
