<?php

namespace common\widgets;

use Yii;

class ActiveForm extends \yii\bootstrap\ActiveForm
{
	/**
	 * @inheritdoc
	 */
	public $fieldClass = 'common\widgets\ActiveField';

}
