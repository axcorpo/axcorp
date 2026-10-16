<?php

namespace backend\components;

use tws\helpers\Url;
use Yii;
use yii\caching\TagDependency;
use yii\helpers\FileHelper;
use yii\web\Application;

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
        $this->checkUserAccess();
		$this->updateUserActivity();
		$this->initUrlManager();
		$this->initEventLog();
		$this->initGoogleMap();
		$app->on(\yii\base\Application::EVENT_BEFORE_ACTION, function ($event) {
			$this->initFormatter();
		});

		$app->on('invalidate.cache', function ($event) use ($app) {
			$cache = $app->cache;
			$initialCachePath = $cache->cachePath;
			$baseCachePath = FileHelper::normalizePath(dirname($app->basePath) . '/{{APP}}/runtime/cache');

			if (isset($event->key)) {
				$cache->cachePath = strtr($baseCachePath, ['{{APP}}' => 'backend']);
				TagDependency::invalidate($cache, $event->key);
				$cache->cachePath = strtr($baseCachePath, ['{{APP}}' => 'frontend']);
				TagDependency::invalidate($cache, $event->key);
				$cache->cachePath = strtr($baseCachePath, ['{{APP}}' => 'console']);
				TagDependency::invalidate($cache, $event->key);
			} else {
				$cache->cachePath = strtr($baseCachePath, ['{{APP}}' => 'backend']);
				$cache->flush();
				$cache->cachePath = strtr($baseCachePath, ['{{APP}}' => 'frontend']);
				$cache->flush();
				$cache->cachePath = strtr($baseCachePath, ['{{APP}}' => 'console']);
				$cache->flush();
			}

			$cache->cachePath = $initialCachePath;
		});
	}

	/**
	 * Checks the current user access.
	 */
	protected function checkUserAccess()
	{
		if (Yii::$app->user->isGuest) {
			Yii::$app->response->redirect(Url::to(['/site/login'], true, '@frontend'))->send();
            Yii::$app->end();
		}

		/** @var \common\models\User $user */
		$user = Yii::$app->user->identity;

		// Block user access if is not active or does not have any role/permission associated
		if ($user->status != \common\models\User::STATUS_ACTIVE || !$user->getHasPermissions()) {
			Yii::$app->session->setFlash('error', Yii::t('yii', 'You are not allowed to perform this action.'));
            Yii::$app->response->redirect(Url::to(['/site/index'], true, '@frontend'))->send();
            Yii::$app->end();
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
	 * Initializes EventLog component.
	 *
	 * @throws \yii\base\InvalidConfigException
	 */
	protected function initEventLog()
	{
		if (Yii::$app->has('eventLog')) {
			/** @var \backend\modules\eventlog\components\EventLog $eventLog */
			$eventLog = Yii::$app->get('eventLog');
			$eventLog->enabled = (bool) Yii::$app->settings->get('enableEventLogs');

			Yii::$app->set('eventLog', $eventLog);
		}
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
	 * Initializes Formatter component.
	 * @link http://www.yiiframework.com/doc-2.0/yii-i18n-formatter.html
	 *
	 * @throws \yii\base\InvalidConfigException
	 */
	protected function initFormatter()
	{
		if (Yii::$app->has('formatter')) {
			/** @var \yii\i18n\Formatter $formatter */
			$formatter = Yii::$app->get('formatter');
			$settings = Yii::$app->settings->getCategory('general');

			if (!empty($settings['timeZone'])) {
				Yii::$app->setTimeZone($settings['timeZone']);
			}
			$formatter->defaultTimeZone = Yii::$app->getTimeZone();
			$formatter->timeFormat = $settings['timeFormat'] ?: 'HH:mm:ss';
			$formatter->dateFormat = $settings['dateFormat'] ?: 'yyyy-MM-dd';
			$formatter->datetimeFormat = $settings['datetimeFormat'] ?: 'yyyy-MM-dd HH:mm:ss';
			$formatter->currencyCode = $settings['currencyCode'] ?: 'USD';
			$formatter->nullDisplay = '';

			$currencyCodesMap = [
				'RON' => 'Lei',
			];
			if (array_key_exists(mb_strtoupper($formatter->currencyCode), $currencyCodesMap)) {
				$formatter->currencyCode = $currencyCodesMap[mb_strtoupper($formatter->currencyCode)];
			}

			Yii::$app->set('formatter', $formatter);
		}
	}
}
