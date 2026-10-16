<?php

namespace common\components;

use common\models\Affiliate;
use common\models\AffiliateSettings;
use common\models\Subscriber;
use Yii;
use yii\web\Cookie;

/**
 * Remembers which affiliate brought a visitor, from the first `?aff=CODE` landing on the
 * master site until the signup that creates their subscriber (see docs/afiliere.md).
 *
 * The code is kept in the session (this visit) and in an `aff` cookie (later visits, for
 * the `cookieDays` affiliate setting). Attribution happens only on master: the stores
 * (workspaces) know nothing about affiliates. The affiliate earns on the master invoices
 * of the subscriber they brought.
 */
class AffiliateTracker
{
	/**
	 * The query parameter, cookie and session key the code travels under.
	 */
	const PARAM = 'aff';
	const COOKIE = 'aff';
	const SESSION_KEY = 'affiliate_code';

	/**
	 * Normalizes a raw code: trimmed, uppercased, only [A-Z0-9], 4–32 chars.
	 *
	 * @param mixed $code
	 * @return string|null the code, or null when it does not have the right shape
	 */
	public static function normalize($code)
	{
		if (!is_string($code)) {
			return null;
		}
		$code = strtoupper(trim($code));

		return preg_match('/^[A-Z0-9]{4,32}$/', $code) ? $code : null;
	}

	/**
	 * Captures `?aff=CODE` from the current request into the session and the cookie.
	 * Called from the frontend controllers' beforeAction, so it must stay cheap: without
	 * the parameter it touches neither the session nor the cookies, and it does not query
	 * the database (the code is resolved at signup).
	 *
	 * @return string|null the captured code
	 */
	public static function capture()
	{
		$request = Yii::$app->request;
		if (!$request instanceof \yii\web\Request) {
			return null;
		}
		$raw = $request->get(static::PARAM);
		if ($raw === null || ($code = static::normalize($raw)) === null) {
			return null;
		}

		Yii::$app->session->set(static::SESSION_KEY, $code);
		Yii::$app->response->cookies->add(new Cookie([
			'name' => static::COOKIE,
			'value' => $code,
			'expire' => time() + AffiliateSettings::getCookieDays() * 86400,
			'httpOnly' => true,
			'sameSite' => Cookie::SAME_SITE_LAX,
		]));

		return $code;
	}

	/**
	 * The captured code for the current visitor: the session first, then the cookie.
	 *
	 * @return string|null
	 */
	public static function getCode()
	{
		if (!Yii::$app->request instanceof \yii\web\Request) {
			return null;
		}
		if (Yii::$app->has('session') && ($code = static::normalize(Yii::$app->session->get(static::SESSION_KEY)))) {
			return $code;
		}

		return static::normalize(Yii::$app->request->cookies->getValue(static::COOKIE));
	}

	/**
	 * Attributes a subscriber being CREATED to the captured affiliate. Set once: an
	 * existing subscriber or one already attributed is never touched. Only an active,
	 * non-deleted affiliate counts, and never for their own account.
	 *
	 * @param Subscriber $subscriber
	 * @return bool whether an affiliate was assigned
	 */
	public static function assign(Subscriber $subscriber)
	{
		// hasAttribute: the affiliate SQL may not have run yet; signup must not break meanwhile.
		if (!$subscriber->getIsNewRecord() || !$subscriber->hasAttribute('affiliate_id') || !empty($subscriber->affiliate_id)) {
			return false;
		}
		if (($code = static::getCode()) === null) {
			return false;
		}
		$affiliate = Affiliate::findActiveByCode($code);
		if ($affiliate === null || $affiliate->user_id === $subscriber->user_id) {
			return false;
		}
		$subscriber->affiliate_id = $affiliate->id;

		return true;
	}
}
