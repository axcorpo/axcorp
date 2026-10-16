<?php
return [
	'aliases' => [
		'@subscriber' => dirname(__DIR__),
	],
	'modules' => [
		'report-manager' => [
			'class' => 'backend\modules\subscriber\modules\report\Module',
		],
	],
	'components' => [
		'urlManager' => [
			'class' => 'yii\web\UrlManager',
			'rules' => [
				[
					'class' => 'yii\web\GroupUrlRule',
					'prefix' => '<module>/subscriber',
					'routePrefix' => '<module>',
					'rules' => [
						[
							'pattern' => '<subscriber_id:([0-9a-fA-F-]{36}|\d+|__\w+__)>/<controller>/<id:([0-9a-fA-F-]{36}|\d+|__\w+__)>/<action>',
							'route' => '<controller>/<action>',
						],
						[
							'pattern' => '<subscriber_id:([0-9a-fA-F-]{36}|\d+|__\w+__)>/<controller>/<action>',
							'route' => '<controller>/<action>',
						],
					],
				],
				[
					'pattern' => '/',
					'route' => 'default/index',
				],
				[
					'pattern' => '<controller>/<id:([0-9a-fA-F-]{36}|\d+|__\w+__|\w+)>/<action>',
					'route' => '<controller>/<action>',
				],
				[
					'pattern' => '<controller>/<action>',
					'route' => '<controller>/<action>',
				],
			],
		],
	],
];
