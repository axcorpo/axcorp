<?php

namespace common\helpers;

use Yii;
use yii\base\InvalidArgumentException;
use yii\helpers\FileHelper;
use yii\validators\FileValidator;
use yii\web\UploadedFile;

/**
 * Storage for documents that must NOT be web-reachable (affiliate invoices, payment
 * proofs): `master/uploads` is served directly by Apache, this tree is not.
 *
 * The base directory is the `privateUploads.path` param, defaulting to
 * `master/private_uploads` — outside every web root and every rewrite rule; its
 * `.htaccess` (`Require all denied`) is only defence in depth, and its `.gitignore`
 * keeps the content out of the repository.
 *
 * Files are stored under a caller-chosen subdirectory (e.g. `affiliate-payout/<uuid>`)
 * with a random name and an extension taken from a validated allow-list — nothing from
 * the client's file name survives, so no path or executable extension can be smuggled in.
 * Controllers read them back through [[resolve()]] after their own ownership/permission
 * check and stream them with `Yii::$app->response->sendFile()`.
 *
 * Usage:
 * ```php
 * $errors = PrivateFileHelper::validate($file);          // [] when acceptable
 * $name = PrivateFileHelper::save($file, $payout->getFileSubdir());
 * $path = PrivateFileHelper::resolve($payout->getFileSubdir(), $name);
 * ```
 * Forms can use [[fileRule()]] to get the same checks as a validation rule.
 */
class PrivateFileHelper
{
	/**
	 * Accepted extensions (documents and scans only).
	 */
	const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

	/**
	 * Accepted sniffed MIME types — the content must match, not just the name.
	 */
	const MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];

	/**
	 * Size limit when the `maxFileSize` setting is not available (bytes).
	 */
	const DEFAULT_MAX_SIZE = 10485760;

	/**
	 * A subdirectory segment / stored name shape — what [[resolve()]] accepts.
	 */
	const SEGMENT_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_-]*$/';
	const NAME_PATTERN = '/^[a-f0-9]{32}\.(pdf|jpg|jpeg|png)$/';

	/**
	 * The absolute base directory of the private storage.
	 *
	 * @return string
	 */
	public static function getBasePath()
	{
		$path = Yii::$app->params['privateUploads.path'] ?? null;
		return FileHelper::normalizePath(Yii::getAlias($path ?: '@root/private_uploads'));
	}

	/**
	 * The upload size limit in bytes: the `maxFileSize` general setting (as the other
	 * upload forms use), or [[DEFAULT_MAX_SIZE]].
	 *
	 * @return int
	 */
	public static function getMaxSize()
	{
		$size = null;
		if (Yii::$app->has('settings')) {
			$general = (array) (Yii::$app->settings->getAll()['general'] ?? []);
			$size = $general['maxFileSize'] ?? null;
		}
		return (int) $size > 0 ? (int) $size : static::DEFAULT_MAX_SIZE;
	}

	/**
	 * A validation rule with the storage's checks, for upload forms:
	 * `PrivateFileHelper::fileRule('invoiceFile')` or `fileRule('proofFiles', 10)`.
	 *
	 * @param string|string[] $attributes
	 * @param int $maxFiles
	 * @param bool $skipOnEmpty
	 * @return array
	 */
	public static function fileRule($attributes, $maxFiles = 1, $skipOnEmpty = true)
	{
		return [(array) $attributes, 'file'] + static::validatorConfig($maxFiles, $skipOnEmpty);
	}

	/**
	 * Checks an upload against the allow-list (extension AND sniffed content), the size
	 * limit and the upload error code.
	 *
	 * @param UploadedFile|null $file
	 * @return string[] the error messages, [] when the file is acceptable
	 */
	public static function validate($file)
	{
		if (!$file instanceof UploadedFile) {
			return [Yii::t('error', 'Please upload a file.')];
		}
		$validator = new FileValidator(static::validatorConfig(1, false));
		$error = null;
		if (!$validator->validate($file, $error)) {
			return [$error];
		}
		return [];
	}

	/**
	 * Validates and stores an upload under `$subdir`.
	 *
	 * @param UploadedFile $file
	 * @param string $subdir relative, `/`-separated, segments matching [[SEGMENT_PATTERN]]
	 * @param bool $deleteTempFile false copies instead of moving (non-HTTP files, tests)
	 * @return string the stored name (keep it on the record; resolve it with [[resolve()]])
	 * @throws InvalidArgumentException when the file or the subdirectory is not acceptable
	 * @throws \yii\base\Exception when the directory cannot be created or the file not written
	 */
	public static function save(UploadedFile $file, $subdir, $deleteTempFile = true)
	{
		$errors = static::validate($file);
		if ($errors) {
			throw new InvalidArgumentException(reset($errors));
		}
		$directory = static::directory($subdir);
		if ($directory === null) {
			throw new InvalidArgumentException('Invalid private uploads subdirectory.');
		}
		FileHelper::createDirectory($directory, 0750);

		// The validator has already matched the extension to the allow-list and the content.
		$name = bin2hex(random_bytes(16)) . '.' . strtolower($file->extension);
		$target = $directory . DIRECTORY_SEPARATOR . $name;
		$saved = $deleteTempFile ? $file->saveAs($target) : copy($file->tempName, $target);
		if (!$saved) {
			throw new \yii\base\Exception('The file could not be stored.');
		}

		return $name;
	}

	/**
	 * The absolute path of a stored file, or null when the name/subdirectory is malformed
	 * (traversal attempts included) or the file does not exist. It performs NO
	 * authorization — the caller checks ownership first.
	 *
	 * @param string $subdir
	 * @param string $name
	 * @return string|null
	 */
	public static function resolve($subdir, $name)
	{
		if (!is_string($name) || !preg_match(static::NAME_PATTERN, $name)) {
			return null;
		}
		$directory = static::directory($subdir);
		if ($directory === null) {
			return null;
		}
		$path = $directory . DIRECTORY_SEPARATOR . $name;
		return is_file($path) ? $path : null;
	}

	/**
	 * Deletes a stored file; true when it is gone (or was never there).
	 *
	 * @param string $subdir
	 * @param string $name
	 * @return bool
	 */
	public static function delete($subdir, $name)
	{
		$path = static::resolve($subdir, $name);
		return $path === null || @unlink($path);
	}

	/**
	 * The MIME type to stream a stored file with.
	 *
	 * @param string $path as returned by [[resolve()]]
	 * @return string
	 */
	public static function getMimeType($path)
	{
		return FileHelper::getMimeType($path) ?: 'application/octet-stream';
	}

	/**
	 * The absolute directory of a subdirectory, null when any segment is malformed.
	 *
	 * @param string $subdir
	 * @return string|null
	 */
	protected static function directory($subdir)
	{
		if (!is_string($subdir) || $subdir === '') {
			return null;
		}
		$segments = explode('/', trim(str_replace('\\', '/', $subdir), '/'));
		foreach ($segments as $segment) {
			if (!preg_match(static::SEGMENT_PATTERN, $segment)) {
				return null;
			}
		}
		return static::getBasePath() . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
	}

	/**
	 * @param int $maxFiles
	 * @param bool $skipOnEmpty
	 * @return array FileValidator configuration
	 */
	protected static function validatorConfig($maxFiles, $skipOnEmpty)
	{
		return [
			'extensions' => static::EXTENSIONS,
			'mimeTypes' => static::MIME_TYPES,
			'checkExtensionByMimeType' => true,
			'maxSize' => static::getMaxSize(),
			'maxFiles' => $maxFiles,
			'skipOnEmpty' => $skipOnEmpty,
		];
	}
}
