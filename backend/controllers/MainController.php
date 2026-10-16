<?php

namespace backend\controllers;

use Yii;
use yii\web\Controller;

class MainController extends Controller
{
	/**
	 * @var array the <body> tag HTML default attributes.
	 */
	public $bodyAttributes = [
		'class' => ['page-header-fixed page-sidebar-closed-hide-logo page-content-white'],
	];


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
		if (!Yii::$app->request->isAjax) {
			$this->registerMetaData();
		}
		return parent::beforeAction($action);
	}

	/**
	 * Gets the parent module name.
	 * This method handles only one level deep nested app modules (eg. app-module/parent-module/child-module.
	 * TODO: Needs recursion to handle unlimited deep nested app modules.
	 *
	 * @param bool $fullName
	 * @return string
	 */
	public function getParentModuleId($fullName = false)
	{
		$currentModule = $this->module;
		$parentModule = $currentModule->module;
		$modulePath = [$currentModule->id];

		if (!($parentModule instanceof \yii\web\Application)) {
			array_unshift($modulePath, $parentModule->id);
		}

		if ($fullName === true) {
			return $modulePath[0];
		}

		return reset(explode('-', $modulePath[0]));
	}

	/**
	 * Registers action meta data.
	 */
	protected function registerMetaData()
	{
		$view = $this->getView();
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
