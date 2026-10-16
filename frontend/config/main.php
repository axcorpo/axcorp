<?php
$params = array_merge(
	require __DIR__ . '/../../common/config/params.php',
	require __DIR__ . '/../../common/config/params-local.php',
	require __DIR__ . '/params.php',
	require __DIR__ . '/params-local.php'
);

return [
	'id' => 'app-frontend',
	'name' => 'StoreDo',
	'basePath' => dirname(__DIR__),
	'controllerNamespace' => 'frontend\controllers',
	'bootstrap' => [
		'log',
		'frontend\components\ApplicationBootstrap',
		'account',
		'article',
		'service',
	],
	'modules' => [
		'account' => [
			'class' => 'frontend\modules\account\Module',
		],
		'article' => [
			'class' => 'frontend\modules\article\Module',
		],
		'service' => [
			'class' => 'frontend\modules\service\Module',
		],
	],
	'components' => [
		'assetManager' => [
			'assetMap' => [
				'jquery.fancybox.min.js' => '/js/noop.js',
//				'jquery.fancybox.min.css' => '/css/empty.css',
//				'animate.min.css' => '/css/empty.css',
			],
			'bundles' => [
				// Disable frontend JqueryAsset completely - use Yii's default instead
				'frontend\assets\JqueryAsset' => [
					'sourcePath' => null,
					'basePath' => null,
					'baseUrl' => null,
					'js' => [],
					'css' => [],
					'depends' => [],
				],
				// Configure Yii's JqueryAsset to load at the end (default POS_END)
				'yii\web\JqueryAsset' => [
					// No position override - will load at POS_END (end of body)
				],
				// YiiAsset depends on JqueryAsset (loads at end of body)
				'yii\web\YiiAsset' => [
					'depends' => [
						'yii\web\JqueryAsset',
					],
				],
				'yii\bootstrap\BootstrapPluginAsset' => [
					'sourcePath' => null,
					'js' => [],
				],
				'yii\bootstrap\BootstrapAsset' => [
					'css' => [],
					'js' => [],
				],
				// Same two bundles under the name some widgets use to declare the
				// dependency, with a leading backslash (tws\widgets\tinymce\TinyMCEAsset).
				// AssetManager matches overrides on the exact string, so without these the
				// support-ticket modal pulled Bootstrap 3's stylesheet over Bootstrap 5.
				'\\yii\\bootstrap\\BootstrapAsset' => [
					'css' => [],
					'js' => [],
				],
				'\\yii\\bootstrap\\BootstrapPluginAsset' => [
					'sourcePath' => null,
					'js' => [],
				],
				// Disable Bootstrap CSS from BootstrapAsset - remove from HEAD
				'frontend\assets\BootstrapAsset' => [
					'css' => [],
				],
				// Disable BootstrapBundleAsset completely - prevent duplicate Bootstrap loading
				'frontend\assets\BootstrapBundleAsset' => [
					'sourcePath' => null,
					'basePath' => null,
					'baseUrl' => null,
					'js' => [],
					'css' => [],
					'depends' => [],
				],
				// Configure DialogAsset to prevent duplicate loading
				'kartik\dialog\DialogAsset' => [
					// Let Yii handle deduplication - assets should only be registered once
				],
			],
		],
		'request' => [
			'baseUrl' => '',
			'csrfParam' => '_csrf',
			// CSRF validation is enabled: ActiveForm injects the token automatically and
			// the layouts emit registerCsrfMetaTags() so yii.js adds the X-CSRF-Token
			// header to every same-origin jQuery AJAX call (kartik's uploaders and the
			// DataTables grids included). The machine-to-machine POSTs -- the Stripe ipn
			// and the payment result -- exempt themselves in their beforeAction().
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
			'loginUrl' => ['/site/login'],
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
						'tier' => 'frontend',
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
			'baseUrl' => '',
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
					'pattern' => '',
					'route' => '/site/index',
				],
				[
					'pattern' => '/rss',
					'route' => '/site/rss',
				],
				[
					'pattern' => '/sitemap',
					'route' => '/site/sitemap',
				],
				[
					'pattern' => '/sitemap.xsl',
					'route' => '/site/sitemap-xsl',
				],
				[
					'pattern' => '<action:(invitation|logout|search|ipn|accept-cookies|assume-identity|newsletter)>',
					'route' => '/site/<action>',
				],
				[
					'pattern' => 'ab2<auth_key>',
					'route' => '/site/ab2',
				],
			],
		],
	],
	'params' => $params,
];
