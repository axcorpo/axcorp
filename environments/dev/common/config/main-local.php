<?php
return [
	'components' => [
		'db' => [
			'class' => 'yii\db\Connection',
			'dsn' => 'mysql:host=localhost;dbname=DATABASE_NAME',
			'username' => 'DATABASE_USERNAME',
			'password' => 'DATABASE_PASSWORD',
			'charset' => 'utf8',
			'enableLogging' => true,
			'enableProfiling' => true,
			'enableSchemaCache' => true,
			'schemaCache' => 'cache',
			'schemaCacheDuration' => 3600,
			'enableQueryCache' => true,
			'queryCache' => 'cache',
			'queryCacheDuration' => 3600,
		],
		'mailer' => [
			'class' => 'yii\symfonymailer\Mailer',
			'viewPath' => '@common/mail',
			'useFileTransport' => false,
			'messageConfig' => [
				'charset' => 'UTF-8',
			],
		],
		'sms' => [
			'class' => 'tws\sms\Sms',
			'viewPath' => '@common/sms',
			'useFileTransport' => false,
			'messageConfig' => [
				'charset' => 'UTF-8',
			],
		],
		'payment' => [
			'class' => 'tws\payment\Payment',
		],
		'company' => [
			'class' => 'tws\commercial\components\Company',
			'enableCaching' => true,
			'cache' => 'cache',
			'cacheKey' => 'company',
			'cacheDuration' => 24 * 60 * 60,
		],
		'cPanel' => [
			// Prefer an API token (cPanel > Security > Manage API Tokens): an account with
			// two-factor authentication cannot authenticate with its password here, and a
			// token keeps the account password out of the requests. Leave `apiToken` empty
			// to fall back to `password`.
			'class' => 'common\components\CPanel',
			'baseUrl' => 'CPANEL_BASE_URL',
			'username' => 'CPANEL_USERNAME',
			'password' => 'CPANEL_PASSWORD',
			'apiToken' => '',
		],
	],
];
