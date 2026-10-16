<?php
return [
	'language' => 'en-US',
	'sourceLanguage' => 'en-US',
	'timeZone' => 'Europe/Bucharest',
	'vendorPath' => dirname(__DIR__, 2) . '/vendor',
	'aliases' => [
		'@bower' => '@vendor/bower-asset',
		'@npm' => '@vendor/npm-asset',
	],
	'bootstrap' => [
		'tws\Bootstrap',
		'common\components\ApplicationBootstrap',
		'common\components\SubscriberBootstrap',
		'commercial',
	],
	'modules' => [
		'translatemanager' => [
			'class' => 'lajax\translatemanager\Module',
			'root' => [
				'@common',
				'@backend',
				'@frontend',
				'@console',
			],
			'ignoredCategories' => ['yii', 'tws'],
			'ignoredItems' => ['config'],
			'patterns' => ['*.php'],
			'scanTimeLimit' => null,
			'scanners' => [
				'lajax\translatemanager\services\scanners\ScannerPhpFunction',
				'lajax\translatemanager\services\scanners\ScannerPhpArray',
				'lajax\translatemanager\services\scanners\ScannerDatabase',
			],
		],
		'commercial' => [
			'class' => 'tws\commercial\Module',
		],
	],
	'controllerMap' => [
		'translate' => 'lajax\translatemanager\commands\TranslatemanagerController',
	],
	'components' => [
		'cache' => [
			'class' => 'yii\caching\FileCache',
		],
		'db' => [
			// Binary-safe query cache keys for BINARY(16) UUID params (see common\db\Command)
			'commandMap' => [
				'mysql' => \common\db\Command::class,
			],
		],
		'authManager' => [
			// Converts UUID string user ids to the BINARY(16) auth_assignment.user_id
			'class' => 'common\rbac\DbManager',
			'cache' => 'cache',
			'cacheKey' => 'rbac',
//			'defaultRoles' => ['operator'],
		],
		'assetManager' => [
			'linkAssets' => true,
			'appendTimestamp' => true,
		],
		'i18n' => [
			'translations' => [
				'*' => [
					'class' => 'yii\i18n\DbMessageSource',
					'db' => 'db',
					'sourceLanguage' => 'en-US', // Developer language
					'sourceMessageTable' => '{{%language_source}}',
					'messageTable' => '{{%language_translate}}',
					'cachingDuration' => 86400, // 24 hours
					'enableCaching' => true,
					'forceTranslation' => true,
				],
				// Kartik widgets (Select2, etc.) register PhpMessageSource for `kvselect*` via TranslationTrait
				// pointing at the vendor messages folder, which has no ro/ro-RO file. We override basePath
				// to our project folder so the missing-file warning goes away.
				'kvselect*' => [
					'class' => 'yii\i18n\PhpMessageSource',
					'basePath' => '@common/messages',
					'forceTranslation' => true,
				],
			],
		],
		'formatter' => [
			'class' => 'common\helpers\Formatter',
		],
		'settings' => [
			'class' => 'tws\settings\Settings',
		],
		'instance' => [
			'class' => 'tws\instance\Instance',
		],
		'user' => [
			'class' => 'yii\web\User',
			'identityClass' => 'common\models\User',
			'on afterLogin' => function($event) {
				Yii::$app->user->identity->afterLogin($event);
			}
		],
	],
];
