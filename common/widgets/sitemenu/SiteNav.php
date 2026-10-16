<?php

namespace common\widgets\sitemenu;

use kartik\nav\NavX;

class SiteNav extends NavX
{
	use SiteMenuTrait;

	const ICON_POSITION_LEFT = 'left';
	const ICON_POSITION_RIGHT = 'right';

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->items = $this->buildItems($this->items);
	}
}