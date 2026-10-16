<?php

namespace common\helpers;

use Yii;

class UrlHelper
{
	/**
	 * Gets the host url.
	 *
	 * @return string
	 */
	public static function getHostUrl()
	{
		return Yii::$app->getRequest()->getHostInfo();
	}

	/**
	 * Gets the host url without the protocol.
	 *
	 * @return string
	 */
	public static function getRelativeHostUrl()
	{
		return preg_replace('#^https?:#', '', self::getHostUrl());
	}

	/**
	 * Gets the upload directory url.
	 *
	 * @return string
	 */
	public static function getUploadsUrl()
	{
		return self::getHostUrl() . '/uploads';
	}
}