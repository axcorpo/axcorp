<?php

namespace frontend\components;

use Yii;

class ApplicationBootstrap extends \common\components\ApplicationBootstrap
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
	}

	/**
	 * @inheritdoc
	 */
	public function bootstrap($app)
	{
		$this->updateUserActivity();
		$this->initUrlManager();
		$this->initGoogleMap();

		$app->language = $this->getUrlLanguage();
		$app->urlManager->addRules($this->buildUrlRules());
	}

	/**
	 * Initializes the GoogleMap extension.
	 * @link https://github.com/2amigos/yii2-google-maps-library
	 *
	 * @throws \yii\base\InvalidConfigException
	 */
	protected function initGoogleMap()
	{
		if (Yii::$app->has('assetManager')) {
			/** @var \yii\web\AssetManager $assetManager */
			$assetManager = Yii::$app->get('assetManager');
			$settings = Yii::$app->settings->getCategory('general');

			$assetManager->bundles['dosamigos\google\maps\MapAsset'] = [
				'options' => [
					'key' => $settings['googleMapKey'],
					'language' => Yii::$app->language,
				],
			];

			Yii::$app->set('assetManager', $assetManager);
		}
	}

	/**
	 * Initializes UrlManager component.
	 * @link http://www.yiiframework.com/doc-2.0/yii-web-urlmanager.html
	 *
	 * @throws \yii\base\InvalidConfigException
	 */
	protected function initUrlManager()
	{
		if (Yii::$app->has('urlManager')) {
			/** @var \codemix\localeurls\UrlManager $urlManager */
			$urlManager = Yii::$app->get('urlManager');
			$urlManager->languages = $this->languages;

			Yii::$app->set('urlManager', $urlManager);
		}
	}

	/**
	 * Builds the URL rules.
	 *
	 * @return array
	 */
	protected function buildUrlRules()
	{
		$pages = \common\models\Page::findPagesByModule('application');
		$urlRules = [];
		$dynamicPagesSlugs = [];

		foreach ($pages as $page) {
			$pageTranslation = $page->getTranslation();
			$route = $page->getRoute();
			$defaults = [];

			// Skip the HomePage because it is already defined
			if ($route === '/site/index') {
				continue;
			} elseif ($route === '/site/page') {
				/** @var \common\models\MenuItem[] $menuItems */
				if ($menuItems = $page->getMenuItems()->where(['IS NOT', 'parent_id', null])->all()) {
					foreach ($menuItems as $menuItem) {
						if ($menuItem->translation->slug || $menuItem->page->translation->slug) {
							$dynamicPagesSlugs[] = $menuItem->translation->slug ?: $menuItem->page->translation->slug;
						}
					}
				} else {
					if($pageTranslation->slug) {
						$dynamicPagesSlugs[] = $pageTranslation->slug;
					}
				}
				continue;
			}

			// Handle nested pages
			if ($menuItems = $page->getMenuItems()->where(['IS NOT', 'parent_id', null])->all()) {
				foreach ($menuItems as $menuItem) {
					if ($menuItem->translation->slug || $menuItem->page->translation->slug) {
						$urlRules[] = [
							'pattern' => $menuItem->translation->slug ?: $menuItem->page->translation->slug,
							'route' => $route,
							'defaults' => $defaults,
						];
					}
				}
			} else {
				if($pageTranslation->slug) {
					// Add the default rule
					$urlRules[] = [
						'pattern' => $pageTranslation->slug,
						'route' => $route,
						'defaults' => $defaults,
					];
				}
			}
		}

		// TODO: check the performance for bigger $dynamicPagesSlugs length
		// Create specific route for dynamic pages
		if (!empty($dynamicPagesSlugs)) {
			$urlRules[] = [
				'pattern' => '<slug:(' . implode('|', $dynamicPagesSlugs) . ')>',
				'route' => '/site/page',
				'encodeParams' => false,
			];
		}

//		echo '<pre>'; var_dump( $urlRules ); die();
		return $urlRules;
	}
}
