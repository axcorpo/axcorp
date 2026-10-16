<?php

namespace common\helpers;

/**
 * File system helper.
 *
 * Overrides the symbolic link creation so that, on Windows, it uses directory
 * junctions (`mklink /J`) instead of symbolic links (`mklink /D`). Junctions do
 * not require administrator privileges / Developer Mode, which makes workspace
 * provisioning work from a regular web/CLI process.
 *
 * @inheritdoc
 */
class FileHelper extends \tws\helpers\FileHelper
{
	/**
	 * Creates an operating system independent symbolic link.
	 *
	 * On Windows a directory junction is created (does not require elevation); on
	 * other systems a regular symbolic link is created. Missing sources and already
	 * existing destinations are skipped.
	 *
	 * @param string|array $target a single link target or an array of multiple source => link pairs.
	 * @param string|null $link the link name, ignored when $target is an array.
	 * @return bool always true.
	 */
	public static function symlink($target, $link = null)
	{
		$pairs = is_array($target) ? $target : [$target => $link];

		foreach ($pairs as $src => $dst) {
			// Skip when the source is missing or the destination already exists.
			if (!file_exists($src) || file_exists($dst)) {
				continue;
			}

			if (DIRECTORY_SEPARATOR === '\\') {
				// Use a directory junction (mklink /J) instead of a symbolic link (mklink /D):
				// junctions do not require administrator privileges / Developer Mode on Windows.
				// Junctions must reference an absolute target.
				$cmd = sprintf(
					'mklink /J %s %s',
					escapeshellarg(str_replace('/', '\\', rtrim($dst, '/\\'))),
					escapeshellarg(str_replace('/', '\\', rtrim($src, '/\\')))
				);
				exec($cmd . ' 2>&1');
			} else {
				// Create a RELATIVE symlink so it keeps resolving across different mount
				// roots (host vs Docker container vs cPanel). An absolute target such as
				// /var/www/html/workspace/... becomes an invalid/broken link the moment
				// the same tree is accessed under a different root (e.g. on the host).
				@symlink(static::relativeSymlinkTarget($src, $dst), $dst);
			}
		}

		return true;
	}

	/**
	 * Computes the link target relative to the link's own directory, so the symlink
	 * resolves regardless of the absolute filesystem root the tree is mounted under.
	 *
	 * Both arguments are expected to be absolute paths.
	 *
	 * @param string $src absolute path of the link target (source).
	 * @param string $dst absolute path of the link itself (destination).
	 * @return string relative path from the link's directory to the source.
	 */
	protected static function relativeSymlinkTarget($src, $dst)
	{
		$from = explode('/', trim(str_replace('\\', '/', dirname($dst)), '/'));
		$to = explode('/', trim(str_replace('\\', '/', $src), '/'));

		// Drop the shared leading path segments.
		while (!empty($from) && !empty($to) && $from[0] === $to[0]) {
			array_shift($from);
			array_shift($to);
		}

		return str_repeat('../', count($from)) . implode('/', $to);
	}
}
