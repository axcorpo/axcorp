<?php

namespace backend\widgets;

use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use Yii;
use tws\helpers\Url;
use yii\widgets\ActiveForm as CoreActiveForm;

/**
 * Metronic menu displays a multi-level menu using nested HTML lists.
 *
 * The main property of Menu is [[items]], which specifies the possible items in the menu.
 * A menu item can contain sub-items which specify the sub-menu under that menu item.
 *
 * Menu checks the current route and request parameters to toggle certain menu items
 * with active state.
 *
 * Note that Menu only renders the HTML tags about the menu. It does do any styling.
 * You are responsible to provide CSS styles to make it look like a real menu.
 *
 * The following example shows how to use Menu:
 *
 * ```php
 * echo Menu::widget([
 *     'items' => [
 *         // Important: you need to specify url as 'controller/action',
 *         // not just as 'controller' even if default action is used.
 *         [
 *           'icon' => '',
 *           'label' => 'Home',
 *           'url' => ['site/index']
 *         ],
 *         // 'Products' menu item will be selected as long as the route is 'product/index'
 *         ['label' => 'Products', 'url' => ['product/index'], 'items' => [
 *             ['label' => 'New Arrivals', 'url' => ['product/index', 'tag' => 'new']],
 *             ['label' => 'Most Popular', 'url' => ['product/index', 'tag' => 'popular']],
 *         ]],
 *         ['label' => 'Login', 'url' => ['site/login'], 'visible' => Yii::$app->user->isGuest],
 *     ],
 *     'search' => [
 *         // required, whether search box is visible. Defaults to 'true'.
 *         'visible' => true,
 *         // optional, the configuration array for [[ActiveForm]].
 *         'form' => [],
 *         // optional, input options with default values
 *         'input' => [
 *             'name' => 'search',
 *             'value' => '',
 *             'options' => [
 *             'placeholder' => 'Search...',
 *         ]
 *     ],
 * ]
 * ]);
 * ```
 *
 */
class Menu extends \yii\widgets\Menu
{
	/**
	 * @inheritdoc
	 */
	public $firstItemCssClass = 'start';

	/**
	 * @inheritdoc
	 */
	public $lastItemCssClass = 'last';

	/**
	 * @inheritdoc
	 */
	public $submenuTemplate = "\n<ul class='sub-menu'>\n{items}\n</ul>\n";

	/**
	 * @inheritdoc
	 */
	public $linkTemplate = '{beginLink}{icon}{label}{arrow}{badge}{endLink}';

	/**
	 * @inheritdoc
	 */
	public $labelTemplate = '<h3 class="uppercase">{label}</h3>';

	/**
	 * @var array Item link options.
	 */
	public $linkOptions = ['class' => 'nav-link'];

	/**
	 * @var array Search options
	 * is an array of the following structure:
	 * ```php
	 * [
	 *   // required, whether search box is visible
	 *   'visible' => true,
	 *   // optional, ActiveForm options
	 *   'form' => [],
	 *   // optional, input options with default values
	 *   'input' => [
	 *     'name' => 'search',
	 *     'value' => '',
	 *     'options' => [
	 *       'placeholder' => 'Search...',
	 *     ]
	 *   ],
	 * ]
	 * ```
	 */
	public $search = ['visible' => true];

