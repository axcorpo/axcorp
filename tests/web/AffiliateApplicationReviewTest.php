<?php

namespace tests\web;

use common\models\Affiliate;
use common\models\User;
use tests\support\FakeSettings;
use tests\WebTestCase;
use Yii;
use yii\web\ForbiddenHttpException;

/**
 * The admin review of affiliate applications: approve / reject are POST-only and need
 * `updateAffiliate`, a rejection needs a reason, and the affiliates list keeps the
 * applications (pending, rejected) on their own tab.
 */
class AffiliateApplicationReviewTest extends WebTestCase
{
	/**
	 * @var Affiliate
	 */
	private $application;

	/**
	 * @var Affiliate
	 */
	private $active;

	protected function setUp(): void
	{
		parent::setUp();
		Yii::$app->setModule('affiliate-manager', ['class' => \backend\modules\affiliate\Module::class]);
		// Flashes without a PHP session (the suite runs in the CLI, after output started).
		Yii::$app->set('session', new class extends \yii\web\Session {
			public function open()
			{
				if (!isset($_SESSION)) {
					$_SESSION = [];
				}
			}

			public function getIsActive()
			{
				return true;
			}
		});
		Yii::$app->set('mailer', [
			'class' => 'yii\symfonymailer\Mailer',
			'useFileTransport' => true,
			'fileTransportPath' => sys_get_temp_dir() . '/affiliate-review-test-mail',
			'messageConfig' => ['from' => 'noreply@example.test'],
		]);
		Yii::$app->set('settings', new FakeSettings());
		// The review is logged like any update; the test schema has no event_log table.
		Yii::$app->set('eventLog', new class extends \backend\modules\eventlog\components\EventLog {
			public function init()
			{
			}

			public function beginRecord($owner = null, $relations = null)
			{
				return $this;
			}

			public function endRecord($forceSave = false)
			{
				return true;
			}
		});

		$adminId = $this->insertRow('user', ['email' => 'admin@example.test', 'status' => 1, 'deleted' => 0]);
		Yii::$app->user->setIdentity(User::findOne(['id' => $adminId]));

		$this->application = $this->affiliate('PEND0001', Affiliate::STATUS_PENDING, 'applicant@example.test');
		$this->active = $this->affiliate('ACTV0001', Affiliate::STATUS_ACTIVE, 'active@example.test');
	}

	protected function tearDown(): void
	{
		$_SESSION = [];
		parent::tearDown();
	}

	/**
	 * @param string $code
	 * @param int $status
	 * @param string $email
	 * @return Affiliate
	 */
	private function affiliate($code, $status, $email)
	{
		$id = $this->insertRow('affiliate', [
			'user_id' => $this->insertRow('user', ['email' => $email, 'first_name' => 'Ana', 'status' => 1, 'deleted' => 0]),
			'code' => $code,
			'commission_percentage' => 10,
			'application_message' => $status === Affiliate::STATUS_PENDING ? 'My blog' : null,
			'status' => $status,
			'deleted' => 0,
		]);
		return Affiliate::findOne(['id' => $id]);
	}

	/**
	 * @return int
	 */
	private function statusOf(Affiliate $affiliate)
	{
		return (int) Affiliate::findOne(['id' => $affiliate->getAttribute('id')])->status;
	}

	public function testAnAdminWhoMayUpdateAffiliatesApproves()
	{
		Yii::$app->user->grantedPermissions = ['viewAffiliate', 'updateAffiliate'];

		$this->runControllerAction('affiliate-manager/affiliate/approve', ['id' => $this->application->uuid], ['confirm' => 1]);

		$this->assertSame(Affiliate::STATUS_ACTIVE, $this->statusOf($this->application));
	}

	public function testViewingIsNotEnoughToDecide()
	{
		Yii::$app->user->grantedPermissions = ['viewAffiliate'];

		try {
			$this->runControllerAction('affiliate-manager/affiliate/approve', ['id' => $this->application->uuid], ['confirm' => 1]);
			$this->fail('viewAffiliate alone must not approve');
		} catch (ForbiddenHttpException $e) {
			$this->assertSame(Affiliate::STATUS_PENDING, $this->statusOf($this->application));
		}
	}

	public function testDecisionsArePostOnly()
	{
		Yii::$app->user->grantedPermissions = ['viewAffiliate', 'updateAffiliate'];

		$this->expectException(ForbiddenHttpException::class);
		$this->runControllerAction('affiliate-manager/affiliate/approve', ['id' => $this->application->uuid]);
	}

	public function testARejectionNeedsAReason()
	{
		Yii::$app->user->grantedPermissions = ['viewAffiliate', 'updateAffiliate'];

		$this->runControllerAction('affiliate-manager/affiliate/reject', ['id' => $this->application->uuid], ['AffiliateRejectForm' => ['reason' => '']]);
		$this->assertSame(Affiliate::STATUS_PENDING, $this->statusOf($this->application));

		$this->runControllerAction('affiliate-manager/affiliate/reject', ['id' => $this->application->uuid], ['AffiliateRejectForm' => ['reason' => 'Not a company']]);
		$this->assertSame(Affiliate::STATUS_REJECTED, $this->statusOf($this->application));
		$this->assertSame('Not a company', Affiliate::findOne(['id' => $this->application->getAttribute('id')])->rejection_reason);
	}

	public function testAnActiveAffiliateCannotBeRejectedAsAnApplication()
	{
		Yii::$app->user->grantedPermissions = ['viewAffiliate', 'updateAffiliate'];

		$this->runControllerAction('affiliate-manager/affiliate/reject', ['id' => $this->active->uuid], ['AffiliateRejectForm' => ['reason' => 'No']]);

		$this->assertSame(Affiliate::STATUS_ACTIVE, $this->statusOf($this->active));
	}

	public function testTheApplicationsHaveTheirOwnList()
	{
		Yii::$app->user->grantedPermissions = ['viewAffiliate'];

		$codes = function ($applications) {
			$payload = $this->jsonPayload($this->runControllerAction('affiliate-manager/affiliate/dt-affiliates', [], [
				'draw' => 1,
				'applications' => $applications,
				'external_filters' => [],
			]));
			return array_column($payload['data'] ?? [], 'code');
		};

		$this->assertSame(['ACTV0001'], $codes(null));
		$this->assertSame(['PEND0001'], $codes(Affiliate::YES));
	}
}
