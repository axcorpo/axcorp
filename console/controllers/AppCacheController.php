<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\FileHelper;

/**
 * Flushes the runtime caches of ALL applications (frontend, backend, console).
 *
 * `php yii cache/flush-all` only reaches the cache of the app it runs in (each app
 * has its own FileCache under its `runtime/cache`), so schema or data cached by the
 * web apps survives a console flush. After schema changes (migrations, the UUID
 * conversion) that is exactly the cache that must go — stale `ColumnSchema` entries
 * make the web app misread BINARY(16) columns.
 *
 * Usage:
 * ```
 * php yii app-cache/flush
 * ```
 */
class AppCacheController extends Controller
{
	/**
	 * @var string[] Cache directories of every application, relative to the project root.
	 */
	public $cacheDirs = [
		'@frontend/runtime/cache',
		'@backend/runtime/cache',
		'@console/runtime/cache',
		// The api app defines its @api alias only in its own config, so go through @root
		'@root/api/runtime/cache',
	];

	/**
	 * Flushes the runtime cache of every application.
	 *
	 * @return int
	 */
	public function actionFlush()
	{
		foreach ($this->cacheDirs as $alias) {
			$path = Yii::getAlias($alias);
			if (!is_dir($path)) {
				$this->stdout("  skip {$alias} (missing)\n", Console::FG_YELLOW);
				continue;
			}
			foreach (FileHelper::findDirectories($path, ['recursive' => false]) as $dir) {
				FileHelper::removeDirectory($dir);
			}
			foreach (FileHelper::findFiles($path, ['recursive' => false]) as $file) {
				@unlink($file);
			}
			$this->stdout("  flushed {$alias}\n", Console::FG_GREEN);
		}
		$this->stdout("Done.\n", Console::FG_GREEN);
		return ExitCode::OK;
	}
}
