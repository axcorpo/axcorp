<?php
return [
	// NOTE: do not register an '@workspace' alias here — it would shadow the global
	// alias from common/config/bootstrap.php that points to the tenant source app
	// (/workspace), breaking Workspace::install() and the subscription upgrade forms.
	'components' => [
		'urlManager' => [
			'class' => 'yii\web\UrlManager',
			'rules' => [
				// Specific routes
				[
					'class' => 'yii\web\GroupUrlRule',
					'prefix' => '<module>/field',
					'routePrefix' => '<module>',
					'rules' => [
						[
							'pattern' => '<field_id:([0-9a-fA-F-]{36}|\d+|__\w+__)>/<controller>/<id:([0-9a-fA-F-]{36}|\d+|__\w+__)>/<action>',
							'route' => '<controller>/<action>',
						],
						[
							'pattern' => '<field_id:([0-9a-fA-F-]{36}|\d+|__\w+__)>/<controller>/<action>',
							'route' => '<controller>/<action>',
						],
					],
				],
				// Default routes
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
