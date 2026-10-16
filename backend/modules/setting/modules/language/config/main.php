<?php
return [
	'aliases' => [
		'@settingLanguage' => dirname(__DIR__),
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
					'pattern' => '<controller>/<id:([0-9a-fA-F-]{36}|\d+|([a-z]+)-([A-Z])+|__\w+__+)>/<action>',
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
