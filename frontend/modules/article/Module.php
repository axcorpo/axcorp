<?php

namespace frontend\modules\article;

use Yii;
use yii\helpers\Inflector;

class Module extends \yii\base\Module implements \yii\base\BootstrapInterface
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		Yii::configure($this, require __DIR__ . '/config/main.php');
	}

	/**
	 * @inheritdoc
	 */
	public function bootstrap($app)
	{
		Yii::setAlias("@{$this->id}", __DIR__);

		$app->i18n->translations[$this->id] = [
			'class' => 'yii\i18n\PhpMessageSource',
			'basePath' => "@{$this->id}/messages",
			'forceTranslation' => true,
			'fileMap' => [
				$this->id => 'i18n.php',
			],
		];

		if ($app instanceof \yii\web\Application) {
			if ($urlRules = $this->buildUrlRules()) {
				$app->getUrlManager()->addRules([$urlRules], false);
			}
		}
	}

	/**
	 * Builds the rules for all pages of this module.
	 *
	 * @return array
	 */
	protected function buildUrlRules()
	{
		$pages = \common\models\Page::findPagesByModule($this->id);
		$urlRules = [];

		foreach ($pages as $page) {
			$pageTranslation = $page->getTranslation();
			$route = "{$page->controller}/{$page->action}";

			/** @var \common\models\MenuItem[] $menuItems */
			if ($menuItems = $page->getMenuItems()->where(['IS NOT', 'parent_id', null])->all()) {
				foreach ($menuItems as $menuItem) {
					$urlRules[] = [
						'pattern' => $menuItem->translation->slug,
						'route' => $route,
					];
				}
				continue;
			}

			if ($page->controller == 'default') {
				$urlRules = array_merge($urlRules, $this->buildArticleUrlRules($page, $pageTranslation));
				continue;
			}

			$urlRules[] = [
				'pattern' => $pageTranslation->slug,
				'route' => $route,
			];
		}

		if (!empty($menuItems)) {
			// TODO: set here the prefix of the top most page translated slug or identify the first URL segment
			$prefix = $menuItems[0]->translation->slug;
		} else {
			$prefix = reset($pages)->translation->slug;
		}

		return [
			'class' => 'yii\web\GroupUrlRule',
			'routePrefix' => $this->id,
			'prefix' => $prefix,
			'rules' => $urlRules,
		];
	}

	/**
	 * Builds URL Rules for ArticleController.
	 *
	 * @param \common\models\Page $page
	 * @param \common\models\PageTranslation $pageTranslation
	 * @return array
	 */
	protected function buildArticleUrlRules($page, $pageTranslation)
	{
		$route = "{$page->controller}/{$page->action}";

		$urlRules[] = [
			'pattern' => Inflector::slug(Yii::t($this->id, 'Archive')) . "/<year:(\d{4})>/<month:(\d{2})>/<day:(\d{2})>",
			'route' => $route,
			'defaults' => [
				'month' => '',
				'day' => '',
			],
		];
		$urlRules[] = [
			'pattern' => Inflector::slug(Yii::t($this->id, 'Category')) . "/<category>",
			'route' => $route,
		];
		$urlRules[] = [
			'pattern' => Inflector::slug(Yii::t($this->id, 'Tag')) . "/<tag>",
			'route' => $route,
		];
		$urlRules[] = [
			'pattern' => "<slug>",
			'route' => "{$page->controller}/view",
		];
		$urlRules[] = [
			'pattern' => '',
			'route' => $route,
		];

		return $urlRules;
	}
}
