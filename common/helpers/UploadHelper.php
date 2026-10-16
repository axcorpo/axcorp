<?php

namespace common\helpers;

use Yii;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use yii\web\UploadedFile;

class UploadHelper
{
	/**
	 * Gets the uploads directory URL.
	 *
	 * @param bool $scheme
	 * @return string
	 */
	public static function getUploadsUrl($scheme = true)
	{
		$search = '';

		if ($baseUrl = Yii::$app->urlManager->baseUrl) {
			$baseUrlParts = array_filter(explode('/', $baseUrl));

			if (count($baseUrlParts) >= 2) {
				$search = '/' . end($baseUrlParts);
			} else {
				$search = reset($baseUrlParts);
			}
		}

		return rtrim(str_replace($search, '', Url::base($scheme)), '/') . '/uploads';
	}

    /**
     * Ensures that the path contains all the directories.
     *
     * @param $path
     * @return bool|string
     * @deprecated TODO: Use FileHelper::createDirectory instead
     */
    public static function ensureDirectoryTree($path)
    {
        // Get the uploads path
        $uploadPath = Yii::getAlias('@uploads');
        // Check the path
        if ($path) {
            // Concat extra path to the upload path
            $uploadPath .= '/' . $path;
            // If the directory does not exist
            if (!is_dir($uploadPath)) {
                // Create the directory
                mkdir($uploadPath, 0755, true);
            }
        }
        // Return the final existing path
        return FileHelper::normalizePath($uploadPath);
    }

	/**
	 * Saves the file.
	 *
	 * @param UploadedFile $uploadedFile
	 * @param string $fileName
	 * @param string $path
	 * @param bool|string|int $suffix
	 * @param bool $copy
	 * @return string|bool The file name if saving is successful or boolean false if it fails.
	 * @throws \yii\base\Exception
	 */
	public static function saveFile($uploadedFile, $fileName = '', $path = '', $suffix = true, $copy = false)
	{
		// Exit if the uploaded file is not an instance of UploadedFile class
		if (!($uploadedFile instanceof UploadedFile)) {
			return false;
		}
		// Ensure the path directory tree
		$path = Yii::getAlias($path ?: '@uploads');
		FileHelper::createDirectory($path);
		// Set a new file name or keep the base name
		$fileName = $fileName ? Inflector::slug($fileName) : $uploadedFile->getBaseName();
		// Add file name custom or random generated suffix
		$fileNameSuffix = '';
		if (!empty($suffix)) {
			$fileNameSuffix = '_' . (is_bool($suffix) ? Yii::$app->security->generateRandomString(8) : $suffix);
			$fileName .= $fileNameSuffix;
		}
		// Truncate the file name to maximum 255 characters
		$fileName = StringHelper::truncate($fileName, (255 - mb_strlen($fileNameSuffix)), '');
		// Keep the extension
		$fileName .= ('.' . $uploadedFile->getExtension());

		// Save the file and return false if saving fails
		if ($copy === true && !copy($uploadedFile->tempName, $path . '/' . $fileName)) {
			return false;
		} elseif (!$uploadedFile->saveAs($path . '/' . $fileName)) {
			return false;
		}
		return $fileName;
	}

	/**
	 * Saves a file from a base64 string.
	 *
	 * @param $data
	 * @param string $fileName
	 * @param string $path
	 * @param string $extension
	 * @param bool $suffix
	 * @return string
	 * @throws \yii\base\Exception
	 */
	public static function saveFileFromBase64($data, $fileName = '', $path = '', $extension = 'png', $suffix = true)
	{
		// Ensure the path directory tree
		$path = Yii::getAlias($path ?: '@uploads');
		FileHelper::createDirectory($path);
		// Set a new file name
		$fileName = Inflector::slug($fileName);
		// Add file name custom or random generated suffix
		$fileNameSuffix = '';
		if (!empty($suffix)) {
			$fileNameSuffix = '_' . (is_bool($suffix) ? Yii::$app->security->generateRandomString(8) : $suffix);
			$fileName .= $fileNameSuffix;
		}
		// Truncate the file name to maximum 255 characters
		$fileName = StringHelper::truncate($fileName, (255 - mb_strlen($fileNameSuffix)), '');
		// Keep the extension
		$fileName .= ('.' . $extension);
		// Process data
		try {
			// Remove headers
			$data = str_replace('data:image/png;base64,', '', $data);
			$data = str_replace(' ', '+', $data);
			// Save the file and return false if saving fails
			if (!file_put_contents("{$path}/{$fileName}", base64_decode($data))) {
				throw new \Exception();
			}
		} catch (\Exception $e) {
			return false;
		}
		return $fileName;
	}

