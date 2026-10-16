<?php

namespace common\widgets\sitemenu;

use yii\widgets\Menu;

class SiteMenu extends Menu
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