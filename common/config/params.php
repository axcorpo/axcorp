<?php
return [
	// This parameter is used to remove a specific URL segment in order to get the right uploads URL
	'upload.ignoredBasePaths' => ['admin', 'dashboard'],

	// Grafana Loki log target. Real url/credentials go in params-local.php (git-ignored).
	// Inert until loki.enabled is true and loki.pushUrl is set.
	'loki.enabled' => false,
	'loki.pushUrl' => '', // e.g. https://loki.example.com/loki/api/v1/push
	'loki.authUser' => '',
	'loki.authPassword' => '',

	// The master hub endpoint tenants POST their announcements to. Written into each tenant's
	// config at install (with its per-workspace api_token). Override in params-local.php.
	'masterApi.url' => 'https://storedo/api/v1/announcements',

	// The Facebook app (Login for Business) every platform connects its page through. Written
	// into each tenant's own common/config/params-local.php at install (and by
	// `php yii workspace-install/params-config` for the installed ones). Set in params-local.php.
	'facebook.appId' => '',
	'facebook.appSecret' => '',
	'facebook.configId' => '',
	'facebook.graphVersion' => 'v26.0',
	// The Facebook app visitors log in with ("Log in with Facebook"), when it is not the page
	// app above: a Business app cannot offer consumer Facebook Login. Written into each tenant's
	// params-local.php like the ones above; empty = the page app.
	'facebook.loginAppId' => '',
	'facebook.loginAppSecret' => '',

	// Signs the backend "login as" tokens the frontend assume-identity action accepts.
	// Must be the same random secret for backend and frontend; set it in params-local.php.
	// Empty turns the feature off.
	'assumeIdentity.key' => '',
	'defaultLanguage' => 'en-US',
	'languages' => [
		'en' => 'en-US',
		'ro' => 'ro-RO',
		'de' => 'de-DE',
	],

	// Allow-list for every image upload (profile avatars, carousels, categories, workspace
	// items). Both keys are read together by the `file` validators; `mimeTypes` makes the
	// validator sniff the real content instead of trusting the submitted file name.
	'image.extensions' => ['jpeg', 'jpg', 'png', 'gif'],
	'image.mimeTypes' => ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'],

	// Allow-list for non-image uploads (support-ticket attachments). Deliberately excludes
	// anything the web server could be talked into executing.
	'file.extensions' => ['jpeg', 'jpg', 'png', 'gif', 'pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip'],
	'file.mimeTypes' => [
		'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
		'application/pdf', 'text/plain', 'text/csv',
		'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'application/zip', 'application/x-zip-compressed',
	],

	// Non-public storage for affiliate invoices and payment proofs (PrivateFileHelper).
	// Null = master/private_uploads; set an absolute path outside the web root in
	// params-local.php to keep them elsewhere.
	'privateUploads.path' => null,

	'schedule.enabled' => false,

	'user.loginDuration' => 60 * 60 * 3600,
	'user.loginTokenExpiration' => 60 * 60 * 3600,
	'user.passwordResetTokenExpiration' => 60 * 60 * 3600,
	'user.signupTokenExpiration' => 60 * 60 * 3600,
	'user.accountActivation' => \common\models\User::ACCOUNT_ACTIVATION_CONFIRMATION,

	// Google Translate API key — set the real value in params-local.php (kept out of VCS)
	'google.translateKey' => '',

	// Recipient of the workspace-health/check alert emails (dead tenant cron, failing
	// scheduled tasks). Set the real address in params-local.php; null disables emails.
	'healthAlert.email' => null,
];
