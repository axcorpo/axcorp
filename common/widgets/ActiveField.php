<?php

namespace common\widgets;

class ActiveField extends \yii\bootstrap\ActiveField
{
	/**
	 * @var string the template for checkboxes in default layout
	 */
	public $checkboxTemplate = "<div class=\"checkbox\">\n{beginLabel}\n{input}\n{labelTitle}\n<span></span>{endLabel}\n{error}\n{hint}\n</div>";

	/**
	 * @var string the template for radios in default layout
	 */
	public $radioTemplate = "<div class=\"radio\">\n{beginLabel}\n{input}\n{labelTitle}\n<span></span>{endLabel}\n{error}\n{hint}\n</div>";

	/**
	 * @var string the template for checkboxes in horizontal layout
	 */
	public $horizontalCheckboxTemplate = "{beginWrapper}\n<div class=\"checkbox\">\n{beginLabel}\n{input}\n{labelTitle}\n<span></span>{endLabel}\n</div>\n{error}\n{endWrapper}\n{hint}";

	/**
	 * @var string the template for radio buttons in horizontal layout
	 */
	public $horizontalRadioTemplate = "{beginWrapper}\n<div class=\"radio\">\n{beginLabel}\n{input}\n{labelTitle}\n<span></span>{endLabel}\n</div>\n{error}\n{endWrapper}\n{hint}";

	/**
	 * @var string the template for inline checkboxLists
	 */
	public $inlineCheckboxListTemplate = "{label}\n{beginWrapper}\n{input}\n{error}\n{endWrapper}\n{hint}";

	/**
	 * @var string the template for inline radioLists
	 */
	public $inlineRadioListTemplate = "{label}\n{beginWrapper}\n{input}\n{error}\n{endWrapper}\n{hint}";
}
