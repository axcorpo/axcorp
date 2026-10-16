<?php

namespace tests\unit;

use common\helpers\UuidHelper;
use common\models\Affiliate;
use common\models\AffiliateCommission;
use common\models\AffiliatePayout;
use common\models\Invoice;
use common\models\Subscriber;
use common\models\Subscription;
use common\models\User;
use frontend\modules\account\controllers\AffiliatePayoutController;
use frontend\modules\account\models\AffiliateDashboard;
use frontend\modules\account\models\AffiliateInvoiceForm;
use frontend\modules\account\models\AffiliateSubscriberSearch;
use frontend\modules\account\models\ProfileForm;
use frontend\modules\account\Module;
use tests\WebTestCase;
use Yii;
use yii\helpers\FileHelper;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

/**
 * The affiliate pages of the account area: payouts and their files reachable only by
 * their affiliate, the invoice upload, the masked subscriber data and the dashboard.
 *
 * Controller methods are called directly rather than through runControllerAction(): the
 * module's beforeAction() works with the session (flashes, reminders), which does not
 * belong in a CLI run; its access rules are covered by AffiliateAccountAccessTest.
 */
class AffiliateAccountPagesTest extends WebTestCase
{
	/**
	 * @var Affiliate
	 */
	private $affiliate;

	/**
	 * @var Affiliate
	 */
	private $other;

	/**
	 * @var string
	 */
	private $storage;

	/**
	 * @var mixed
	 */
	private $previousPath;

	/**
	 * @var string[]
	 */
	private $tempFiles = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->previousPath = Yii::$app->params['privateUploads.path'] ?? null;
		$this->storage = sys_get_temp_dir() . '/affiliate-account-test-' . bin2hex(random_bytes(4));
		Yii::$app->params['privateUploads.path'] = $this->storage;

