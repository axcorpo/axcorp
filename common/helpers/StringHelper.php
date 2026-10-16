<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace common\helpers;

/**
 * StringHelper.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Alex Makarov <sam@rmcreative.ru>
 * @author Alin Hort <alinhort@gmail.com>
 * @since 2.0
 */
class StringHelper extends \yii\helpers\BaseStringHelper
{
    /**
     * Generates a random alphanumeric string based of an integer length.
     *
     * @param int $length The number of the characters.
     * @param string|array|null $source The source from where characters should be generated.
     * @return string The generated random alphanumeric string.
     */
    public static function generateRandomAlphanumerics($length, $source = null)
    {
        if (!$source) {
            $source = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        } elseif (is_array($source)) {
            $source = implode('', $source);
        }
        // CSPRNG, not str_shuffle(): this feeds the password reset and signup codes,
        // which must not be predictable from mt_rand state.
        $result = '';
        $max = strlen($source) - 1;
        for ($i = 0; $i < $length; $i++) {
            $result .= $source[random_int(0, $max)];
        }
        return $result;
    }

    /**
     * Generates a random string of letters based of an integer length.
     *
     * @param int $length The number of the letters.
     * @param string|array|null $source The source from where letters should be generated.
     * @return string The generated random letters.
     */
    public static function generateRandomLetters($length, $source = null)
    {
        if (!$source) {
            $source = implode('', range('a','z'));
        } elseif (is_array($source)) {
            $source = implode('', $source);
        }
        return substr(str_shuffle($source), 0, $length);
    }

    /**
     * Extract initials from a given string.
     *
     * @param string $source The source from where letters should be generated.
     * @param int $length The exact number of the initials.
     * @return string|null The extracted initials.
     */
    public static function extractInitials($source, $length = null)
    {
        if (!is_string($source) || strlen($source) == 0) {
            return null;
        }

        $initials = [];
        if (!($sourceParts = preg_split('/\s+/', $source))) {
            $sourceParts = [$source];
        }
        if ($length > 0) {
            // Split by letters if there is only one word
            if (count($sourceParts) === 1) {
                preg_match_all('/[a-zA-Z]/', $source, $matches);
                $sourceParts = $matches[0];
            }
        }
        // Extract the first letter of each part
        foreach ($sourceParts as $sourcePart) {
            $initials[] = substr($sourcePart, 0, 1);
        }
        if ($length > 0) {
            $initials = array_slice($initials, 0, $length);
        }

        return implode('', $initials);
    }

	/**
	 * Replaces all items in a source.
	 *
	 * @param string $source The source string.
	 * @param array $items The items to be replaced.
	 * @return mixed|string
	 */
	public static function replaceMultiple($source, $items)
	{
		// Exit if the source or items are invalid
		if (!is_string($source) || !is_array($items) || empty($items)) {
			return $source;
		}
		// Replace all items in the source
		foreach ($items as $key => $value) {
			$source = str_replace($key, $value, $source);
		}
		return $source;
	}

    public static function splitString($input, $maxLength)
    {
        $parts = explode(".", $input);
        $i = 0;
        while ($i < count($parts)) {
            if (preg_match("/<[^>]*$/", $parts[$i])) {
                array_splice($parts, $i, 2, $parts[$i] . "." . $parts[$i+1]);
            } else {
                if ($i < (count($parts) - 1) && strlen($parts[$i] . "." . $parts[$i+1]) < $maxLength) {
                    array_splice($parts, $i, 2, $parts[$i] . "." . $parts[$i+1]);
                } else {
                    $i++;
                }
            }
        }
        return $parts;
    }
}
