<?php

namespace common\filters;

use Yii;
use yii\base\ActionFilter;
use yii\web\TooManyRequestsHttpException;

/**
 * Throttle for the credential and mail-sending endpoints.
 *
 * Yii's own RateLimiter keys on the identity, which is no use on exactly the actions that
 * matter here — login, signup and password reset are reached before anyone is
 * authenticated, so an attacker is never the same "user" twice.
 *
 * Two counters run per request, and either one tripping refuses it:
 *
 * - **by address** ([[limit]] / [[window]]), which catches one client working through a
 *   list of accounts;
 * - **by identifier** ([[identityParams]], [[identityLimit]] / [[identityWindow]]), which
 *   catches the opposite shape — many addresses against one account, and the mail/SMS
 *   flooding of one inbox that an address-only counter cannot see at all.
 *
 * The identifier counter is deliberately more generous than the address one. Throttling
 * by e-mail means an attacker can lock a known account out of its own password reset by
 * hammering it, so the limit is set where it stops flooding without making that cheap.
 *
 * Counting lives in the cache, so it is best-effort by construction: a flush forgets the
 * window. Two deployment facts decide whether it binds at all:
 *
 * 1. **The cache must be shared across web nodes.** The default is a FileCache, which is
 *    per-server, so on more than one node an attacker simply gets one budget per node.
 * 2. **`request.trustedHosts` must be configured if anything proxies this app.** Yii
 *    returns REMOTE_ADDR from getUserIP() and ignores X-Forwarded-For until it is told
 *    which proxies to believe. Behind a load balancer or a CDN that means every request
 *    carries the proxy's address, all visitors share one counter, and the first ten
 *    arrivals lock out everyone else. [[isAddressUsable()]] refuses to enforce the
 *    address counter when it cannot tell clients apart, so a misconfiguration degrades
 *    to "no address throttling" rather than to an outage — but it is logged, and the
 *    identifier counter keeps working either way.
 */
class RateLimit extends ActionFilter
{
	/**
	 * @var int How many requests one address may make within [[window]].
	 */
	public $limit = 10;

	/**
	 * @var int The length of the address counting window, in seconds.
	 */
	public $window = 900;

	/**
	 * @var string[] Body params identifying the target account (e.g. `username`, `email`).
	 * Empty disables the identifier counter.
	 */
	public $identityParams = [];

	/**
	 * @var int How many requests may name the same identifier within [[identityWindow]].
	 */
	public $identityLimit = 5;

	/**
	 * @var int The length of the identifier counting window, in seconds.
	 */
	public $identityWindow = 3600;

	/**
	 * @var string Cache key prefix, so unrelated filters cannot share a counter.
	 */
	public $keyPrefix = 'ratelimit';

	/**
	 * @var bool Whether GET requests count. They are reads — the form page itself is a
	 * GET — so by default only the submissions are counted.
	 */
	public $countSafeRequests = false;

	/**
	 * @var bool Whether the limits are enforced. Off in the dev environment, where
	 * repeated logins and form submissions are part of the work; still on in test and prod.
	 */
	public $enabled = !YII_ENV_DEV;

	/**
	 * {@inheritdoc}
	 */
	public function beforeAction($action)
	{
		if (!$this->enabled) {
			return parent::beforeAction($action);
		}
		if (!$this->countSafeRequests && Yii::$app->request->isGet) {
			return parent::beforeAction($action);
		}

		$cache = Yii::$app->has('cache') ? Yii::$app->cache : null;
		if ($cache === null) {
			// No cache configured: let the request through rather than lock the site out.
			return parent::beforeAction($action);
		}

		if ($this->isAddressUsable()) {
			$this->hit(
				$cache,
				$this->key($action, 'ip', (string) Yii::$app->request->userIP),
				$this->limit,
				$this->window,
				$action
			);
		}

		foreach ($this->identityValues() as $value) {
			$this->hit(
				$cache,
				$this->key($action, 'id', $value),
				$this->identityLimit,
				$this->identityWindow,
				$action
			);
		}

		return parent::beforeAction($action);
	}

	/**
	 * Counts one request against a counter and refuses it once the budget is spent.
	 *
	 * @param \yii\caching\CacheInterface $cache
	 * @param string $key
	 * @param int $limit
	 * @param int $window
	 * @param \yii\base\Action $action
	 * @throws TooManyRequestsHttpException
	 */
	protected function hit($cache, $key, $limit, $window, $action)
	{
		$now = time();
		$entry = $cache->get($key);

		// A fixed window, carried in the value rather than left to the TTL: re-setting a
		// key renews its expiry, so counting that way would let each further attempt push
		// the window out and a user fumbling their password would extend their own
		// lockout indefinitely.
		if (!is_array($entry) || ($now - $entry['start']) >= $window) {
			$entry = ['count' => 0, 'start' => $now];
		}

		if ($entry['count'] >= $limit) {
			Yii::warning(
				"Rate limit hit on {$action->getUniqueId()} from " . Yii::$app->request->userIP,
				__METHOD__
			);
			throw new TooManyRequestsHttpException(
				Yii::t('common', 'Too many attempts. Please try again later.')
			);
		}

		$entry['count']++;
		$cache->set($key, $entry, $window - ($now - $entry['start']));
	}

	/**
	 * Whether the client address is specific enough to count against.
	 *
	 * @return bool
	 */
	protected function isAddressUsable()
	{
		$ip = Yii::$app->request->userIP;
		if ($ip === null || $ip === '') {
			return false;
		}
		// Every request arriving from the same loopback address is the signature of an
		// unconfigured reverse proxy: the real client is in X-Forwarded-For, which Yii
		// ignores until request.trustedHosts says which proxies to believe. Counting
		// those together would throttle the whole site as one visitor.
		if (in_array($ip, ['127.0.0.1', '::1'], true) && !YII_ENV_TEST && !YII_ENV_DEV) {
			Yii::warning(
				'Rate limiting by address is off: every request reports the loopback address, '
					. 'which means a proxy is in front and request.trustedHosts is not configured.',
				__METHOD__
			);
			return false;
		}

		return true;
	}

	/**
	 * The identifier values named by this request, normalised so that casing and padding
	 * cannot buy extra attempts.
	 *
	 * @return string[]
	 */
	protected function identityValues()
	{
		$values = [];
		foreach ($this->identityParams as $param) {
			$value = Yii::$app->request->getBodyParam($param);
			// Yii nests form fields under the model name (LoginForm[username]), so look
			// one level in as well.
			if ($value === null) {
				foreach ((array) Yii::$app->request->getBodyParams() as $group) {
					if (is_array($group) && isset($group[$param])) {
						$value = $group[$param];
						break;
					}
				}
			}
			if (is_string($value) && trim($value) !== '') {
				$values[] = mb_strtolower(trim($value));
			}
		}

		return array_unique($values);
	}

	/**
	 * @param \yii\base\Action $action
	 * @param string $kind
	 * @param string $value
	 * @return string
	 */
	protected function key($action, $kind, $value)
	{
		// Hashed so an e-mail address is not sitting in a cache file name.
		return implode(':', [$this->keyPrefix, $action->getUniqueId(), $kind, sha1($value)]);
	}
}