		$this->affiliate = $this->insertAffiliate('MINE0001');
		$this->other = $this->insertAffiliate('THEM0001');
	}

	protected function tearDown(): void
	{
		// The rollback must run whatever happens here, or the open transaction keeps its
		// row locks and the next test waits on them.
		try {
			Yii::$app->params['privateUploads.path'] = $this->previousPath;
			foreach ($this->tempFiles as $path) {
				@unlink($path);
			}
			FileHelper::removeDirectory($this->storage);
		} finally {
			parent::tearDown();
		}
	}

	/**
	 * @param string $code
	 * @return Affiliate
	 */
	private function insertAffiliate($code)
	{
		$userId = $this->insertRow('user', [
			'email' => strtolower($code) . '@example.test',
			'first_name' => 'Aff',
			'last_name' => $code,
			'status' => User::STATUS_ACTIVE,
			'deleted' => User::NO,
		]);
		$id = $this->insertRow('affiliate', [
			'user_id' => $userId,
			'code' => $code,
			'commission_percentage' => 10,
			'status' => Affiliate::STATUS_ACTIVE,
			'deleted' => Affiliate::NO,
		]);
		return Affiliate::findOne(['id' => $id]);
	}

	/**
	 * @param Affiliate $affiliate
	 * @param int $status
	 * @return AffiliatePayout
	 */
	private function insertPayout(Affiliate $affiliate, $status = AffiliatePayout::STATUS_OPEN)
	{
		$id = $this->insertRow('affiliate_payout', [
			'affiliate_id' => $affiliate->getAttribute('id'),
			'amount' => '150.00',
			'currency' => 'RON',
			'status' => $status,
			'deleted' => AffiliatePayout::NO,
			'created_at' => date('Y-m-d H:i:s'),
		]);
		return AffiliatePayout::findOne(['id' => $id]);
	}

	/**
	 * @param string $content
	 * @param string $name
	 * @return UploadedFile
	 */
	private function upload($content, $name)
	{
		$path = tempnam(sys_get_temp_dir(), 'affinv');
		file_put_contents($path, $content);
		$this->tempFiles[] = $path;
		return new UploadedFile([
			'name' => $name,
			'tempName' => $path,
			'type' => 'application/octet-stream',
			'size' => filesize($path),
			'error' => UPLOAD_ERR_OK,
		]);
	}

	/**
	 * @return UploadedFile
	 */
	private function pdf()
	{
		return $this->upload("%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n", 'factura.pdf');
	}

	/**
	 * A payout controller acting as the given affiliate's user.
	 *
	 * @param Affiliate $affiliate
	 * @return AffiliatePayoutController
	 */
	private function controllerAs(Affiliate $affiliate)
	{
		Yii::$app->user->setIdentity(User::findOne(['id' => $affiliate->user_id]));
		return new AffiliatePayoutController('affiliate-payout', new Module('account', Yii::$app));
	}

	/**
	 * @param AffiliatePayoutController $controller
	 * @param string $id
	 * @return AffiliatePayout
	 */
	private function find(AffiliatePayoutController $controller, $id)
	{
		$method = new \ReflectionMethod($controller, 'findModel');
		$method->setAccessible(true);
		return $method->invoke($controller, $id);
	}

	/**
	 * @param AffiliatePayout $payout
	 * @param array $values
	 * @param UploadedFile|null $file
	 * @return AffiliateInvoiceForm
	 */
	private function invoiceForm(AffiliatePayout $payout, array $values, $file)
	{
		$form = new AffiliateInvoiceForm($payout, ['deleteTempFile' => false]);
		$form->setAttributes($values);
		$form->invoiceFile = $file;
		return $form;
	}

	// ------------------------------------------------------------------------- access

	public function testAnAffiliateFindsTheirOwnPayout()
	{
		$payout = $this->insertPayout($this->affiliate);

		$found = $this->find($this->controllerAs($this->affiliate), $payout->uuid);

		$this->assertSame($payout->getAttribute('id'), $found->getAttribute('id'));
	}

	public function testAnotherAffiliatesPayoutIsNotFound()
	{
		$payout = $this->insertPayout($this->other);

		$this->expectException(NotFoundHttpException::class);
		$this->find($this->controllerAs($this->affiliate), $payout->uuid);
	}

	public function testAMalformedPayoutIdIsNotFound()
	{
		$this->expectException(NotFoundHttpException::class);
		$this->find($this->controllerAs($this->affiliate), "' OR 1=1 --");
	}

	public function testAUserWithoutAnAffiliateRowIsForbidden()
	{
		$userId = $this->insertRow('user', ['email' => 'plain@example.test', 'status' => User::STATUS_ACTIVE, 'deleted' => User::NO]);
		Yii::$app->user->setIdentity(User::findOne(['id' => $userId]));
		$controller = new AffiliatePayoutController('affiliate-payout', new Module('account', Yii::$app));

		$this->expectException(ForbiddenHttpException::class);
		$controller->getAffiliate();
	}

	public function testTheInvoiceDownloadsForItsAffiliate()
	{
		$payout = $this->insertPayout($this->affiliate);
		$this->assertTrue($this->invoiceForm($payout, ['invoice_number' => 'AF-1', 'invoice_date' => '2026-09-30'], $this->pdf())->save());

		$response = $this->controllerAs($this->affiliate)->actionDownload($payout->uuid, $payout->invoice_file);
		// sendFile() keeps the file open for streaming; Windows will not delete it otherwise.
		fclose($response->stream[0]);

		$this->assertStringContainsString('attachment', $response->getHeaders()->get('Content-Disposition'));
		$this->assertStringContainsString('af-1.pdf', $response->getHeaders()->get('Content-Disposition'));
	}

	public function testAnotherAffiliatesFileIsNotFound()
	{
		$payout = $this->insertPayout($this->other);
		$this->assertTrue($this->invoiceForm($payout, ['invoice_number' => 'X-1', 'invoice_date' => '2026-09-30'], $this->pdf())->save());

		$this->expectException(NotFoundHttpException::class);
		$this->controllerAs($this->affiliate)->actionDownload($payout->uuid, $payout->invoice_file);
	}

	public function testOnlyTheFilesRecordedOnThePayoutDownload()
	{
		$mine = $this->insertPayout($this->affiliate);
		$theirs = $this->insertPayout($this->other);
		$this->assertTrue($this->invoiceForm($theirs, ['invoice_number' => 'X-2', 'invoice_date' => '2026-09-30'], $this->pdf())->save());

		// Their stored name, asked for through my payout: not one of my payout's files.
		$this->expectException(NotFoundHttpException::class);
		$this->controllerAs($this->affiliate)->actionDownload($mine->uuid, $theirs->invoice_file);
	}

	// ----------------------------------------------------------------- invoice upload

	public function testAValidInvoiceIsAttachedAndPutUpForReview()
	{
		$payout = $this->insertPayout($this->affiliate);
		$form = $this->invoiceForm($payout, ['invoice_number' => ' AF-7 ', 'invoice_date' => '2026-09-30'], $this->pdf());

		$this->assertTrue($form->save(), json_encode($form->getErrors()));
		$payout = AffiliatePayout::findOne(['id' => $payout->getAttribute('id')]);
		$this->assertSame(AffiliatePayout::STATUS_INVOICE_RECEIVED, (int) $payout->status);
		$this->assertSame(AffiliatePayout::INVOICE_STATUS_PENDING, (int) $payout->invoice_status);
		$this->assertSame('AF-7', $payout->invoice_number);
		$this->assertNotNull($payout->getFilePath($payout->invoice_file));
	}

	public function testTheNumberDateAndFileAreRequired()
	{
		$form = $this->invoiceForm($this->insertPayout($this->affiliate), [], null);

		$this->assertFalse($form->save());
		$this->assertTrue($form->hasErrors('invoice_number'));
		$this->assertTrue($form->hasErrors('invoice_date'));
		$this->assertTrue($form->hasErrors('invoiceFile'));
	}

	public function testTheDateIsReadInTheDatetimeFormatOfTheSettings()
	{
		// What the DateTimePicker sends with the dev settings.
		Yii::$app->settings->set('datetimeFormat', 'dd.MM.yyyy HH:mm:ss');
		Yii::$app->settings->set('dateFormat', 'dd.MM.yyyy');
		$payout = $this->insertPayout($this->affiliate);
		$form = $this->invoiceForm($payout, ['invoice_number' => 'AF-1', 'invoice_date' => '30.09.2026 14:30:00'], $this->pdf());

		$this->assertTrue($form->save(), json_encode($form->getErrors()));
		$this->assertSame('2026-09-30 14:30:00', $form->invoice_date);
		$payout = AffiliatePayout::findOne(['id' => $payout->getAttribute('id')]);
		$this->assertSame('2026-09-30 14:30:00', $payout->invoice_date);
	}

	public function testAMalformedDateIsRejected()
	{
		Yii::$app->settings->set('datetimeFormat', 'dd.MM.yyyy HH:mm:ss');
		Yii::$app->settings->set('dateFormat', 'dd.MM.yyyy');
		foreach (['31.09.2026 10:00:00', '30.09.2026 25:00:00', 'next monday', 'x'] as $date) {
			$form = $this->invoiceForm($this->insertPayout($this->affiliate), ['invoice_number' => 'AF-1', 'invoice_date' => $date], $this->pdf());

			$this->assertFalse($form->save(), $date);
			$this->assertTrue($form->hasErrors('invoice_date'), $date);
		}
	}

	public function testAnInvoiceCannotBeDatedInTheFuture()
	{
		$form = $this->invoiceForm($this->insertPayout($this->affiliate), ['invoice_number' => 'AF-1', 'invoice_date' => date('Y-m-d H:i:s', strtotime('+2 days'))], $this->pdf());

		$this->assertFalse($form->save());
		$this->assertTrue($form->hasErrors('invoice_date'));
	}

	public function testAFileThatIsNotADocumentIsRejected()
	{
		$payout = $this->insertPayout($this->affiliate);
		$form = $this->invoiceForm($payout, ['invoice_number' => 'AF-1', 'invoice_date' => '2026-09-30'], $this->upload("<?php echo 'x';", 'factura.pdf'));

		$this->assertFalse($form->save());
		$this->assertTrue($form->hasErrors('invoiceFile'));
		$this->assertNull(AffiliatePayout::findOne(['id' => $payout->getAttribute('id')])->invoice_file);
	}

	public function testAPayoutNotAwaitingAnInvoiceRefusesTheUpload()
	{
		foreach ([AffiliatePayout::STATUS_INVOICE_RECEIVED, AffiliatePayout::STATUS_PAID, AffiliatePayout::STATUS_CANCELLED] as $status) {
			$form = $this->invoiceForm($this->insertPayout($this->affiliate, $status), ['invoice_number' => 'AF-1', 'invoice_date' => '2026-09-30'], $this->pdf());

			$this->assertFalse($form->getCanUpload(), (string) $status);
			$this->assertFalse($form->save(), (string) $status);
			$this->assertTrue($form->hasErrors('invoiceFile'), (string) $status);
		}
	}

	public function testTheFormLoadsOnlyItsOwnFields()
	{
		$payout = $this->insertPayout($this->affiliate);
		$form = new AffiliateInvoiceForm($payout);

		$this->assertEqualsCanonicalizing(['invoice_number', 'invoice_date', 'invoiceFile'], $form->safeAttributes());
	}

	// -------------------------------------------------------------------- masked data

	/**
	 * A master subscriber referred by `$affiliate`, with its user.
	 *
	 * @param Affiliate $affiliate
	 * @param array $user user attributes
	 * @param array $subscriber subscriber attributes
	 * @return string raw subscriber id
	 */
	private function insertSubscriber(Affiliate $affiliate, array $user = [], array $subscriber = [])
	{
		$userId = $this->insertRow('user', $user + [
			'first_name' => 'Ion',
			'last_name' => 'Popescu',
			'email' => 'ion.popescu.' . bin2hex(random_bytes(3)) . '@gmail.com',
			'phone' => '+40722' . random_int(100000, 999999),
			'status' => User::STATUS_ACTIVE,
			'deleted' => User::NO,
		]);
		return $this->insertRow('subscriber', $subscriber + [
			'user_id' => $userId,
			'affiliate_id' => $affiliate->getAttribute('id'),
			'status' => Subscriber::STATUS_ACTIVE,
			'deleted' => Subscriber::NO,
			'created_at' => '2026-09-01 10:00:00',
		]);
	}

	/**
	 * @param string $subscriberId raw
	 * @param int $status
	 * @param array $attributes
	 * @return string raw id
	 */
	private function insertSubscription($subscriberId, $status, array $attributes = [])
	{
		return $this->insertRow('subscription', $attributes + [
			'subscriber_id' => $subscriberId,
			'code' => 'S' . bin2hex(random_bytes(5)),
			'price' => '10.00',
			'currency' => 'RON',
			'status' => $status,
			'deleted' => Subscription::NO,
		]);
	}

	/**
	 * A paid master invoice of a subscriber, for a commission row to point at.
	 *
	 * @param string|null $subscriberId raw
	 * @return string raw id
	 */
	private function insertInvoice($subscriberId = null)
	{
		return $this->insertRow('invoice', [
			'subscriber_id' => $subscriberId,
			'type' => Invoice::TYPE_INVOICE,
			'status' => Invoice::STATUS_PAID,
			'deleted' => Invoice::NO,
		]);
	}

	public function testTheSubscriberListShowsOnlyMaskedData()
	{
		$id = $this->insertSubscriber($this->affiliate, ['email' => 'ion.popescu@gmail.com', 'phone' => '+40722123456']);
		$packageId = $this->insertRow('package', ['status' => 1, 'deleted' => 0]);
		$this->insertRow('package_translation', ['package_id' => $packageId, 'language_id' => Yii::$app->language, 'name' => 'Premium']);
		$this->insertSubscription($id, Subscription::STATUS_ACTIVE, ['package_id' => $packageId, 'price' => '49.00']);

		$models = (new AffiliateSubscriberSearch(['affiliate' => $this->affiliate]))->search()->getModels();
		$this->assertCount(1, $models);
		$row = AffiliateSubscriberSearch::formatRow($models[0]);

		$this->assertSame('Ion P.', $row['name']);
		$this->assertSame('i***@gmail.com', $row['email']);
		$serialized = json_encode($row);
		$this->assertStringNotContainsString('Popescu', $serialized);
		$this->assertStringNotContainsString('ion.popescu', $serialized);
		$this->assertStringNotContainsString('722123456', $serialized);
		$this->assertSame('Premium', $row['subscriptions'][0]['package']);
	}

	public function testTheSubscriberListHoldsOnlyTheAffiliatesOwn()
	{
		$this->insertSubscriber($this->affiliate);
		$this->insertSubscriber($this->other, ['first_name' => 'Maria']);
		$this->insertSubscriber($this->affiliate, ['first_name' => 'Gone'], ['deleted' => Subscriber::YES]);
		// A subscriber nobody referred.
		$this->insertRow('subscriber', [
			'user_id' => $this->insertRow('user', ['first_name' => 'Direct', 'status' => User::STATUS_ACTIVE, 'deleted' => User::NO]),
			'status' => Subscriber::STATUS_ACTIVE,
			'deleted' => Subscriber::NO,
		]);

		$models = (new AffiliateSubscriberSearch(['affiliate' => $this->affiliate]))->search()->getModels();

		$this->assertSame(['Ion'], array_map(function (Subscriber $model) {
			return $model->user->first_name;
		}, $models));
	}

	// ---------------------------------------------------------------------- dashboard

	public function testTheDashboardFiguresAreTheAffiliatesOwn()
	{
		$mine = $this->insertSubscriber($this->affiliate);
		$theirs = $this->insertSubscriber($this->other);
		foreach ([[$mine, Subscription::STATUS_ACTIVE], [$mine, Subscription::STATUS_CANCELLED], [$theirs, Subscription::STATUS_ACTIVE]] as list($subscriber, $status)) {
			$this->insertSubscription($subscriber, $status);
		}
		foreach ([
			[$this->affiliate, $mine, '100.00', '10.00', AffiliateCommission::STATUS_PAID],
			[$this->affiliate, $mine, '50.00', '5.00', AffiliateCommission::STATUS_PENDING],
			[$this->affiliate, $mine, '80.00', '8.00', AffiliateCommission::STATUS_CANCELLED],
			[$this->other, $theirs, '999.00', '99.90', AffiliateCommission::STATUS_PAID],
		] as list($affiliate, $subscriber, $base, $amount, $status)) {
			$this->insertRow('affiliate_commission', [
				'affiliate_id' => $affiliate->getAttribute('id'),
				'invoice_id' => $this->insertInvoice($subscriber),
				'subscriber_id' => $subscriber,
				'base_amount' => $base,
				'currency' => 'RON',
				'percentage' => 10,
				'amount' => $amount,
				'status' => $status,
			]);
		}

		$dashboard = new AffiliateDashboard(['affiliate' => $this->affiliate]);

		$this->assertSame(1, $dashboard->getSignupCount());
		$this->assertSame(1, $dashboard->getActiveSubscriptionCount());
		$this->assertEquals(150.0, (float) $dashboard->getRevenueTotals()['RON']);
		$this->assertEquals(10.0, (float) $dashboard->getCommissionTotals()['RON'][AffiliateCommission::STATUS_PAID]);
		$this->assertEquals(5.0, (float) $dashboard->getCommissionTotals()['RON'][AffiliateCommission::STATUS_PENDING]);
		$this->assertSame(['RON'], $dashboard->getCurrencies());
	}

	public function testTheDashboardShowsOneReferralLinkWithTheCode()
	{
		$url = (new AffiliateDashboard(['affiliate' => $this->affiliate]))->getReferralUrl();

		$this->assertStringEndsWith('/?aff=MINE0001', $url);
		$this->assertSame($this->affiliate->getReferralUrl(), $url);
	}

	// ------------------------------------------------------------------------ profile

	public function testThePureAffiliateProfileHasNoSubscriberFields()
	{
		// Populated without afterFind(), which also reads marketing_recipient (absent from master_test).
		$form = new ProfileForm();
		ProfileForm::populateRecord($form, (new \yii\db\Query())->from('user')->where(['id' => $this->affiliate->user_id])->one());

		$this->assertFalse($form->getHasSubscriberProfile());
		$this->assertNotContains('address', $form->safeAttributes());
		$this->assertNotContains('date_of_birth', $form->safeAttributes());
		$this->assertContains('first_name', $form->safeAttributes());
	}
}