	/**
	 * Saves the file using MultipartFormDataParser.
	 *
	 * @param UploadedFile $uploadedFile
	 * @param string $file
	 * @param string $path
	 * @return bool
	 * @throws \yii\base\Exception
	 */
	public static function saveFileMultipart($uploadedFile, $file, $path = '')
	{
		// Ensure the path directory tree
		$path = Yii::getAlias($path ?: '@uploads');
		FileHelper::createDirectory($path);
		// Copy the file
		copy($uploadedFile, "{$path}/{$file}");

		return is_file("{$path}/{$file}");
	}

	/**
	 * Moves a file from an old path to a new path.
	 *
	 * @param string $file
	 * @param string $oldPath
	 * @param string $newPath
	 * @return bool
	 * @throws \yii\base\Exception
	 */
	public static function moveFile($file, $oldPath, $newPath)
	{
		$oldPath = Yii::getAlias($oldPath ?: '@uploads');
		FileHelper::createDirectory($oldPath);

		$newPath = Yii::getAlias($newPath ?: '@uploads');
		FileHelper::createDirectory($newPath);

		return rename($oldPath . DIRECTORY_SEPARATOR . $file, $newPath . DIRECTORY_SEPARATOR . $file);
	}

	/**
	 * Removes the file.
	 *
	 * @param $file
	 * @return bool The file unlink operation status.
	 * @deprecated TODO: Use FileHelper::unlink instead
	 */
	public static function removeFile($file)
	{
		// Prepend the uploads directory path
		$file = FileHelper::normalizePath(Yii::getAlias('@uploads') . '/' . $file);
		// Check the file existence
		if (!file_exists($file) || !is_file($file)) {
			return false;
		}
		// Return the unlink operation status
		return unlink($file);
	}