	/**
	 * @inheritdoc
	 */
	public $visible = true;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		$this->_initOptions();
	}

	/**
	 * @inheritdoc
	 */
	public function run()
	{
		if ($this->route === null && Yii::$app->controller !== null) {
			$this->route = Yii::$app->controller->getRoute();
		}
		if ($this->params === null) {
			$this->params = Yii::$app->request->getQueryParams();
		}

		echo Html::beginTag('div', ['class' => 'page-sidebar navbar-collapse collapse']);
		$items = $this->normalizeItems($this->items, $hasActiveChild);
		$options = $this->options;
		$tag = ArrayHelper::remove($options, 'tag', 'ul');
		$data = [Html::tag('li', Html::tag('div', '<span></span>', ['class' => 'sidebar-toggler']), ['class' => 'sidebar-toggler-wrapper hide'])];
		if (isset($this->search['visible']) && $this->search['visible']) {
			$data[] = Html::tag('li', $this->renderSearch(), ['class' => 'sidebar-search-wrapper']);
		}
		$data[] = $this->renderItems($items);
		echo Html::tag($tag, implode("\n", $data), $options);
		echo Html::endTag('div');
	}

	/**
	 * @inheritdoc
	 */
	protected function isItemActive($item)
	{
		// Do not activate the item if an exception is thrown
		if (Yii::$app->errorHandler->exception) {
			return false;
		}

		if (isset($item['url']) && is_array($item['url']) && isset($item['url'][0])) {
			$route = Yii::getAlias($item['url'][0]);
			if ($route[0] !== '/' && Yii::$app->controller) {
				$route = Yii::$app->controller->module->getUniqueId() . '/' . $route;
			}
			// Get route segments as array
			$routeSegments = array_values(array_filter(explode('/', $route)));
			// Get url path segments as array
			$pathSegments = array_values(array_filter(explode('/', Yii::$app->request->pathInfo)));
			// Module and controller ID
			$parentModuleId = Yii::$app->controller->getParentModuleId(true);
			$moduleId = Yii::$app->controller->module->id;
			$controllerId = Yii::$app->controller->id;
			$actionId = Yii::$app->controller->action->id;

			if (
				(in_array($moduleId, $routeSegments) && in_array($controllerId, $routeSegments)) &&
				($routeSegments[0] === $pathSegments[0] && $routeSegments[1] === $pathSegments[1]) &&
				in_array('index', $routeSegments) && count($item['url']) === 1 &&
				(in_array('view', $pathSegments) || in_array('update', $pathSegments) || in_array('create', $pathSegments) || in_array('translate', $pathSegments))
			) {
				return true;
			}

			if (
				$parentModuleId === $moduleId && in_array($moduleId, $routeSegments) && count($pathSegments) >= 4 &&
				($routeSegments[0] === $pathSegments[0] && $routeSegments[1] === $pathSegments[1])
			) {
				return true;
			}

			if (ltrim($route, '/') !== $this->route) {
				return false;
			}
			unset($item['url']['#']);
			if (count($item['url']) > 1) {
				$params = $item['url'];
				unset($params[0]);
				foreach ($params as $name => $value) {
					if ($value !== null && (!isset($this->params[$name]) || $this->params[$name] != $value)) {
						return false;
					}
				}
			}
			return true;
		}

		return false;
	}

	/**
	 * Renders search box
	 * @return string the rendering result
	 */
	public function renderSearch()
	{
		$defaultFormOptions = ['options' => ['class' => 'sidebar-search']];
		$defaultInputOptions = ['name' => 'search', 'value' => '', 'options' => ['class' => 'form-control', 'placeholder' => 'Search...']];
		$formOptions = ArrayHelper::merge($defaultFormOptions, ArrayHelper::getValue($this->search, 'form', []));
		$inputOptions = ArrayHelper::merge($defaultInputOptions, ArrayHelper::getValue($this->search, 'input', []));
		ob_start();
		ob_implicit_flush(false);
		CoreActiveForm::begin($formOptions);
		echo '<a href="javascript:;" class="remove"><i class="icon-close"></i></a>';
		echo '<div class="input-group">';
			echo Html::input('text', $inputOptions['name'],  $inputOptions['value'], $inputOptions['options']);
			echo '<span class="input-group-btn">';
				echo '<button type="submit" class="btn submit"><i class="icon-magnifier"></i></button>';
			echo '</span>';
		echo '</div>';
		CoreActiveForm::end();

		return ob_get_clean();
	}

	/**
	 * @inheritdoc
	 */
	protected function renderItems($items, $level = 1)
	{
		$n = count($items);
		$lines = [];
		foreach ($items as $i => $item) {
			$options = array_merge($this->itemOptions, ArrayHelper::getValue($item, 'options', []));
			$tag = ArrayHelper::remove($options, 'tag', 'li');
			$class = [];

			if ($item['active']) {
				$class[] = $this->activeCssClass;
			}
			if (Yii::$app->controller->route == $item['url']) {
				$class[] = $this->activeCssClass;
			}
			if ($i === 0 && $this->firstItemCssClass !== null) {
				$class[] = $this->firstItemCssClass;
			}
			if ($i === $n - 1 && $this->lastItemCssClass !== null) {
				$class[] = $this->lastItemCssClass;
			}
			if (!empty($class)) {
				if (empty($options['class'])) {
					$options['class'] = implode(' ', $class);
				} else {
					$options['class'] .= ' ' . implode(' ', $class);
				}
			}

			// Set the item visibility by current user role
			if (!empty($item['roles'])) {
				$visible = [];
				foreach ($item['roles'] as $role) {
					if (Yii::$app->user->can($role)) {
						$visible[] = 1;
					}
				}
				// If all of the submenu items are not visible, then skip the parent menu item too
				if (!in_array(1, $visible)) {
					continue;
				}
			}

			// Set item level
			$item['level'] = $level;
			// Render the item
			$menu = $this->renderItem($item);
			if (!empty($item['items'])) {
				$menu .= strtr($this->submenuTemplate, [
					'{items}' => $this->renderItems($item['items'], $level + 1),
				]);
			}
			$lines[] = Html::tag($tag, $menu, $options);
		}
		return implode("\n", $lines);
	}

	/**
	 * @inheritdoc
	 */
	protected function renderItem($item)
	{
		if (isset($item['url'])) {
			$template = ArrayHelper::getValue($item, 'template', $this->linkTemplate);
			return strtr($template, [
				'{beginLink}' => Html::beginTag('a', $this->_pullItemLinkOptions($item)),
				'{label}' => $this->_pullItemLabel($item),
				'{icon}' => $this->_pullItemIcon($item),
				'{arrow}' => $this->_pullItemArrow($item),
				'{badge}' => $this->_pullItemBadge($item),
				'{endLink}' => Html::endTag('a'),
			]);
		}
		$template = ArrayHelper::getValue($item, 'template', $this->labelTemplate);
		return strtr($template, [
			'{label}' => $this->_pullItemLabel($item),
		]);
	}

	/**
	 * Pulls out item link options.
	 * @param array $item given item
	 * @return array item link options
	 */
	private function _pullItemLinkOptions($item)
	{
		$url = ArrayHelper::getValue($item, 'url', '#');
		$toggle = ArrayHelper::getValue($item, 'toggle', false);
		$linkOptions = ArrayHelper::merge($this->linkOptions, ArrayHelper::getValue($item, 'linkOptions', []));

		if ($url === '#' || $toggle === true) {
			$linkOptions['href'] = 'javascript:void(0);';
			Html::addCssClass($linkOptions, 'nav-toggle');
		} else if (is_string($url) && !Url::isRelative($url)) {
			$linkOptions['href'] = $url;
		} else {
			$linkOptions['href'] = Url::toRoute($item['url']);
		}
		return $linkOptions;
	}

	/**
	 * Pulls out item label.
	 * @param array $item given item
	 * @return string item label
	 */
	private function _pullItemLabel($item)
	{
		$label = ArrayHelper::getValue($item, 'label', '');
		$level = ArrayHelper::getValue($item, 'level', 1);
		if ($level == 1) {
			return Html::tag('span', $label, ['class' => 'title']);
		}
		return sprintf(' %s', $label);
	}

	/**
	 * Pulls out item icon.
	 * @param array $item given item
	 * @return string item icon
	 */
	private function _pullItemIcon($item)
	{
		$icon = ArrayHelper::getValue($item, 'icon', null);
		if ($icon) {
			return Html::tag('i', '', ['class' => $icon]);
		}
		return '';
	}

	/**
	 * Pulls out item arrow.
	 * @param array $item given item
	 * @return string item arrow
	 */
	private function _pullItemArrow($item)
	{
		$active = ArrayHelper::getValue($item, 'active', false);
		$level = ArrayHelper::getValue($item, 'level', 1);
		$items = ArrayHelper::getValue($item, 'items', []);
		$arrow = '';
		if (!empty($items)) {
			$arrow = Html::tag('span', '', ['class' => 'arrow' . ($active ? ' open' : '')]);
		}
		if ($active && $level == 1) {
			$arrow = Html::tag('div', '', ['class' => 'selected']) . $arrow;
		}
		return $arrow;
	}

	/**
	 * Pulls out item badge.
	 * @param array $item given item
	 * @return string item badge
	 */
	private function _pullItemBadge($item)
	{
		$badge = ArrayHelper::getValue($item, 'badge', null);
		if (!empty($badge)) {
			return Html::tag('span', $badge['value'], ['class' => 'badge badge-' . $badge['type']]);
		}
		return '';
	}

	/**
	 * Inits options.
	 */
	private function _initOptions()
	{
		Html::addCssClass($this->options, 'page-sidebar-menu');

		$this->options['data-slide-speed'] = 200;
		$this->options['data-auto-scroll'] = 'true';
		$this->options['data-keep-expanded'] = 'false';
		$this->options['data-height'] = 261;
	}
}
