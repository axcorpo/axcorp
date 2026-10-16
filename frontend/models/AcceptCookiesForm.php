<?php

namespace frontend\models;

use Yii;
use yii\base\Model;
use yii\web\Cookie;

/**
 * The visitor's cookie consent, kept in the `acceptCookies` cookie as the list of accepted
 * categories (e.g. `necessary,analytics`).
 *
 * The necessary category is always in the list: it covers the session, identity, affiliate
 * and consent cookies themselves, which need no consent. Analytics and marketing are opt-in:
 * the layouts ask allows() before emitting the Google Analytics and Facebook scripts, and a
 * visitor who has not answered the banner yet is treated as having refused them.
 */
class AcceptCookiesForm extends Model
{
	/**
	 * The cookie that stores the accepted categories.
	 */
	const COOKIE = 'acceptCookies';

	/**
	 * The consent categories.
	 */
	const CATEGORY_NECESSARY = 'necessary';
	const CATEGORY_ANALYTICS = 'analytics';
	const CATEGORY_MARKETING = 'marketing';

	/**
	 * @var string[] Accepted categories field.
	 */
	public $acceptCookies;

	/**
	 * @var string Back Url field.
	 */
	public $backUrl;

	/**
	 * @var string The honeypot field.
	 */
	public $workEmail;

	/**
	 * All the consent categories, the necessary one first.
	 *
	 * @return string[]
	 */
	public static function categories()
	{
		return [static::CATEGORY_NECESSARY, static::CATEGORY_ANALYTICS, static::CATEGORY_MARKETING];
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['acceptCookies'], 'required'],
			['acceptCookies', 'each', 'rule' => ['in', 'range' => static::categories()]],
			['backUrl', 'safe'],
			['workEmail', 'safe'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [];
	}

	/**
	 * Whether the visitor accepted a category. Without a consent cookie only the necessary
	 * category is allowed.
	 *
	 * @param string $category one of the CATEGORY_* constants
	 * @return bool
	 */
	public static function allows($category)
	{
		$value = Yii::$app->getRequest()->getCookies()->getValue(static::COOKIE);
		if ($value === null) {
			return $category === static::CATEGORY_NECESSARY;
		}
		return in_array($category, explode(',', $value), true);
	}

	/**
	 * Whether the visitor has answered the banner.
	 *
	 * @return bool
	 */
	public static function answered()
	{
		// The previous banner stored a bare "1" (a blanket yes): that visitor has not chosen
		// categories yet, so the banner asks again.
		return static::allows(static::CATEGORY_NECESSARY)
			&& Yii::$app->getRequest()->getCookies()->has(static::COOKIE);
	}

	/**
	 * Stores the accepted categories, the necessary one always included, for a year.
	 *
	 * @return bool whether the cookie was set.
	 */
	public function setCookies()
	{
		$categories = array_values(array_intersect(
			static::categories(),
			array_merge([static::CATEGORY_NECESSARY], (array) $this->acceptCookies)
		));
		$cookie = new Cookie([
			'name' => static::COOKIE,
			'value' => implode(',', $categories),
			'expire' => time() + 86400 * 365,
			'httpOnly' => true,
			'sameSite' => Cookie::SAME_SITE_LAX,
		]);
		Yii::$app->getResponse()->getCookies()->add($cookie);
		return true;
	}
}