	/**
	 * Whether the stored value is a link to a file hosted elsewhere rather than a file
	 * name on our own disk. Announcements submitted through a tenant's embed form carry
	 * such links in `image` and in their gallery, and those must be used as they are.
	 *
	 * @param string $value
	 * @return bool
	 */
	public static function isExternalUrl($value)
	{
		return $value && (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '//'));
	}

	/**
	 * Gets the file full URL.
	 *
	 * @param string $file
	 * @param bool $scheme
	 * @return string|null
	 */
	public static function getFileUrl($file, $scheme = true)
	{
		if (is_file(Yii::getAlias("@uploads/{$file}"))) {
			return self::getUploadsUrl($scheme) . "/{$file}";
		}
		return null;
	}

	/**
	 * Gets the entity image full URL.
	 *
	 * @param string $image
	 * @param string $entity
	 * @return string
	 */
	public static function getImageUrl($image, $entity = 'blank')
	{
		// Set default image
		$defaultImage = Url::home(true) . "img/img-placeholder-{$entity}.png";
		// Check if the file exist
		if (is_file(Yii::getAlias('@uploads') . '/' . $image)) {
			$image = self::getUploadsUrl() . "/{$image}";
		} else {
			$image = $defaultImage;
		}
		return $image;
	}

	/**
	 * Saves the file.
	 *
	 * @param $uploadedFile
	 * @param string $fileName
	 * @param string $path
	 * @param null $width
	 * @param null $height
	 * @param bool $crop
	 * @param bool $transparency
	 * @param bool $suffix
	 * @param bool $copy
	 * @return bool|string
	 * @throws \yii\base\Exception
	 */
	public static function saveImage($uploadedFile, $fileName = '', $path = '', $width = null, $height = null, $crop = false, $transparency = true, $suffix = true, $copy = false)
	{
		$new_img = ''; $new_image = '';

		// Exit if the uploaded file is not an instance of UploadedFile class
		if (!($uploadedFile instanceof UploadedFile)) {
			return false;
		}
		// Ensure the path directory tree
		$path = Yii::getAlias($path ?: '@uploads');
		FileHelper::createDirectory($path);
		// Set a new file name or keep the base name
		$fileName = $fileName ? Inflector::slug($fileName) : $uploadedFile->getBaseName();
		// Add file name custom or random generated suffix
		$fileNameSuffix = '';
		if (!empty($suffix)) {
			$fileNameSuffix = '_' . (is_bool($suffix) ? Yii::$app->security->generateRandomString(8) : $suffix);
			$fileName .= $fileNameSuffix;
		}
		// Truncate the file name to maximum 255 characters
		$fileName = StringHelper::truncate($fileName, (255 - mb_strlen($fileNameSuffix)), '');
		// Keep the extension
		$fileName .= ('.' . $uploadedFile->getExtension());

		// Save the file and return false if saving fails
		if ($copy === true && !copy($uploadedFile->tempName, $path . '/' . $fileName)) {
			return false;
		} elseif (!$uploadedFile->saveAs($path . '/' . $fileName)) {
			return false;
		}


		if (in_array(strtolower($uploadedFile->getExtension()), ['jpg', 'jpeg'])) {
			$image = imagecreatefromjpeg($path . '/' . $fileName);
		} else if (strtolower($uploadedFile->getExtension()) == 'png') {
			$image = imagecreatefrompng($path . '/' . $fileName);
		} else if (strtolower($uploadedFile->getExtension()) == 'gif') {
			$image = imagecreatefromgif($path . '/' . $fileName);
		}

		$old_width = imagesx($image);
		$old_height = imagesy($image);

		if ($crop) {

			if($width == null  || $height == null) {
				$new_width = $old_width;
				$new_height = $old_height;
			} else {
				$scale = max($width / $old_width, $height / $old_height);
				$new_width = ceil($scale * $old_width);
				$new_height = ceil($scale * $old_height);
			}

			$tmp_img = imagecreatetruecolor($new_width, $new_height);
			if ($transparency) {
				if (strtolower($uploadedFile->getExtension()) == 'png') {
					imagealphablending($tmp_img, false);
					$colorTransparent = imagecolorallocatealpha($tmp_img, 0, 0, 0, 127);
					imagefill($tmp_img, 0, 0, $colorTransparent);
					imagesavealpha($tmp_img, true);
				} elseif (strtolower($uploadedFile->getExtension()) == 'gif') {
					$trnprt_indx = imagecolortransparent($image);
					if ($trnprt_indx >= 0) {
						//its transparent
						$trnprt_color = imagecolorsforindex($image, $trnprt_indx);
						$trnprt_indx = imagecolorallocate($tmp_img, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
						imagefill($tmp_img, 0, 0, $trnprt_indx);
						imagecolortransparent($tmp_img, $trnprt_indx);
					}
				}
			} else {
				Imagefill($new_image, 0, 0, imagecolorallocate($new_image, 255, 255, 255));
			}
			imagecopyresampled($tmp_img, $image, 0, 0, 0, 0, $new_width, $new_height, $old_width, $old_height);

			if ($new_width == $width) {

				$src_x = 0;
				$src_y = ($new_height / 2) - ($height / 2);

			} elseif ($new_height == $height) {

				$src_x = ($new_width / 2) - ($width / 2);
				$src_y = 0;

			}

			$new_image = imagecreatetruecolor($width, $height);
			if ($transparency) {
				if (strtolower($uploadedFile->getExtension()) == 'png') {
					imagealphablending($new_image, false);
					$colorTransparent = imagecolorallocatealpha($new_image, 0, 0, 0, 127);
					imagefill($new_image, 0, 0, $colorTransparent);
					imagesavealpha($new_image, true);
				} elseif (strtolower($uploadedFile->getExtension()) == 'gif') {
					$trnprt_indx = imagecolortransparent($image);
					if ($trnprt_indx >= 0) {
						//its transparent
						$trnprt_color = imagecolorsforindex($image, $trnprt_indx);
						$trnprt_indx = imagecolorallocate($new_image, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
						imagefill($new_image, 0, 0, $trnprt_indx);
						imagecolortransparent($new_image, $trnprt_indx);
					}
				}
			} else {
				Imagefill($new_image, 0, 0, imagecolorallocate($new_image, 255, 255, 255));
			}

			imagecopyresampled($new_image, $tmp_img, 0, 0, $src_x, $src_y, $width, $height, $width, $height);
			imagedestroy($tmp_img);

		} else {

			if($width == null || $height == null) {
				$new_width = $old_width;
				$new_height = $old_height;
			} else {
				$scale = min($width / $old_width, $height / $old_height);
				$new_width = ceil($scale * $old_width);
				$new_height = ceil($scale * $old_height);
			}
			$new_image = imagecreatetruecolor($new_width, $new_height);
			if ($transparency) {
				if (strtolower($uploadedFile->getExtension()) == 'png') {
					imagealphablending($new_image, false);
					$colorTransparent = imagecolorallocatealpha($new_image, 0, 0, 0, 127);
					imagefill($new_image, 0, 0, $colorTransparent);
					imagesavealpha($new_image, true);
				} elseif (strtolower($uploadedFile->getExtension()) == 'gif') {
					$trnprt_indx = imagecolortransparent($image);
					if ($trnprt_indx >= 0) {
						//its transparent
						$trnprt_color = imagecolorsforindex($image, $trnprt_indx);
						$trnprt_indx = imagecolorallocate($new_image, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
						imagefill($new_image, 0, 0, $trnprt_indx);
						imagecolortransparent($new_image, $trnprt_indx);
					}
				}
			} else {
				Imagefill($new_img, 0, 0, imagecolorallocate($new_img, 255, 255, 255));
			}
			imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $old_width, $old_height);

		}

		if (in_array(strtolower($uploadedFile->getExtension()), ['jpg', 'jpeg'])) {
			imagejpeg($new_image, $path . '/' . $fileName, 100);
		} else if (strtolower($uploadedFile->getExtension()) == 'png') {
			imagepng($new_image, $path . '/' . $fileName);
		} else if (strtolower($uploadedFile->getExtension()) == 'gif') {
			imagegif($new_image, $path . '/' . $fileName);
		}

		imagedestroy($image);
		imagedestroy($new_image);

		return $fileName;
	}
}
