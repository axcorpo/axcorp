<?php

namespace console\components;

use common\models\ScheduledTask;
use tws\helpers\Url;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\httpclient\Client;

/**
 * Class ScheduleBootstrap
 */
class ScheduleBootstrap extends Component implements BootstrapInterface
{
	/**
	 * @inheritdoc
	 * @throws \yii\base\InvalidConfigException
	 */
	public function bootstrap($app)
	{
		if ($app instanceof yii\console\Application) {
			if ($app->has('schedule')) {
				/** @var \omnilight\scheduling\Schedule $schedule */
				$schedule = $app->get('schedule');

				/** @var ScheduledTask $scheduledTask */
				foreach (ScheduledTask::find()->active()->deleted(false)->all() as $scheduledTask) {
					/** @var \omnilight\scheduling\Event $event */
					if (!empty($scheduledTask->app_command)) {
						$event = $schedule->exec('"'. PHP_BINARY . '" ' . dirname(Yii::getAlias('@app')) . "/{$schedule->cliScriptName} {$scheduledTask->app_command}");
					} elseif (!empty($scheduledTask->shell_command)) {
						$event = $schedule->exec($scheduledTask->shell_command);
					} else {
						$event = $schedule->call(function() use ($scheduledTask) {
							if ($scheduledTask->request_url) {
								$this->makeHttpRequest($scheduledTask);
							}
						});
					}
					$event->cron($scheduledTask->cron_expression);
				}
			}
		}
	}

	/**
	 * Makes a HTTP request for a given URL.
	 *
	 * @param ScheduledTask $scheduledTask
	 * @return \yii\httpclient\Response|null
	 */
	protected function makeHttpRequest($scheduledTask)
	{
		try {
			if (Url::isRelative($scheduledTask->request_url)) {
				$scheduledTask->request_url = implode('/', array_filter([
					rtrim(Url::base(true), '/'),
					ltrim($scheduledTask->request_url, '/'),
				]));
			}

			$requestConfig = $scheduledTask->getUnserializedValue('request_config');
			$client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
			$request = $client->createRequest()->setUrl($scheduledTask->request_url);

			if (!empty($requestConfig)) {
				if (!empty($requestConfig['method'])) {
					$request->setMethod(mb_strtoupper($requestConfig['method']));
				}
				if (!empty($requestConfig['headers'])) {
					$request->setHeaders((array) $requestConfig['headers']);
				}
				if (!empty($requestConfig['options'])) {
					$request->setOptions((array) $requestConfig['options']);
				}
				if (!empty($requestConfig['data'])) {
					$request->setData((array) $requestConfig['data']);
				}
			}

			return $request->send();
		} catch (\Exception $e) {
			return null;
		}
	}
}
