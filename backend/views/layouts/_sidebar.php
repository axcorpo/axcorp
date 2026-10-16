<?php
/* @var $this \yii\web\View */

use backend\widgets\Menu;
?>

<div class="page-sidebar-wrapper">
	<?= Menu::widget([
		'activateItems' => true,
		'activateParents' => true,
		'encodeLabels' => false,
		'itemOptions' => [
			'class' => 'nav-item',
		],
		'search' => [
			'visible' => false,
			'form' => [
				'method' => 'GET',
				'action' => ['/site/search'],
			],
			'input' => [
				'name' => 'q',
				'value' => '',
				'options' => [
					'placeholder' => Yii::t('common', 'Search') . '...',
				],
			],
		],
		'items' => array_filter([
			[
				'icon' => 'icon-home',
				'label' => Yii::t('common', 'Dashboard'),
				'url' => ['/site/index'],
			],
			\backend\modules\website\Module::getInstance()->getNavMenuItems(),
			\backend\modules\subscriber\Module::getInstance()->getNavMenuItems(),
			\backend\modules\workspace\Module::getInstance()->getNavMenuItems(),
			\backend\modules\affiliate\Module::getInstance()->getNavMenuItems(),
			\backend\modules\nomenclature\Module::getInstance()->getNavMenuItems(),
			\backend\modules\marketing\Module::getInstance()->getNavMenuItems(),
			\backend\modules\helpdesk\Module::getInstance()->getNavMenuItems(),
			\backend\modules\user\Module::getInstance()->getNavMenuItems(),
            \backend\modules\tutorial\Module::getInstance()->getNavMenuItems(),
			\backend\modules\setting\Module::getInstance()->getNavMenuItems(),
			\backend\modules\backup\Module::getInstance()->getNavMenuItems(),
			\backend\modules\eventlog\Module::getInstance()->getNavMenuItems(),
		]),
	]) ?>
</div>
