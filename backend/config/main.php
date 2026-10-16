<?php
$params = array_merge(
	require __DIR__ . '/../../common/config/params.php',
	require __DIR__ . '/../../common/config/params-local.php',
	require __DIR__ . '/params.php',
	require __DIR__ . '/params-local.php'
);

return [
	'id' => 'app-backend',
	'name' => 'BotAi Administration',
	'basePath' => dirname(__DIR__),
	'controllerNamespace' => 'backend\controllers',
	'bootstrap' => [
		'log',
		'backend\components\ApplicationBootstrap',
		'setting-manager',
		'backup-manager',
		'export-manager',
		'user-manager',
		'eventlog-manager',
		'notification-manager',
		'website-manager',
		'subscriber-manager',
		'nomenclature-manager',
		'marketing-manager',
		'helpdesk-manager',
        'tutorial-manager',
        'workspace-manager',
		'affiliate-manager',
	],
	'modules' => [
		'workspace-manager' => [
			'class' => 'backend\modules\workspace\Module',
		],
		'setting-manager' => [
			'class' => 'backend\modules\setting\Module',
		],
		'backup-manager' => [
			'class' => 'backend\modules\backup\Module',
		],
		'export-manager' => [
			'class' => 'backend\modules\export\Module',
		],
		'user-manager' => [
			'class' => 'backend\modules\user\Module',
		],
		'eventlog-manager' => [
			'class' => 'backend\modules\eventlog\Module',
		],
		'notification-manager' => [
			'class' => 'backend\modules\notification\Module',
		],
		'website-manager' => [
			'class' => 'backend\modules\website\Module',
		],
		'subscriber-manager' => [
			'class' => 'backend\modules\subscriber\Module',
		],
		'nomenclature-manager' => [
			'class' => 'backend\modules\nomenclature\Module',
		],
		'marketing-manager' => [
			'class' => 'backend\modules\marketing\Module',
		],
		'helpdesk-manager' => [
			'class' => 'backend\modules\helpdesk\Module',
		],
        'tutorial-manager' => [
            'class' => 'backend\modules\tutorial\Module',
        ],
		'affiliate-manager' => [
			'class' => 'backend\modules\affiliate\Module',
		],
	],
	'components' => [
		'request' => [
			'baseUrl' => '/admin',
			'csrfParam' => '_csrf',
			// See the frontend config: the admin shares this session cookie, so the two
			// apps must declare the same attributes or whichever answers last rewrites
			// them. Only the `error` action opts out, in SiteController::beforeAction().
			'enableCsrfValidation' => true,
			'enableCookieValidation' => true,
			'enableCsrfCookie' => false,
			'csrfCookie' => [
				'path' => '/',
				'httpOnly' => true,
				'sameSite' => 'Lax',
				// Dev runs on plain HTTP, where a secure cookie is simply dropped.
				'secure' => YII_ENV_PROD,
			],
		],
		'user' => [
			'identityClass' => 'common\models\User',
			'enableAutoLogin' => true,
			'identityCookie' => [
				'path' => '/',
				'name' => '_identity',
				'httpOnly' => true,
				'sameSite' => 'Lax',
				// Dev runs on plain HTTP, where a secure cookie is simply dropped.
				'secure' => YII_ENV_PROD,
			],
			'loginUrl' => '/',
		],
		'session' => [
			'name' => 'BOTAISESSID',
			'useCookies' => true,
			'cookieParams' => [
				'path' => '/',
				'httpOnly' => true,
				'sameSite' => 'Lax',
				// Dev runs on plain HTTP, where a secure cookie is simply dropped.
				'secure' => YII_ENV_PROD,
			],
		],
		'log' => [
			'traceLevel' => YII_DEBUG ? 3 : 0,
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
						'tier' => 'backend',
						'environment' => YII_ENV,
					],
				],
			]),
		],
		'errorHandler' => [
			'errorAction' => 'site/error',
		],
		'urlManager' => [
			'class' => 'common\components\UuidUrlManager',
			'baseUrl' => '/admin',
			'languages' => ['en' => 'en-US'],
			'enableLanguageDetection' => false,
			'enableDefaultLanguageUrlCode' => false,
			'enableLanguagePersistence' => false,
			'enableStrictParsing' => true,
			'enablePrettyUrl' => true,
			'showScriptName' => false,
			'normalizer' => [
				'class' => 'yii\web\UrlNormalizer',
				'action' => yii\web\UrlNormalizer::ACTION_REDIRECT_PERMANENT,
			],
			'rules' => [
				[
					'pattern' => '/',
					'route' => '/site/index',
				],
				[
					'pattern' => '/profile',
					'route' => '/profile/index',
				],
				[
					'pattern' => '/<action:(login|logout|reset-password|profile|search)>',
					'route' => '/site/<action>',
				],
				[
					'pattern' => '/<controller>/<id:([0-9a-fA-F-]{36}|\d+|__\w+__)>/<action>',
					'route' => '/<controller>/<action>',
				],
				[
					'pattern' => '/<controller>/<action>',
					'route' => '/<controller>/<action>',
				],
			],
		],
		'eventLog' => [
			'class' => 'backend\modules\eventlog\components\EventLog',
			'model' => 'common\models\EventLog',
		],
        'translate' => [
            'class' => 'common\components\GoogleTranslation',
            'key' => $params['google.translateKey'] ?? '',
        ],
	],
	'params' => $params,
];
