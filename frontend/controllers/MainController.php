<?php

namespace frontend\controllers;

use common\models\Language;
use common\models\Page;
use Yii;
use tws\helpers\Url;
use yii\web\Controller;

/**
 * Main controller
 *
 * @property Language[] $appLanguages
 * @property Language $currentAppLanguage
 * @property Page $currentPage
 */
class MainController extends Controller
{
	/**
	 * @var array the <body> tag HTML default attributes.
	 */
	public $bodyAttributes = [];

	/**
	 * @var Language[] The application languages list.
	 */
	private $_appLanguages;

	/**
	 * @var Language The application current language.
	 */
	private $_currentAppLanguage;

	/**
	 * @var Page The current page.
	 */
	private $_currentPage;


	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
	}

	/**
	 * @inheritdoc
	 * @throws \yii\web\BadRequestHttpException
	 */
	public function beforeAction($action)
	{
		// An affiliate's link can land on any page: remember ?aff= for the signup.
		// No-op (no session/cookie touched) when the parameter is absent.
		\common\components\AffiliateTracker::capture();

		if (Yii::$app->settings->get('enableTwoFactorAuthentication')) {
			if (Yii::$app->user->identity->login_token) {
				if (Yii::$app->controller->action->id != 'confirm-login') {
					return $this->redirect(['/site/confirm-login']);
				}
			} else {
				if (Yii::$app->controller->action->id == 'confirm-login') {
					return $this->goHome();
				}
			}
		}
		if (!Yii::$app->request->isAjax) {
			$this->registerMetaData();
		}
		return parent::beforeAction($action);
	}

	/**
	 * Gets all active application languages.
	 *
	 * @return array|Language[]
	 */
	public function getAppLanguages()
	{
		if (!$this->_appLanguages) {
			$this->_appLanguages = Language::findAllLanguages();
		}
		return $this->_appLanguages;
	}

	/**
	 * Gets the application current language.
	 *
	 * @return array|Language
	 */
	public function getCurrentAppLanguage()
	{
		if (!$this->_currentAppLanguage) {
			$this->_currentAppLanguage = $this->getAppLanguages()[Yii::$app->language];
		}
		return $this->_currentAppLanguage;
	}

	/**
	 * Gets the current application page.
	 *
	 * @return null|Page
	 */
	public function getCurrentPage()
	{
		if (!$this->_currentPage) {
			$this->_currentPage = Page::findPageByPathInfo(Yii::$app->request->pathInfo);
		}
		return $this->_currentPage;
	}

	/**
	 * Registers action meta data.
	 */
	protected function registerMetaData()
	{
		$settings = Yii::$app->settings->getAll();
		$view = $this->getView();
		$canonicalUrl = Url::canonical();
		$ogLogo = Url::to('@uploads/' . Yii::$app->settings->get('appLogo'), true) ?: Url::to('@web/img/logo.png', true);

		// Twitter metadata
		// --- this can be mixed with the above basic ogp metadata (@see https://dev.twitter.com/cards/getting-started)
		$view->registerMetaTag(['name' => 'twitter:card', 'content' => 'summary'], 'twitter:card');
		if ($settings['socialNetwork']['twitterSite']) {
			$view->registerMetaTag(['name' => 'twitter:site', 'content' => $settings['socialNetwork']['twitterSite']], 'twitter:site');
		}
		if ($settings['socialNetwork']['twitterCreator']) {
			$view->registerMetaTag(['name' => 'twitter:creator', 'content' =>  $settings['socialNetwork']['twitterCreator']], 'twitter:creator');
		}

		if ($page = $this->getCurrentPage()) {
			$pageTranslation = $page->getTranslation();

			// Standard meta tags
			$view->title = $pageTranslation->title;
			$view->registerMetaTag(['name' => 'description', 'content' => $pageTranslation->description], 'description');
			$view->registerMetaTag(['name' => 'keywords', 'content' => $pageTranslation->keywords], 'keywords');

			// Basic metadata for open graph
			$view->registerMetaTag(['property' => 'og:title', 'content' => $pageTranslation->title], 'og:title');
			$view->registerMetaTag(['property' => 'og:description', 'content' => $pageTranslation->description], 'og:description');
		}
		$view->registerMetaTag(['property' => 'og:type', 'content' => 'website'], 'og:type');
		$view->registerMetaTag(['property' => 'og:url', 'content' => $canonicalUrl], 'og:url');
		$view->registerMetaTag(['property' => 'og:image', 'content' => $ogLogo], 'og:image');
		$view->registerMetaTag(['property' => 'og:site_name', 'content' => Yii::$app->name], 'og:site_name');
		$view->registerMetaTag(['property' => 'og:locale', 'content' => str_replace('-', '_', Yii::$app->language)], 'og:locale');
		foreach ($this->getAppLanguages() as $language) {
			if ($language->language_id != Yii::$app->language) {
				$view->registerMetaTag(['property' => 'og:locale:alternate', 'content' => str_replace('-', '_', $language->language_id)], 'og:locale:alternate');
			}
		}

		// Facebook metadata
		if ($settings['socialNetwork']['facebookAppId']) {
			$view->registerMetaTag(['property' => 'fb:app_id', 'content' => $settings['socialNetwork']['facebookAppId']], 'fb:app_id');
		}

		// Robots
		$view->registerMetaTag(['name' => 'robots', 'content' => $settings['seo']['allowRobotsCrawling'] ? 'index, follow' : 'noindex, nofollow'], 'robots');

		// Verification codes
		if ($settings['seo']['googleVerification']) {
			$view->registerMetaTag(['name' => 'google-site-verification', 'content' => $settings['seo']['googleVerification']], 'google-site-verification');
		}
		if ($settings['seo']['bingVerification']) {
			$view->registerMetaTag(['name' => 'msvalidate.01', 'content' => $settings['seo']['bingVerification']], 'msvalidate.01');
		}
		if ($settings['seo']['pinterestVerification']) {
			$view->registerMetaTag(['name' => 'p:domain_verify', 'content' => $settings['seo']['pinterestVerification']], 'p:domain_verify');
		}

		// Links
		$view->registerLinkTag(['rel' => 'alternate', 'href' => Url::to(['/site/rss'], true), 'type' => 'application/rss+xml', 'title' => 'RSS 2.0'], 'alternate');
		$view->registerLinkTag(['rel' => 'canonical', 'href' => $canonicalUrl], 'canonical');

		// Favicon
		$view->registerLinkTag(['rel' => 'apple-touch-icon', 'sizes' => '57x57', 'href' => '/img/ico/apple-icon-57x57.png'], 'apple-touch-icon-57x57');
		$view->registerLinkTag(['rel' => 'apple-touch-icon', 'sizes' => '60x60', 'href' => '/img/ico/apple-icon-60x60.png'], 'apple-touch-icon-60x60');
		$view->registerLinkTag(['rel' => 'apple-touch-icon', 'sizes' => '72x72', 'href' => '/img/ico/apple-icon-72x72.png'], 'apple-touch-icon-72x72');
		$view->registerLinkTag(['rel' => 'apple-touch-icon', 'sizes' => '76x76', 'href' => '/img/ico/apple-icon-76x76.png'], 'apple-touch-icon-76x76');
		$view->registerLinkTag(['rel' => 'apple-touch-icon', 'sizes' => '114x114', 'href' => '/img/ico/apple-icon-114x114.png'], 'apple-touch-icon-114x114');
		$view->registerLinkTag(['rel' => 'apple-touch-icon', 'sizes' => '120x120', 'href' => '/img/ico/apple-icon-120x120.png'], 'apple-touch-icon-120x120');
		$view->registerLinkTag(['rel' => 'apple-touch-icon', 'sizes' => '144x144', 'href' => '/img/ico/apple-icon-144x144.png'], 'apple-touch-icon-144x144');
		$view->registerLinkTag(['rel' => 'apple-touch-icon', 'sizes' => '152x152', 'href' => '/img/ico/apple-icon-152x152.png'], 'apple-touch-icon-152x152');
		$view->registerLinkTag(['rel' => 'apple-touch-icon', 'sizes' => '180x180', 'href' => '/img/ico/apple-icon-180x180.png'], 'apple-touch-icon-180x180');
		$view->registerLinkTag(['rel' => 'icon', 'type' => 'image/png', 'sizes' => '192x192', 'href' => '/img/ico/android-icon-192x192.png'], 'android-icon-192x192');
		$view->registerLinkTag(['rel' => 'icon', 'type' => 'image/png', 'sizes' => '32x32', 'href' => '/img/ico/favicon-32x32.png'], 'favicon-32x32');
		$view->registerLinkTag(['rel' => 'icon', 'type' => 'image/png', 'sizes' => '96x96', 'href' => '/img/ico/favicon-96x96.png'], 'favicon-96x96');
		$view->registerLinkTag(['rel' => 'icon', 'type' => 'image/png', 'sizes' => '16x16', 'href' => '/img/ico/favicon-16x16.png'], 'favicon-16x16');
		$view->registerLinkTag(['rel' => 'manifest', 'href' => '/manifest.json'], 'manifest');
		$view->registerMetaTag(['name' => 'msapplication-TileColor', 'content' => '#ffffff'], 'msapplication-TileColor');
		$view->registerMetaTag(['name' => 'msapplication-TileImage', 'content' => '/img/ico/ms-icon-144x144.png'], 'msapplication-TileImage');
		$view->registerMetaTag(['name' => 'theme-color', 'content' => '#ffffff'], 'theme-color');
	}
}
