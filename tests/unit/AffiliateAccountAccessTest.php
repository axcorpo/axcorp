<?php

namespace tests\unit;

use common\models\Affiliate;
use common\models\Subscriber;
use common\models\User;
use frontend\modules\account\Module;
use tests\DatabaseTestCase;

/**
 * Who may open which page of the account area, and which sidebar items they see.
 *
 * The account module used to admit subscribers only; affiliates now get in too, but only
 * to the shared pages (profile, company, help) and the affiliate pages. Subscriber-only
 * controllers dereference the subscriber row, so a pure affiliate is redirected to the
 * affiliate dashboard before reaching them. The sidebar menu follows the same rule.
 */
class AffiliateAccountAccessTest extends DatabaseTestCase
{
	/**
	 * @var Module
	 */
	private $module;

	protected function setUp(): void
	{
		parent::setUp();
		$this->module = new Module('account');
	}

	/**
	 * @param bool $subscriber
	 * @param int|null $affiliateStatus null for no affiliate row
	 * @return User
	 */
	private function user($subscriber, $affiliateStatus = null)
	{
		$userId = $this->insertRow('user', [
			'email' => 'u' . bin2hex(random_bytes(4)) . '@example.test',
			'status' => User::STATUS_ACTIVE,
			'deleted' => User::NO,
		]);
		if ($subscriber) {
			$this->insertRow('subscriber', [
				'user_id' => $userId,
				'status' => Subscriber::STATUS_ACTIVE,
				'deleted' => Subscriber::NO,
			]);
		}
		if ($affiliateStatus !== null) {
			$this->insertRow('affiliate', [
				'user_id' => $userId,
				'code' => 'ACC' . strtoupper(bin2hex(random_bytes(3))),
				'commission_percentage' => 10,
				'status' => $affiliateStatus,
				'deleted' => Affiliate::NO,
			]);
		}
		return User::findOne(['id' => $userId]);
	}

	public function testAPureAffiliateOpensTheAffiliateAndSharedPages()
	{
		$user = $this->user(false, Affiliate::STATUS_ACTIVE);

		foreach (['affiliate', 'affiliate-subscriber', 'affiliate-commission', 'affiliate-payout', 'profile', 'company', 'default'] as $controller) {
			$this->assertTrue($this->module->isControllerAllowed($controller, $user), $controller);
			$this->assertNull($this->module->getAccessRedirect($controller, $user), $controller);
		}
	}

	public function testSubscriberOnlyPagesRedirectAPureAffiliateToTheDashboard()
	{
		$user = $this->user(false, Affiliate::STATUS_ACTIVE);

		foreach (['invoice', 'subscription', 'workspace', 'payment', 'helpdesk', 'support-ticket'] as $controller) {
			$this->assertFalse($this->module->isControllerAllowed($controller, $user), $controller);
			$this->assertSame(['/account/affiliate/index'], $this->module->getAccessRedirect($controller, $user), $controller);
		}
	}

	public function testASubscriberDoesNotOpenTheAffiliatePages()
	{
		$user = $this->user(true);

		$this->assertTrue($this->module->isControllerAllowed('invoice', $user));
		$this->assertTrue($this->module->isControllerAllowed('profile', $user));
		$this->assertFalse($this->module->isControllerAllowed('affiliate-payout', $user));
		$this->assertSame(['/account/profile/index'], $this->module->getAccessRedirect('affiliate', $user));
	}

	public function testAUserWhoIsBothSeesBothSets()
	{
		$user = $this->user(true, Affiliate::STATUS_ACTIVE);

		foreach (['invoice', 'workspace', 'affiliate', 'affiliate-payout', 'company'] as $controller) {
			$this->assertNull($this->module->getAccessRedirect($controller, $user), $controller);
		}
	}

	public function testAnInactiveAffiliateIsNotAnAffiliate()
	{
		$user = $this->user(false, Affiliate::STATUS_INACTIVE);

		$this->assertFalse($this->module->isControllerAllowed('affiliate', $user));
		$this->assertFalse($this->module->isControllerAllowed('profile', $user));
	}

	/**
	 * A nested item as Menu::getNestedItems() returns it.
	 *
	 * @param string|null $module
	 * @param string|null $controller null for an item without a page
	 * @param array $children
	 * @return array
	 */
	private function menuItem($module, $controller, array $children = [])
	{
		// Stand-ins for MenuItem/Page: the filter reads page_id and page->module/controller
		// only, and master_test has no menu tables.
		$model = (object) ['page_id' => null, 'page' => null];
		if ($controller !== null) {
			$model->page_id = $this->uuidBytes();
			$model->page = (object) ['module' => $module, 'controller' => $controller, 'action' => 'index'];
		}
		$item = ['id' => $controller, 'content' => (string) $controller, 'model' => $model];
		if ($children) {
			$item['items'] = $children;
		}
		return $item;
	}

	/**
	 * @return array
	 */
	private function sidebar()
	{
		return [
			$this->menuItem('account', 'profile'),
			$this->menuItem('account', 'company'),
			$this->menuItem('account', 'subscription'),
			$this->menuItem('account', 'invoice', [
				$this->menuItem('account', 'affiliate-payout'),
			]),
			$this->menuItem('account', 'affiliate'),
			$this->menuItem('account', 'affiliate-commission'),
			$this->menuItem(null, null),
			$this->menuItem('article', 'default'),
		];
	}

	/**
	 * @param array $items
	 * @return string[]
	 */
	private function ids(array $items)
	{
		$ids = [];
		foreach ($items as $item) {
			$ids[] = $item['id'];
			if (!empty($item['items'])) {
				$ids = array_merge($ids, $this->ids($item['items']));
			}
		}
		return $ids;
	}

	public function testTheSidebarShowsAnAffiliateOnlyTheirPages()
	{
		$items = $this->module->filterMenuItems($this->sidebar(), $this->user(false, Affiliate::STATUS_ACTIVE));

		// The pageless item and the other module's page are not the module's to hide.
		$this->assertSame(['profile', 'company', 'affiliate', 'affiliate-commission', null, 'default'], $this->ids($items));
	}

	public function testTheSidebarOfASubscriberIsUnchanged()
	{
		$items = $this->module->filterMenuItems($this->sidebar(), $this->user(true));

		$this->assertSame(['profile', 'company', 'subscription', 'invoice', null, 'default'], $this->ids($items));
	}

	public function testTheSidebarOfAUserWhoIsBothHasEverything()
	{
		$items = $this->module->filterMenuItems($this->sidebar(), $this->user(true, Affiliate::STATUS_ACTIVE));

		$this->assertSame($this->ids($this->sidebar()), $this->ids($items));
	}
}
