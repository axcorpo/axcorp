<?php

namespace common\widgets;

use Yii;
use yii\helpers\Html;

class Actions extends \yii\base\Widget
{
	/**
	 * @var array the widget options.
	 */
	public $options = [];

	/**
	 * @var array the items for rendering the close button tag.
	 */
	public $items = [];

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->items = is_array($this->items) ? $this->items : [];
	}

	/**
	 * @inheritdoc
	 */
	public function run()
	{
		// Prevent widget run since there are no items
		if (empty($this->items)) {
			return null;
		}
		// Set the widget id
		$this->options['id'] = $this->id;
		// Generates the rendering result of the widget
		return Html::tag('div', $this->_renderItems(), $this->options);
	}

	/**
	 * Renders the proper content for each widget item.
	 *
	 * @return array
	 */
	private function _renderItems()
	{
		// Normalized items
		$items = [];
		// Loop through the items
		foreach ($this->items as $item) {
			// Continue with the next iteration if the current item should not be visible
			if (isset($item['visible']) && !$item['visible']) {
				continue;
			}
			// Icon
			$itemIcon = $item['icon'] ? Html::tag('i', null, ['class' => $item['icon']]) : '';
			// Content
			$itemContent = ($item['icon'] && $item['content']) ?
				($itemIcon . ' ' . $item['content']) :
				($itemIcon ?: $item['content']);
			// Render the proper HTML tags
			if ($item['content']) {
				// Treat it as raw content
				$items[] = $item['content'];
			} elseif (!$item['tag'] && !$item['url']) {
				// Treat it as raw content
				$items[] = $item[0];
			} elseif ($item['url']) {
				// Create an anchor tag
				$items[] = Html::a($itemContent, $item['url'], $item['options']);
			} else {
				// Create a custom tag
				$items[] = Html::tag($item['tag'], $itemContent, $item['options']);
			}
		}
		// Return the widget items
		return implode('', $items);
	}
}
