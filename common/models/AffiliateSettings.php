<?php

namespace common\models;

use Yii;

/**
 * Read access to the `affiliate` settings group, with the defaults the module runs on
 * until an admin saves the group.
 *
 * The group is an ordinary app setting row (`setting.name = 'affiliate'`, type APP,
 * serialized array) — the same storage as `general`/`email`, so
 * `Yii::$app->settings->get('holdDays', 'affiliate')` works once the row exists. The
 * backend settings form should save exactly the keys in [[DEFAULTS]].
 *
 * Code must read through this class, not `settings->get()` directly: the vendor
 * component indexes `$data[$category][$item]` without checking, so a missing group (the
 * state of every installation before the first save) would raise "Undefined array key".
 */
class AffiliateSettings
{
	/**
	 * The `setting.name` of the group.
	 */
	const GROUP = 'affiliate';

	/**
	 * Days a commission stays PENDING after the invoice is paid, so a storno can still
	 * cancel or offset it before it becomes payable.
	 */
	const HOLD_DAYS = 'holdDays';
	/**
	 * Commission percentage proposed for a new affiliate.
	 */
	const DEFAULT_PERCENTAGE = 'defaultPercentage';
	/**
	 * Smallest payable total (per currency) a payout can be created for; 0 = no minimum.
	 */
	const MINIMUM_PAYOUT = 'minimumPayout';
	/**
	 * Days the `aff` cookie remembers a referral code between the first ?aff= landing and
	 * the signup that attributes the visitor.
	 */
	const COOKIE_DAYS = 'cookieDays';

	/**
	 * @var array key => value used when the group or the key is not saved (or empty).
	 */
	const DEFAULTS = [
		self::HOLD_DAYS => 30,
		self::DEFAULT_PERCENTAGE => 10,
		self::MINIMUM_PAYOUT => 0,
		self::COOKIE_DAYS => 60,
	];

	/**
	 * A setting of the group, or its default when unset/empty.
	 *
	 * @param string $key one of the class constants
	 * @param mixed $default overrides the built-in default
	 * @return mixed
	 */
	public static function get($key, $default = null)
	{
		if ($default === null) {
			$default = static::DEFAULTS[$key] ?? null;
		}
		$group = static::getGroup();
		if (!array_key_exists($key, $group) || $group[$key] === null || $group[$key] === '') {
			return $default;
		}
		return $group[$key];
	}

	/**
	 * Every key of the group with defaults filled in — handy to populate the settings form.
	 *
	 * @return array
	 */
	public static function getAll()
	{
		$values = [];
		foreach (array_keys(static::DEFAULTS) as $key) {
			$values[$key] = static::get($key);
		}
		return $values;
	}

	/**
	 * @return int
	 */
	public static function getHoldDays()
	{
		return max(0, (int) static::get(self::HOLD_DAYS));
	}

	/**
	 * @return float
	 */
	public static function getDefaultPercentage()
	{
		return (float) static::get(self::DEFAULT_PERCENTAGE);
	}

	/**
	 * @return float
	 */
	public static function getMinimumPayout()
	{
		return max(0, (float) static::get(self::MINIMUM_PAYOUT));
	}

	/**
	 * @return int
	 */
	public static function getCookieDays()
	{
		return max(1, (int) static::get(self::COOKIE_DAYS));
	}

	/**
	 * The saved group, [] when the settings component or the group is missing.
	 *
	 * @return array
	 */
	protected static function getGroup()
	{
		if (!Yii::$app || !Yii::$app->has('settings')) {
			return [];
		}
		$all = (array) Yii::$app->settings->getAll();
		return isset($all[static::GROUP]) && is_array($all[static::GROUP]) ? $all[static::GROUP] : [];
	}
}
