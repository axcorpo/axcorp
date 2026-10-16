<?php
return [
	'aliases' => [
		'@website' => dirname(__DIR__),
	],
	'components' => [
		'urlManager' => [
			'class' => 'yii\web\UrlManager',
			'rules' => [
				[
					'class' => 'yii\web\GroupUrlRule',
					'prefix' => '<module>/menu',
					'routePrefix' => '<module>',
					'rules' => [
						[
							'pattern' => '<menu_id:([0-9a-fA-F-]{36}|\d+|__\w+__)>/<controller>/<id:([0-9a-fA-F-]{36}|\d+|__\w+__)>/<action>',
							'route' => '<controller>/<action>',
						],
						[
							'pattern' => '<menu_id:([0-9a-fA-F-]{36}|\d+|__\w+__)>/<controller>/<action>',
							'route' => '<controller>/<action>',
						],
					],
				],
				[
					'class' => 'yii\web\GroupUrlRule',
					'prefix' => '<module>/carousel',
					'routePrefix' => '<module>',
					'rules' => [
						[
							'pattern' => '<carousel_id:([0-9a-fA-F-]{36}|\d+|__\w+__)>/<controller>/<id:([0-9a-fA-F-]{36}|\d+|__\w+__)>/<action>',
							'route' => '<controller>/<action>',
						],
						[
							'pattern' => '<carousel_id:([0-9a-fA-F-]{36}|\d+|__\w+__)>/<controller>/<action>',
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
