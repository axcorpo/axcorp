<?php
/* @var $this \yii\web\View */
/* @var $menu \common\models\Menu */
/* @var $class string */

use tws\helpers\Url;
use yii\helpers\Html;

// The items are built exactly like SiteNav builds them (labels, routes, page slugs,
// html options, cache); only the markup is the theme's: .box-nav-menu > .menu-item
// with a .sub-menu one level down. main.js clones this list into the mobile menu.
$builder = new \common\widgets\sitemenu\SiteNav(['items' => []]);
$items = $menu ? $builder->buildItems($menu->getNestedItems(null, \common\models\MenuItem::STATUS_ACTIVE)) : [];
// The template's menu opens with Home.
array_unshift($items, ['label' => Yii::t('yii', 'Home'), 'url' => ['/site/index']]);

$currentPath = '/' . trim(parse_url(Yii::$app->request->url, PHP_URL_PATH), '/');
$isActive = function ($item) use (&$isActive, $currentPath) {
	if (isset($item['url']) && $item['url'] !== '#') {
		$path = '/' . trim((string) parse_url(Url::to($item['url']), PHP_URL_PATH), '/');
		if ($path === $currentPath) {
			return true;
		}
	}
	foreach ($item['items'] ?? [] as $child) {
		if ($isActive($child)) {
			return true;
		}
	}
	return false;
};
$href = function ($item) {
	return $item['url'] === '#' ? '#' : Url::to($item['url']);
};
?>
<ul class="<?= $class ?>">
	<?php foreach ($items as $item): ?>
		<?php
		$children = $item['items'] ?? [];
		$itemOptions = $item['options'] ?? [];
		Html::addCssClass($itemOptions, $children ? ['menu-item', 'has-sub_menu'] : ['menu-item']);
		$linkOptions = $item['linkOptions'] ?? [];
		Html::addCssClass($linkOptions, 'item-link');
		if ($isActive($item)) {
			Html::addCssClass($linkOptions, 'active');
		}
		$linkOptions['href'] = $href($item);
		?>
		<?= Html::beginTag('li', $itemOptions) ?>
			<?= Html::tag('a', '<span>' . $item['label'] . '</span>', $linkOptions) ?>
			<?php if ($children): ?>
				<div class="sub-menu">
					<ul class="sub-menu_list">
						<?php foreach ($children as $child): ?>
							<?php
							$childLinkOptions = $child['linkOptions'] ?? [];
							Html::addCssClass($childLinkOptions, 'sub-menu_link');
							if ($isActive($child)) {
								Html::addCssClass($childLinkOptions, 'active');
							}
							$childLinkOptions['href'] = $href($child);
							?>
							<?= Html::tag('li', Html::tag('a', $child['label'], $childLinkOptions), $child['options'] ?? []) ?>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		<?= Html::endTag('li') ?>
	<?php endforeach; ?>
	<?php // Contact: on desktop it is the button next to the menu; main.js copies this item into
	// the mobile menu, where that button is hidden. ?>
	<li class="menu-item d-none">
		<a class="item-link" href="<?= Url::to(['/site/contact']) ?>"><span><?= Yii::t('frontend', 'Contact Us') ?></span></a>
	</li>
</ul>
