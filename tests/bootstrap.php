<?php
/**
 * PHPUnit bootstrap for the master app: boots a minimal Yii console application
 * against the dedicated `master_test` database. Provision the schema once with:
 *
 *   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS master_test CHARACTER SET utf8 COLLATE utf8_unicode_ci"
 *   mysqldump -u root -p --no-data masteranunturi workspace subscription subscriber | mysql -u root -p master_test
 *
 * Run the suite from the master directory: run-tests.bat
 */

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/../common/config/bootstrap.php';
require __DIR__ . '/../console/config/bootstrap.php';

Yii::setAlias('@tests', __DIR__);

$config = [
	'id' => 'master-tests',
	'basePath' => dirname(__DIR__),
	'controllerNamespace' => 'console\controllers',
	'language' => 'en-US',
	// The real application params, so the upload allow-lists and the rest of the
	// configuration the models read are the ones production uses rather than whatever
	// each test happens to set.
	'params' => require __DIR__ . '/../common/config/params.php',
	'components' => [
		// Catch-all message source so Yii::t() in validators/labels resolves without the
		// full application config; untranslated messages fall back to the source text.
		'i18n' => [
			'translations' => [
				'*' => [
					'class' => 'yii\i18n\PhpMessageSource',
					'basePath' => '@common/messages',
				],
			],
		],
		// The application always has one (FileCache in master/common/config/main.php), and
		// code invalidates cache tags on write — TagDependency::invalidate() fatals on a
		// null cache. In-memory so nothing leaks between test cases or onto disk.
		'cache' => [
			'class' => 'yii\caching\ArrayCache',
		],
		'db' => [
			'class' => 'yii\db\Connection',
			'dsn' => getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;port=3307;dbname=master_test',
			'username' => getenv('TEST_DB_USER') ?: 'root',
			'password' => getenv('TEST_DB_PASSWORD') !== false ? getenv('TEST_DB_PASSWORD') : 'mysql',
			'charset' => 'utf8',
			// Same as common/config/main.php: binary-safe query cache keys for BINARY(16) params
			'commandMap' => [
				'mysql' => \common\db\Command::class,
			],
		],
		// Same as common/config/main.php: UUID string user ids <-> BINARY(16) auth_assignment.user_id
		'authManager' => [
			'class' => 'common\rbac\DbManager',
			'cache' => 'cache',
			'cacheKey' => 'rbac',
		],
	],
];

new yii\console\Application($config);
