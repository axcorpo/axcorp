<?php

namespace common\widgets\datatable;

use tws\widgets\datetimepicker\DateTimePicker;
use kartik\number\NumberControl;
use kartik\select2\Select2;
use Yii;
use yii\helpers\Html;
use yii\web\JsExpression;

/**
 * Class BaseDataTableColumn
 * @package common\widgets\datatable
 * @author AlinHort <alinhort@gmail.com>
 * @see https://datatables.net/reference/option/columns
 */
class BaseDataTableColumn extends \yii\base\BaseObject
{
	/**
	 * @var string Cell type to be created for a column.
	 */
	public $cellType = 'td';

	/**
	 * @var string CSS Class to assign to each cell in the column.
	 */
	public $className;

	/**
	 * @var string Set the data source for the column from the rows data object / array.
	 */
	public $data;

	/**
	 * @var string Set default, static, content for a column.
	 */
	public $defaultContent;

	/**
	 * @var string Set a descriptive name for a column.
	 */
	public $name;

	/**
	 * @var bool Enable or disable ordering on this column.
	 */
	public $orderable = true;

	/**
	 * @var bool Enable or disable filtering on the data in this column.
	 */
	public $searchable = true;

	/**
	 * @var string Render (process) the data for use in the table.
	 */
	public $render;

	/**
	 * @var string Set the column title.
	 */
	public $title;

	/**
	 * @var string Set the column type - used for filtering and sorting string processing.
	 */
	public $type;

	/**
	 * @var bool Enable or disable the display of this column.
	 */
	public $visible = true;

	/**
	 * @var string|int|double Column width assignment.
	 */
	public $width;

	/**
	 * @var string|null Cell created callback to allow DOM manipulation.
	 */
	public $createdCell = null;

	// Custom
	/**
	 * @var string|array Column filter content.
	 */
	public $filter;

	/**
	 * @inheritdoc
	 * @throws \Exception
	 */
	public function init()
	{
		// Call the parent
		parent::init();
		// Init defaults
		$this->initDefaults();
		// If a custom render is already set then use it
		if (isset($this->render)) {
			// Ensure that the render is always and instance of JsExpression
			if (!($this->render instanceof JsExpression)) {
				$this->render = new JsExpression($this->render);
			}
		}
	}

	/**
	 * Initializes default attributes.
	 * @throws \Exception
	 */
	protected function initDefaults()
	{
		// Ensure default filter by specified type
		if (isset($this->filter) && is_array($this->filter)) {
			// Set the proper filter
			$this->filter = $this->getDefaultFilter($this->filter[0], $this->filter[1]);
		}
	}

	/**
	 * Gets the default filter by type.
	 *
	 * @param string $type
	 * @param string|array|null $data
	 * @return mixed
	 * @throws \Exception
	 */
	protected function getDefaultFilter($type = 'text', $data = null)
	{
		// Test the type
		switch ($type) {
			case 'clear':
				$filter = Html::tag('button', '<span class="fa fa-times"></span>', array_merge_recursive([
					'type' => 'button',
					'class' => 'btn btn-xs btn-default',
					'title' => Yii::t('common', 'Clear column filters'),
					'data' => [
						'toggle' => 'tooltip',
						'dt-clear-filters' => true,
					],
				], is_array($data) ? $data : []));
				break;
			case 'text':
				$filter = Html::tag('input', null, array_merge_recursive([
					'type' => 'text',
					'class' => 'form-control',
					'placeholder' => Yii::t('common', 'Filter'),
				], is_array($data) ? $data : []));
				break;
			case 'number':
				$filter = NumberControl::widget(array_merge_recursive([
					'name' => '',
					'maskedInputOptions' => [
						'groupSeparator' => '.',
						'radixPoint' => ',',
						'allowMinus' => false,
					],
					'displayOptions' => [
						'placeholder' => Yii::t('common', 'Filter'),
					],
				], is_array($data) ? $data : []));
				break;
			case 'date':
				$filter = DateTimePicker::widget([
					'id' => 'dp-' . rand(0, 9999),
					'name' => '',
					'options' => [
						'placeholder' => Yii::t('common', 'Filter'),
					],
					'clientOptions' => [
						'format' => ($data ?: 'YYYY-MM-DD HH:mm:ss'),
						'ignoreReadonly' => true,
						'showTodayButton' => true,
						'showClear' => true,
						'showClose' => true,
						'allowInputToggle' => true,
						'useCurrent' => false,
						'widgetParent' => 'body',
					],
				]);
				break;
			case 'select':
				$filter = Select2::widget([
					'name' => '',
					'data' => is_array($data) ? $data : [],
					'pluginLoading' => false,
					'pluginOptions' => [
						'allowClear' => true,
						'placeholder' => Yii::t('common', 'Filter'),
					],
				]);
				break;
			default:
				$filter = null;
				break;
		}
		// Return the filter based on the type
		return $filter;
	}
}
