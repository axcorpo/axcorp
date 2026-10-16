<?php

namespace backend\widgets;

use yii\helpers\Html;

class ActiveField extends \yii\bootstrap\ActiveField
{
	/**
	 * @inheritdoc
	 */
	public $checkboxTemplate = "{beginLabel}\n{input}\n{labelTitle}\n<span></span>\n{endLabel}\n{error}\n{hint}";

	/**
	 * @inheritdoc
	 */
	public $radioTemplate = "{beginLabel}\n{input}\n{labelTitle}\n<span></span>\n{endLabel}\n{error}\n{hint}";

	/**
	 * @inheritdoc
	 */
	public $horizontalCheckboxTemplate = "{beginWrapper}\n{beginLabel}\n{input}\n{labelTitle}\n<span></span>\n{endLabel}\n{error}\n{endWrapper}\n{hint}";

	/**
	 * @inheritdoc
	 */
	public $horizontalRadioTemplate = "{beginWrapper}\n{beginLabel}\n{input}\n{labelTitle}\n<span></span>\n{endLabel}\n{error}\n{endWrapper}\n{hint}";

	/**
	 * @inheritdoc
	 */
	public $inlineCheckboxListTemplate = "{label}\n{beginWrapper}\n{input}\n{error}\n{endWrapper}\n{hint}";

	/**
	 * @inheritdoc
	 */
	public $inlineRadioListTemplate = "{label}\n{beginWrapper}\n{input}\n{error}\n{endWrapper}\n{hint}";

	/**
	 * @inheritdoc
	 */
	public function checkbox($options = [], $enclosedByLabel = true)
	{
		if ($enclosedByLabel) {
			if (!isset($options['template'])) {
				$this->template = $this->form->layout === 'horizontal' ?
					$this->horizontalCheckboxTemplate : $this->checkboxTemplate;
			} else {
				$this->template = $options['template'];
				unset($options['template']);
			}
			if (isset($options['label'])) {
				$this->parts['{labelTitle}'] = $options['label'];
			}
			if ($this->form->layout === 'horizontal') {
				Html::addCssClass($this->wrapperOptions, $this->horizontalCssClasses['offset']);
			}
			$this->labelOptions = array_merge_recursive(
				['class' => 'mt-checkbox mt-checkbox-outline'],
				(array) $options['labelOptions']
			);
		}

		return parent::checkbox($options, false);
	}

	/**
	 * @inheritdoc
	 */
	public function radio($options = [], $enclosedByLabel = true)
	{
		if ($enclosedByLabel) {
			if (!isset($options['template'])) {
				$this->template = $this->form->layout === 'horizontal' ?
					$this->horizontalRadioTemplate : $this->radioTemplate;
			} else {
				$this->template = $options['template'];
				unset($options['template']);
			}
			if (isset($options['label'])) {
				$this->parts['{labelTitle}'] = $options['label'];
			}
			if ($this->form->layout === 'horizontal') {
				Html::addCssClass($this->wrapperOptions, $this->horizontalCssClasses['offset']);
			}
			$this->labelOptions = array_merge_recursive(
				['class' => 'mt-radio mt-radio-outline'],
				(array) $options['labelOptions']
			);
		}

		return parent::radio($options, false);
	}

	/**
	 * @inheritdoc
	 */
	public function checkboxList($items, $options = [])
	{
		if (!isset($options['itemOptions'])) {
			$options['itemOptions'] = [
				'labelOptions' => ['class' => 'mt-checkbox mt-checkbox-outline'],
			];
		}
		if ($this->inline) {
			if (!isset($options['template'])) {
				$this->template = $this->inlineCheckboxListTemplate;
			} else {
				$this->template = $options['template'];
				unset($options['template']);
			}
			if (!isset($options['class'])) {
				$options['class'] = 'mt-checkbox-inline';
			}
		} elseif (!isset($options['item'])) {
			if (!isset($options['class'])) {
				$options['class'] = 'mt-radio-list';
			}
			$itemOptions = isset($options['itemOptions']) ? $options['itemOptions'] : [];
			$options['item'] = function ($index, $label, $name, $checked, $value) use ($itemOptions) {
				$options = array_merge(['label' => $label, 'value' => $value], $itemOptions);
				return Html::checkbox($name, $checked, $options);
			};
		}
		parent::checkboxList($items, $options);
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function radioList($items, $options = [])
	{
		if (!isset($options['itemOptions'])) {
			$options['itemOptions'] = [
				'labelOptions' => ['class' => 'mt-radio mt-checkbox-outline'],
			];
		}
		if ($this->inline) {
			if (!isset($options['template'])) {
				$this->template = $this->inlineRadioListTemplate;
			} else {
				$this->template = $options['template'];
				unset($options['template']);
			}
			if (!isset($options['class'])) {
				$options['class'] = 'mt-radio-inline';
			}
		} elseif (!isset($options['item'])) {
			if (!isset($options['class'])) {
				$options['class'] = 'mt-radio-list';
			}
			$itemOptions = isset($options['itemOptions']) ? $options['itemOptions'] : [];
			$options['item'] = function ($index, $label, $name, $checked, $value) use ($itemOptions) {
				$options = array_merge(['label' => $label, 'value' => $value], $itemOptions);
				return Html::radio($name, $checked, $options);
			};
		}
		parent::radioList($items, $options);
		return $this;
	}
}
