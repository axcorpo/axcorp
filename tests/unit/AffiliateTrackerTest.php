<?php

namespace tests\unit;

use common\components\AffiliateTracker;
use common\models\Affiliate;
use common\models\Subscriber;
use common\models\User;
use tests\WebTestCase;
use Yii;

/**
 * AffiliateTracker::assign(): a subscriber being created at signup is attributed to the
 * affiliate whose code the visitor brought (session first, then the `aff` cookie) — only
 * an active affiliate, never for their own account, and only once.
 */
class AffiliateTrackerTest extends WebTestCase
{
	/**
	 * @var string raw user id of the active affiliate
	 */
	private $affiliateUserId;

	/**
	 * @var Affiliate
	 */
	private $affiliate;

	protected function setUp(): void
	{
		parent::setUp();
		// An in-memory session: the real one would session_start() in the CLI run.
		Yii::$app->set('session', new class extends \yii\web\Session {
			public $data = [];

			public function get($key, $defaultValue = null)
			{
				return $this->data[$key] ?? $defaultValue;
			}

			public function set($key, $value)
			{
				$this->data[$key] = $value;
			}
		});
		unset($_COOKIE[AffiliateTracker::COOKIE]);

		$this->affiliateUserId = $this->user();
		$this->affiliate = $this->insertAffiliate($this->affiliateUserId, 'TRACK001', Affiliate::STATUS_ACTIVE);
	}

	protected function tearDown(): void
	{
		unset($_COOKIE[AffiliateTracker::COOKIE]);
		parent::tearDown();
	}

	/**
	 * @return string raw user id
	 */
	private function user()
	{
		return $this->insertRow('user', [
			'email' => 'u' . bin2hex(random_bytes(4)) . '@example.test',
			'status' => User::STATUS_ACTIVE,
			'deleted' => User::NO,
		]);
	}

	/**
	 * @param string $userId raw
	 * @param string $code
	 * @param int $status
	 * @return Affiliate
	 */
	private function insertAffiliate($userId, $code, $status)
	{
		$id = $this->insertRow('affiliate', [
			'user_id' => $userId,
			'code' => $code,
			'commission_percentage' => 10,
			'status' => $status,
			'deleted' => Affiliate::NO,
		]);
		return Affiliate::findOne(['id' => $id]);
	}

	/**
	 * A subscriber as the signup builds it, not saved yet.
	 *
	 * @param string|null $userId raw; null = a new user
	 * @return Subscriber
	 */
	private function newSubscriber($userId = null)
	{
		return new Subscriber([
			'user_id' => $userId ?: $this->user(),
			'code' => Subscriber::generateUniqueCode(),
			'status' => Subscriber::STATUS_ACTIVE,
		]);
	}

	/**
	 * @param string $code as the visitor brought it
	 */
	private function visitWith($code)
	{
		Yii::$app->session->set(AffiliateTracker::SESSION_KEY, $code);
	}

	public function testAnActiveAffiliatesCodeAttributesTheNewSubscriber()
	{
		$this->visitWith('track001');
		$subscriber = $this->newSubscriber();

		$this->assertTrue(AffiliateTracker::assign($subscriber));
		$this->assertSame($this->affiliate->getAttribute('id'), $subscriber->affiliate_id);
		$this->assertTrue($subscriber->save(), json_encode($subscriber->getErrors()));
		$this->assertSame($this->affiliate->getAttribute('id'), Subscriber::findOne(['id' => $subscriber->getAttribute('id')])->affiliate_id);
	}

	public function testTheCookieCarriesTheCodeOnALaterVisit()
	{
		$_COOKIE[AffiliateTracker::COOKIE] = 'TRACK001';
		$subscriber = $this->newSubscriber();

		$this->assertTrue(AffiliateTracker::assign($subscriber));
		$this->assertSame($this->affiliate->getAttribute('id'), $subscriber->affiliate_id);
	}

	public function testWithoutACodeNothingIsAssigned()
	{
		$subscriber = $this->newSubscriber();

		$this->assertFalse(AffiliateTracker::assign($subscriber));
		$this->assertNull($subscriber->affiliate_id);
	}

	public function testAnUnknownOrMalformedCodeIsNotAttributed()
	{
		foreach (['NOPE0001', 'x', 'with-dash'] as $code) {
			$this->visitWith($code);
			$subscriber = $this->newSubscriber();

			$this->assertFalse(AffiliateTracker::assign($subscriber), $code);
			$this->assertNull($subscriber->affiliate_id, $code);
		}
	}

	public function testOnlyAnActiveAffiliateAttributes()
	{
		$this->insertAffiliate($this->user(), 'PENDING1', Affiliate::STATUS_PENDING);
		$this->insertAffiliate($this->user(), 'REJECTD1', Affiliate::STATUS_REJECTED);
		$this->insertAffiliate($this->user(), 'INACTIV1', Affiliate::STATUS_INACTIVE);
		$deleted = $this->insertAffiliate($this->user(), 'DELETED1', Affiliate::STATUS_ACTIVE);
		$deleted->updateAttributes(['deleted' => Affiliate::YES]);

		foreach (['PENDING1', 'REJECTD1', 'INACTIV1', 'DELETED1'] as $code) {
			$this->visitWith($code);
			$subscriber = $this->newSubscriber();

			$this->assertFalse(AffiliateTracker::assign($subscriber), $code);
			$this->assertNull($subscriber->affiliate_id, $code);
		}
	}

	public function testAnAffiliateCannotReferThemselves()
	{
		$this->visitWith('TRACK001');
		$subscriber = $this->newSubscriber($this->affiliateUserId);

		$this->assertFalse(AffiliateTracker::assign($subscriber));
		$this->assertNull($subscriber->affiliate_id);
	}

	public function testAnExistingSubscriberIsNeverTouched()
	{
		$subscriber = $this->newSubscriber();
		$this->assertTrue($subscriber->save(), json_encode($subscriber->getErrors()));
		$subscriber = Subscriber::findOne(['id' => $subscriber->getAttribute('id')]);

		$this->visitWith('TRACK001');

		$this->assertFalse(AffiliateTracker::assign($subscriber));
		$this->assertNull($subscriber->affiliate_id);
	}

	public function testAnAlreadyAttributedSubscriberKeepsItsAffiliate()
	{
		$first = $this->insertAffiliate($this->user(), 'FIRST001', Affiliate::STATUS_ACTIVE);
		$subscriber = $this->newSubscriber();
		$subscriber->affiliate_id = $first->getAttribute('id');

		$this->visitWith('TRACK001');

		$this->assertFalse(AffiliateTracker::assign($subscriber));
		$this->assertSame($first->getAttribute('id'), $subscriber->affiliate_id);
	}
}
