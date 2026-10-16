<?php
$params = array_merge(
	require __DIR__ . '/../../common/config/params.php',
	require __DIR__ . '/../../common/config/params-local.php',
	require __DIR__ . '/params.php',
	require __DIR__ . '/params-local.php'
);

return [
	'id' => 'app-console',
	'name' => 'ManagerGSM Console',
	'basePath' => dirname(__DIR__),
	'aliases' => [
		'@bower' => '@vendor/bower-asset',
		'@npm' => '@vendor/npm-asset',
	],
	'bootstrap' => [
		'log',
		'console\components\ApplicationBootstrap',
		'console\components\ScheduleBootstrap',
	],
	'controllerNamespace' => 'console\controllers',
	'controllerMap' => [
		'fixture' => [
			'class' => 'yii\console\controllers\FixtureController',
			'namespace' => 'common\fixtures',
		],
	],
	'components' => [
		'log' => [
			'targets' => array_merge([
				[
					'class' => 'yii\log\FileTarget',
					'levels' => ['error', 'warning'],
				],
			], empty($params['loki.enabled']) ? [] : [
				[
					'class' => 'common\components\LokiLogTarget',
					'levels' => ['error', 'warning'],
					'lokiPushUrl' => $params['loki.pushUrl'],
					'lokiAuthUser' => $params['loki.authUser'],
					'lokiAuthPassword' => $params['loki.authPassword'],
					'labels' => [
						'app' => 'storedo-master',
						'tier' => 'console',
						'environment' => YII_ENV,
					],
				],
			]),
		],
		// Models carry BlameableBehavior, which reads Yii::$app->user->id on save. The web
		// user component from common config renews its auth status through the session,
		// which a console app does not have; without sessions the identity is simply null
		// and created_by/updated_by stay empty for cron-issued rows.
		'user' => [
			'class' => 'yii\web\User',
			'identityClass' => 'common\models\User',
			'enableSession' => false,
			'enableAutoLogin' => false,
		],
		'urlManager' => [
			'class' => 'yii\web\UrlManager',
			'baseUrl' => '',
			'enablePrettyUrl' => true,
			'showScriptName' => false,
		],
		'schedule' => 'omnilight\scheduling\Schedule',
	],
	'params' => $params,
];
