<?php
return [
	'aliases' => [
		'@setting' => dirname(__DIR__),
	],
	'modules' => [
		'language-manager' => [
			'class' => 'backend\modules\setting\modules\language\Module',
		],
		'currency-manager' => [
			'class' => 'backend\modules\setting\modules\currency\Module',
		],
		'integration-manager' => [
			'class' => 'backend\modules\setting\modules\integration\Module',
		],
	],
	'components' => [
		'urlManager' => [
			'class' => 'yii\web\UrlManager',
			'rules' => [
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
