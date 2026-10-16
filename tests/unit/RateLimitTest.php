<?php

namespace tests\unit;

use common\filters\RateLimit;
use tests\WebTestCase;
use Yii;
use yii\base\Action;
use yii\web\Controller;
use yii\web\TooManyRequestsHttpException;

/**
 * The per-IP throttle on the credential endpoints.
 *
 * Yii's own RateLimiter keys on the identity, which is no help on login, signup and
 * password reset — nobody is authenticated yet, so an attacker is never the same user
 * twice. These cases cover the counting this replaces it with, and in particular the
 * fixed window: counting through the TTL alone would let each further attempt renew the
 * key and a user fumbling their password would extend their own lockout indefinitely.
 */
class RateLimitTest extends WebTestCase
{
	/**
	 * @param string $id
	 * @return Action
	 */
	private function action($id = 'login')
	{
		return new Action($id, new Controller('site', Yii::$app));
	}

	/**
	 * @param array $config
	 * @return RateLimit
	 */
	private function filter(array $config = [])
	{
		return new RateLimit(array_merge(['limit' => 3, 'window' => 900], $config));
	}

	protected function setUp(): void
	{
		parent::setUp();
		Yii::$app->cache->flush();
		$_SERVER['REQUEST_METHOD'] = 'POST';
		// PHPUnit runs without REMOTE_ADDR, and the filter refuses to count against an
		// address it does not have -- so a request has to look like one.
		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
		Yii::$app->request->setBodyParams(['username' => 'someone']);
	}

	protected function tearDown(): void
	{
		unset($_SERVER['REMOTE_ADDR']);
		parent::tearDown();
	}

	public function testAnAddresslessRequestIsNotCountedAgainstEveryoneElse()
	{
		// Behind an unconfigured proxy every request looks identical; counting those
		// together would throttle the whole site as one visitor.
		unset($_SERVER['REMOTE_ADDR']);
		$filter = $this->filter(['limit' => 1]);
		$action = $this->action();

		$this->assertTrue($filter->beforeAction($action));
		$this->assertTrue($filter->beforeAction($action), 'the site must not lock itself out');
	}

	public function testRequestsUnderTheLimitPass()
	{
		$filter = $this->filter();
		$action = $this->action();

		for ($i = 0; $i < 3; $i++) {
			$this->assertTrue($filter->beforeAction($action), "attempt {$i} should pass");
		}
	}

	public function testTheRequestPastTheLimitIsRefused()
	{
		$filter = $this->filter();
		$action = $this->action();

		for ($i = 0; $i < 3; $i++) {
			$filter->beforeAction($action);
		}

		$this->expectException(TooManyRequestsHttpException::class);
		$filter->beforeAction($action);
	}

	public function testActionsAreCountedSeparately()
	{
		$filter = $this->filter();

		for ($i = 0; $i < 3; $i++) {
			$filter->beforeAction($this->action('login'));
		}

		$this->assertTrue(
			$filter->beforeAction($this->action('signup')),
			'exhausting one action must not lock out another'
		);
	}

	public function testGetRequestsAreNotCounted()
	{
		$filter = $this->filter();
		$action = $this->action();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		Yii::$app->request->setBodyParams([]);

		for ($i = 0; $i < 10; $i++) {
			$this->assertTrue($filter->beforeAction($action), 'loading the form page is free');
		}
	}

	public function testAttemptsDoNotExtendTheWindow()
	{
		// The window is carried in the value, not the TTL, so the deadline set by the
		// first attempt is the one that stands.
		$filter = $this->filter(['limit' => 5]);
		$action = $this->action();

		$filter->beforeAction($action);
		$key = $this->addressKey($action);
		$first = Yii::$app->cache->get($key);

		$filter->beforeAction($action);
		$second = Yii::$app->cache->get($key);

		$this->assertSame($first['start'], $second['start'], 'the window start must not move');
		$this->assertSame(2, $second['count']);
	}

	public function testTheWindowIsForgottenOnceItHasElapsed()
	{
		$filter = $this->filter(['window' => 1]);
		$action = $this->action();

		for ($i = 0; $i < 3; $i++) {
			$filter->beforeAction($action);
		}

		// Reach into the stored entry rather than sleeping: backdating the start is the
		// same thing the clock would do.
		$key = $this->addressKey($action);
		$entry = Yii::$app->cache->get($key);
		$entry['start'] -= 3600;
		Yii::$app->cache->set($key, $entry, 3600);

		$this->assertTrue($filter->beforeAction($action), 'a lockout must not be permanent');
	}

	/**
	 * The filter hashes its key parts, so the tests ask it rather than rebuild the string.
	 *
	 * @param Action $action
	 * @return string
	 */
	private function addressKey($action)
	{
		return implode(':', [
			'ratelimit',
			$action->getUniqueId(),
			'ip',
			sha1((string) Yii::$app->request->userIP),
		]);
	}

	public function testTheSameAccountIsThrottledAcrossAddresses()
	{
		// The shape an address counter cannot see: many clients working on one account,
		// or one inbox being flooded with reset mail.
		$filter = $this->filter(['limit' => 1000, 'identityParams' => ['username'], 'identityLimit' => 3]);
		$action = $this->action();
		Yii::$app->request->setBodyParams(['username' => 'victim@example.test']);

		for ($i = 0; $i < 3; $i++) {
			$this->assertTrue($filter->beforeAction($action));
		}

		$this->expectException(TooManyRequestsHttpException::class);
		$filter->beforeAction($action);
	}

	public function testIdentifiersAreNormalisedBeforeCounting()
	{
		// Casing and padding must not buy extra attempts.
		$filter = $this->filter(['limit' => 1000, 'identityParams' => ['username'], 'identityLimit' => 2]);
		$action = $this->action();

		foreach (['victim@example.test', '  VICTIM@Example.test  '] as $variant) {
			Yii::$app->request->setBodyParams(['username' => $variant]);
			$filter->beforeAction($action);
		}

		Yii::$app->request->setBodyParams(['username' => 'Victim@Example.Test']);
		$this->expectException(TooManyRequestsHttpException::class);
		$filter->beforeAction($action);
	}

	public function testIdentifiersAreFoundInsideTheFormName()
	{
		// ActiveForm posts LoginForm[username], not username.
		$filter = $this->filter(['limit' => 1000, 'identityParams' => ['username'], 'identityLimit' => 1]);
		$action = $this->action();
		Yii::$app->request->setBodyParams(['LoginForm' => ['username' => 'victim@example.test']]);

		$this->assertTrue($filter->beforeAction($action));

		$this->expectException(TooManyRequestsHttpException::class);
		$filter->beforeAction($action);
	}

	public function testADifferentAccountHasItsOwnBudget()
	{
		$filter = $this->filter(['limit' => 1000, 'identityParams' => ['username'], 'identityLimit' => 1]);
		$action = $this->action();

		Yii::$app->request->setBodyParams(['username' => 'one@example.test']);
		$filter->beforeAction($action);

		Yii::$app->request->setBodyParams(['username' => 'two@example.test']);
		$this->assertTrue(
			$filter->beforeAction($action),
			'throttling one account must not lock out another'
		);
	}

	public function testAMissingCacheDoesNotLockTheSiteOut()
	{
		$filter = $this->filter();
		$cache = Yii::$app->cache;
		Yii::$app->set('cache', null);

		try {
			$this->assertTrue($filter->beforeAction($this->action()));
		} finally {
			Yii::$app->set('cache', $cache);
		}
	}
}
