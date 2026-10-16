<?php

namespace backend\widgets;

use Yii;

class ActiveForm extends \yii\bootstrap\ActiveForm
{
	/**
	 * @inheritdoc
	 */
	public $fieldClass = 'backend\widgets\ActiveField';

}
