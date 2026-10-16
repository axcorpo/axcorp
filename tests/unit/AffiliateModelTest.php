<?php

namespace tests\unit;

use common\models\Affiliate;
use common\models\Subscriber;
use common\models\User;
use tests\DatabaseTestCase;

/**
 * The affiliate row: defaults of a new affiliate, the referral code shape the tracker
 * accepts, one affiliate per user, the User accessors the account area gates on, and the
 * masked subscriber data an affiliate sees.
 */
class AffiliateModelTest extends DatabaseTestCase
{
	/**
	 * @return string raw user id
	 */
	private function user()
	{
		return $this->insertRow('user', [
			'username' => 'aff-' . bin2hex(random_bytes(4)),
			'email' => bin2hex(random_bytes(4)) . '@example.com',
			'status' => 1,
			'deleted' => 0,
		]);
	}

	public function testANewAffiliateGetsAGeneratedCodeAndTheDefaultPercentage()
	{
		$affiliate = new Affiliate(['user_id' => $this->user()]);

		$this->assertTrue($affiliate->save(), json_encode($affiliate->getErrors()));
		$this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $affiliate->code);
		$this->assertEquals(10, (float) $affiliate->commission_percentage);
		$this->assertEquals(Affiliate::STATUS_ACTIVE, (int) $affiliate->status);
		$this->assertNull($affiliate->commission_months);
	}

	public function testTheCodeIsNormalizedAndMustHaveTheTenantShape()
	{
		$affiliate = new Affiliate(['user_id' => $this->user(), 'code' => ' promo2026 ']);
		$this->assertTrue($affiliate->validate(['code']));
		$this->assertEquals('PROMO2026', $affiliate->code);

		foreach (['ab', 'with-dash', 'spaces in', str_repeat('A', 33)] as $bad) {
			$affiliate->code = $bad;
			$this->assertFalse($affiliate->validate(['code']), $bad);
		}
	}

	public function testCodesAndUsersAreUnique()
	{
		$user = $this->user();
		$first = new Affiliate(['user_id' => $user, 'code' => 'UNIQ01']);
		$this->assertTrue($first->save());

		$sameCode = new Affiliate(['user_id' => $this->user(), 'code' => 'uniq01']);
		$this->assertFalse($sameCode->validate());
		$this->assertTrue($sameCode->hasErrors('code'));

		$sameUser = new Affiliate(['user_id' => $user]);
		$this->assertFalse($sameUser->validate());
		$this->assertTrue($sameUser->hasErrors('user_id'));
	}

	public function testUserIsAffiliate()
	{
		$userId = $this->user();
		$user = User::findOne(['id' => $userId]);
		$this->assertFalse($user->isAffiliate);

		$affiliate = new Affiliate(['user_id' => $userId]);
		$affiliate->save();
		$user = User::findOne(['id' => $userId]);
		$this->assertTrue($user->isAffiliate);
		$this->assertEquals($affiliate->code, $user->affiliate->code);

		$affiliate->updateAttributes(['status' => Affiliate::STATUS_INACTIVE]);
		$user = User::findOne(['id' => $userId]);
		$this->assertFalse($user->getIsAffiliate());
		$this->assertTrue($user->getIsAffiliate(false));
	}

	public function testFindActiveByCodeIgnoresInactiveAndDeleted()
	{
		$affiliate = new Affiliate(['user_id' => $this->user(), 'code' => 'FIND01']);
		$affiliate->save();

		$this->assertNotNull(Affiliate::findActiveByCode('find01'));
		$affiliate->updateAttributes(['deleted' => 1]);
		$this->assertNull(Affiliate::findActiveByCode('FIND01'));
	}

	public function testSubscriberMasking()
	{
		// The personal data of a master subscriber lives on its user.
		$subscriber = new Subscriber();
		$subscriber->populateRelation('user', new User(['first_name' => 'Ion', 'last_name' => 'popescu', 'email' => 'ion.popescu@gmail.com']));

		$this->assertEquals('Ion P.', $subscriber->maskedName);
		$this->assertEquals('i***@gmail.com', $subscriber->maskedEmail);

		$subscriber->populateRelation('user', new User(['first_name' => 'Ana', 'last_name' => '', 'email' => null]));
		$this->assertEquals('Ana', $subscriber->maskedName);
		$this->assertEquals('', $subscriber->maskedEmail);

		$subscriber->populateRelation('user', null);
		$this->assertEquals('', $subscriber->maskedName);
		$this->assertEquals('', $subscriber->maskedEmail);
	}
}
