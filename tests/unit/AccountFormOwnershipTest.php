<?php

namespace tests\unit;

use common\helpers\UuidHelper;
use common\models\Subscriber;
use common\models\Subscription;
use common\models\User;
use frontend\modules\account\models\CompanyForm;
use frontend\modules\account\models\SubscriptionForm;
use frontend\modules\account\models\SupportTicketForm;
use frontend\modules\account\models\WorkspaceForm;
use tests\WebTestCase;
use Yii;

/**
 * Mass assignment and ownership on the customer account forms.
 *
 * The forms returned Model::scenarios(), which made every attribute with a parent rule
 * safe — user_id, status, deleted, created_by — so one extra posted field could hand a
 * company or ticket to another account or flip its status. Their `exist` rules also
 * accepted any row in the table, so a customer could bill another customer's company or
 * attach someone else's subscription.
 */
class AccountFormOwnershipTest extends WebTestCase
{
	/**
	 * @var string raw bytes
	 */
	private $ownerId;

	/**
	 * @var string raw bytes
	 */
	private $outsiderId;

	protected function setUp(): void
	{
		parent::setUp();

		$this->ownerId = $this->insertUser('owner@example.test');
		$this->outsiderId = $this->insertUser('outsider@example.test');
		Yii::$app->user->setIdentity(User::findOne(['id' => $this->ownerId]));
	}

	/**
	 * @param string $email
	 * @return string raw bytes
	 */
	private function insertUser($email)
	{
		return $this->insertRow('user', [
			'email' => $email,
			'first_name' => 'Test',
			'last_name' => 'User',
			'status' => User::STATUS_ACTIVE,
			'deleted' => User::NO,
		]);
	}

	/**
	 * @param string $userId raw bytes
	 * @return string raw bytes the subscription id
	 */
	private function insertSubscriptionFor($userId)
	{
		$subscriberId = $this->insertRow('subscriber', [
			'user_id' => $userId,
			'status' => Subscriber::STATUS_ACTIVE,
			'deleted' => Subscriber::NO,
		]);

		return $this->insertRow('subscription', [
			'subscriber_id' => $subscriberId,
			'status' => Subscription::STATUS_ACTIVE,
			'deleted' => Subscription::NO,
		]);
	}

	public function testCompanyOwnerAndStatusCannotBePosted()
	{
		$form = new CompanyForm();
		$form->user_id = UuidHelper::fromBytes($this->ownerId);
		$form->load(['CompanyForm' => [
			'name' => 'Acme',
			'user_id' => UuidHelper::fromBytes($this->outsiderId),
			'status' => 0,
			'deleted' => 1,
			'created_by' => UuidHelper::fromBytes($this->outsiderId),
		]]);

		$this->assertSame('Acme', $form->name);
		$this->assertSame(UuidHelper::fromBytes($this->ownerId), $form->user_id);
		$this->assertSame(CompanyForm::STATUS_ACTIVE, $form->status);
		$this->assertNotEquals(1, $form->deleted);
		$this->assertNull($form->created_by);
	}

	public function testANewTicketTakesOnlyItsFormFields()
	{
		$form = new SupportTicketForm();
		$form->load(['SupportTicketForm' => [
			'subject' => 'Help',
			'user_id' => UuidHelper::fromBytes($this->outsiderId),
			'created_by' => UuidHelper::fromBytes($this->outsiderId),
			'deleted' => 1,
		]]);

		$this->assertSame('Help', $form->subject);
		$this->assertNull($form->user_id);
		$this->assertNull($form->created_by);
		$this->assertNotEquals(1, $form->deleted);
	}

	public function testWorkspaceStatusTypeAndDeletedCannotBePosted()
	{
		$form = new WorkspaceForm();
		$form->load(['WorkspaceForm' => [
			'url' => 'tenant',
			'status' => 0,
			'type' => 99,
			'deleted' => 1,
		]]);

		$this->assertSame('tenant', $form->url);
		$this->assertSame(WorkspaceForm::STATUS_ACTIVE, $form->status);
		$this->assertSame(WorkspaceForm::TYPE_GENERAL, $form->type);
		$this->assertNotEquals(1, $form->deleted);
	}

	public function testATicketCanOnlyReferenceOwnSubscriptions()
	{
		$own = $this->insertSubscriptionFor($this->ownerId);
		$foreign = $this->insertSubscriptionFor($this->outsiderId);

		$form = new SupportTicketForm();
		$form->subscription_id = UuidHelper::fromBytes($own);
		$this->assertTrue($form->validate(['subscription_id']), print_r($form->errors, true));

		$form = new SupportTicketForm();
		$form->subscription_id = UuidHelper::fromBytes($foreign);
		$this->assertFalse($form->validate(['subscription_id']), 'another customer\'s subscription was accepted');
	}

	public function testAWorkspaceCanOnlyUseOwnSubscriptions()
	{
		$foreign = $this->insertSubscriptionFor($this->outsiderId);

		$form = new WorkspaceForm();
		$form->subscription_id = UuidHelper::fromBytes($foreign);
		$this->assertFalse($form->validate(['subscription_id']), 'another customer\'s subscription was accepted');
	}

	public function testOnlyOwnCompaniesCanBeBilled()
	{
		$own = $this->insertRow('company', ['user_id' => $this->ownerId, 'name' => 'Mine', 'status' => 1, 'deleted' => 0]);
		$foreign = $this->insertRow('company', ['user_id' => $this->outsiderId, 'name' => 'Theirs', 'status' => 1, 'deleted' => 0]);

		$form = new SubscriptionForm();
		$form->company_id = UuidHelper::fromBytes($own);
		$this->assertTrue($form->validate(['company_id']), print_r($form->errors, true));

		$form = new SubscriptionForm();
		$form->company_id = UuidHelper::fromBytes($foreign);
		$this->assertFalse($form->validate(['company_id']), 'another customer\'s company was accepted');
	}
}
