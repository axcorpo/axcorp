<?php

namespace tests\unit;

use common\models\Affiliate;
use common\models\Company;
use common\models\Template;
use common\models\User;
use common\services\AffiliateApplicationService;
use frontend\models\AffiliateApplicationForm;
use tests\DatabaseTestCase;
use tests\support\FakeSettings;
use Yii;
use yii\base\UserException;
use yii\db\Query;
use yii\helpers\FileHelper;

/**
 * The public affiliate application: a company applies, which creates the account (for a
 * guest), the company with the account's company form and a PENDING affiliate; the admin
 * then approves or rejects it, and each decision is e-mailed to the applicant.
 */
class AffiliateApplicationTest extends DatabaseTestCase
{
	/**
	 * @var string
	 */
	private $mailPath;

	protected function setUp(): void
	{
		parent::setUp();
		$this->mailPath = sys_get_temp_dir() . '/affiliate-application-test-' . bin2hex(random_bytes(4));
		$settings = new FakeSettings();
		$settings->values['general'] = [
			'userAccountActivation' => User::ACCOUNT_ACTIVATION_AUTOMATIC,
			'defaultCountry' => 'RO',
		];
		$settings->values['contact'] = ['email' => 'office@example.test'];
		Yii::$app->set('settings', $settings);
		Yii::$app->set('mailer', [
			'class' => 'yii\symfonymailer\Mailer',
			'useFileTransport' => true,
			'fileTransportPath' => $this->mailPath,
			'viewPath' => '@common/mail',
			'messageConfig' => ['from' => 'noreply@example.test'],
		]);

		foreach ([
			Template::EMAIL_VARIANT_AFFILIATE_APPLICATION_APPROVED => 'Approved {{AFFILIATE_CODE}}',
			Template::EMAIL_VARIANT_AFFILIATE_APPLICATION_REJECTED => 'Rejected {{COMPANY_NAME}}',
		] as $variant => $subject) {
			$templateId = $this->insertRow('template', [
				'type' => Template::TYPE_EMAIL,
				'variant' => $variant,
				'default' => 1,
				'status' => 1,
				'deleted' => 0,
			]);
			$this->insertRow('template_translation', [
				'template_id' => $templateId,
				'language_id' => 'en-US',
				'name' => "Variant {$variant}",
				'subject' => $subject,
				'content' => '<p>Hi {{FIRST_NAME}}: {{COMPANY_NAME}} / {{AFFILIATE_CODE}}. {{REASON}}</p>',
				'deleted' => 0,
			]);
		}
	}

	protected function tearDown(): void
	{
		FileHelper::removeDirectory($this->mailPath);
		Yii::$app->clear('mailer');
		Yii::$app->clear('settings');
		parent::tearDown();
	}

	/**
	 * @param User|null $user
	 * @param array $form
	 * @param array|null $company null = no company posted
	 * @return AffiliateApplicationForm
	 */
	private function application($user = null, array $form = [], $company = [])
	{
		$model = new AffiliateApplicationForm($user);
		$data = ['AffiliateApplicationForm' => array_merge([
			'first_name' => 'Ana',
			'last_name' => 'Pop',
			'email' => 'ana.pop@example.test',
			'phone' => '+40733000222',
			'password' => 'Affiliate#Pass2026',
			'password_confirm' => 'Affiliate#Pass2026',
			'application_message' => 'I run a <b>blog</b> for small businesses.',
			'acceptTerms' => '1',
		], $form)];
		if ($company !== null) {
			$data['CompanyForm'] = array_merge([
				'tin' => 'RO12345678',
				'registration_number' => 'J35/1/2020',
				'name' => 'Pop Media SRL',
				'email' => 'office@popmedia.test',
				'phone' => '+40722000111',
				'country' => 'RO',
				'county' => 'Timiș',
				'locality' => 'Timișoara',
				'address' => 'Str. Exemplu 1',
			], $company);
		}
		$model->load($data);
		return $model;
	}

	/**
	 * @return string[] the raw .eml files written
	 */
	private function mails()
	{
		return array_map(function ($file) {
			return quoted_printable_decode(file_get_contents($file));
		}, glob($this->mailPath . '/*.eml') ?: []);
	}

	/**
	 * @param string $email
	 * @return Affiliate|null
	 */
	private function affiliateOf($email)
	{
		$user = User::findOne(['email' => $email]);
		return $user ? Affiliate::findOne(['user_id' => $user->id]) : null;
	}

	public function testAGuestApplicationCreatesTheAccountTheCompanyAndAPendingAffiliate()
	{
		$model = $this->application();

		$this->assertTrue($model->save(), json_encode([$model->getErrors(), $model->company->getErrors()]));

		$user = User::findOne(['email' => 'ana.pop@example.test']);
		$this->assertNotNull($user);
		$this->assertTrue($user->validatePassword('Affiliate#Pass2026'));
		$this->assertSame(0, (int) (new Query())->from('auth_assignment')->where(['user_id' => $user->id])->count(), 'an applicant gets no RBAC role');

		$company = Company::findOne(['user_id' => $user->id]);
		$this->assertNotNull($company);
		$this->assertSame('Pop Media SRL', $company->name);
		$this->assertSame('RO12345678', $company->tin);

		$affiliate = Affiliate::findOne(['user_id' => $user->id]);
		$this->assertNotNull($affiliate);
		$this->assertSame(Affiliate::STATUS_PENDING, (int) $affiliate->status);
		$this->assertTrue($affiliate->getIsPending());
		$this->assertFalse($affiliate->getIsActive());
		$this->assertSame('I run a blog for small businesses.', $affiliate->application_message, 'tags are stripped');
		$this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $affiliate->code);
		$this->assertSame($company->id, $affiliate->company->id);
		$this->assertFalse($user->getIsAffiliate(), 'a pending affiliate cannot reach the affiliate area');
		$this->assertNull(Affiliate::findActiveByCode($affiliate->code), 'a pending affiliate does not attribute signups');

		$mails = $this->mails();
		$this->assertCount(1, $mails, 'the notice to the site');
		$this->assertStringContainsString('office@example.test', $mails[0]);
		$this->assertStringContainsString('Pop Media SRL', $mails[0]);
	}

	public function testTheCompanyIsRequired()
	{
		$model = $this->application(null, [], null);

		$this->assertFalse($model->save());
		$this->assertTrue($model->company->hasErrors('tin'));
		$this->assertTrue($model->company->hasErrors('name'));
		$this->assertNull(User::findOne(['email' => 'ana.pop@example.test']), 'nothing is saved');
	}

	public function testAGuestCannotApplyWithTheEmailOfAnExistingAccount()
	{
		$this->insertRow('user', ['email' => 'ana.pop@example.test', 'status' => User::STATUS_ACTIVE, 'deleted' => 0]);

		$model = $this->application();

		$this->assertFalse($model->save());
		$this->assertTrue($model->hasErrors('email'));
	}

	public function testTheTermsMustBeAccepted()
	{
		$model = $this->application(null, ['acceptTerms' => '']);

		$this->assertFalse($model->save());
		$this->assertTrue($model->hasErrors('acceptTerms'));
	}

	public function testTheHoneypotDropsTheApplication()
	{
		$model = $this->application(null, ['workEmail' => 'bot@example.test']);

		$this->assertFalse($model->save());
		$this->assertNull(User::findOne(['email' => 'ana.pop@example.test']));
	}

	public function testASignedInUserWithACompanyAppliesWithThatCompany()
	{
		$userId = $this->insertRow('user', ['email' => 'member@example.test', 'first_name' => 'Ion', 'status' => User::STATUS_ACTIVE, 'deleted' => 0]);
		$companyId = $this->insertRow('company', ['user_id' => $userId, 'name' => 'Existing SRL', 'status' => 1, 'deleted' => 0]);
		$user = User::findOne(['id' => $userId]);

		$model = $this->application($user, ['email' => 'other@example.test', 'first_name' => 'Hacker'], null);

		$this->assertFalse($model->getIsGuestApplicant());
		$this->assertFalse($model->getNeedsCompany());
		$this->assertTrue($model->save(), json_encode($model->getErrors()));

		$affiliate = Affiliate::findOne(['user_id' => $userId]);
		$this->assertSame(Affiliate::STATUS_PENDING, (int) $affiliate->status);
		$this->assertSame($companyId, $affiliate->company->id);
		$this->assertSame(1, (int) Company::find()->where(['user_id' => $userId])->count(), 'no second company');
		$this->assertSame('Ion', User::findOne(['id' => $userId])->first_name, 'the account fields are not loaded for a signed-in user');
		$this->assertNull(User::findOne(['email' => 'other@example.test']));
	}

	public function testASignedInUserWithoutACompanyEntersOne()
	{
		$userId = $this->insertRow('user', ['email' => 'member@example.test', 'status' => User::STATUS_ACTIVE, 'deleted' => 0]);

		$model = $this->application(User::findOne(['id' => $userId]));

		$this->assertTrue($model->getNeedsCompany());
		$this->assertTrue($model->save(), json_encode([$model->getErrors(), $model->company->getErrors()]));
		$this->assertSame('Pop Media SRL', Affiliate::findOne(['user_id' => $userId])->company->name);
	}

	public function testAnExistingAffiliateCannotApplyAgain()
	{
		$userId = $this->insertRow('user', ['email' => 'member@example.test', 'status' => User::STATUS_ACTIVE, 'deleted' => 0]);
		$this->insertRow('affiliate', ['user_id' => $userId, 'code' => 'EXIST001', 'commission_percentage' => 10, 'status' => Affiliate::STATUS_REJECTED, 'deleted' => 0]);

		$model = $this->application(User::findOne(['id' => $userId]));

		$this->assertFalse($model->save());
		$this->assertSame(1, (int) Affiliate::find()->where(['user_id' => $userId])->count());
	}

	public function testOnlyTheRenderedFieldsAreAssignable()
	{
		$model = new AffiliateApplicationForm(null);
		$model->load(['AffiliateApplicationForm' => ['status' => Affiliate::STATUS_ACTIVE, 'commission_percentage' => 90]]);

		$this->assertNotContains('status', $model->safeAttributes());
		$this->assertNotContains('commission_percentage', $model->safeAttributes());

		$signedIn = new AffiliateApplicationForm(new User());
		$this->assertNotContains('email', $signedIn->safeAttributes());
		$this->assertNotContains('password', $signedIn->safeAttributes());
	}

	public function testApprovingActivatesTheAffiliateAndEmailsTheApplicant()
	{
		$this->assertTrue($this->application()->save());
		FileHelper::removeDirectory($this->mailPath);
		$affiliate = $this->affiliateOf('ana.pop@example.test');

		(new AffiliateApplicationService())->approve($affiliate);

		$affiliate->refresh();
		$this->assertSame(Affiliate::STATUS_ACTIVE, (int) $affiliate->status);
		$this->assertNotNull($affiliate->reviewed_at);
		$this->assertTrue($affiliate->getIsActive());
		$this->assertNotNull(Affiliate::findActiveByCode($affiliate->code));

		$mails = $this->mails();
		$this->assertCount(1, $mails);
		$this->assertStringContainsString('ana.pop@example.test', $mails[0]);
		$this->assertStringContainsString('Approved ' . $affiliate->code, $mails[0]);

		$this->expectException(UserException::class);
		(new AffiliateApplicationService())->approve($affiliate);
	}

	public function testRejectingNeedsAReasonAndEmailsIt()
	{
		$this->assertTrue($this->application()->save());
		FileHelper::removeDirectory($this->mailPath);
		$affiliate = $this->affiliateOf('ana.pop@example.test');
		$service = new AffiliateApplicationService();

		try {
			$service->reject($affiliate, '  ');
			$this->fail('an empty reason must be refused');
		} catch (UserException $e) {
			$affiliate->refresh();
			$this->assertSame(Affiliate::STATUS_PENDING, (int) $affiliate->status);
		}

		$service->reject($affiliate, 'No <b>activity</b> yet');

		$affiliate->refresh();
		$this->assertSame(Affiliate::STATUS_REJECTED, (int) $affiliate->status);
		$this->assertSame('No <b>activity</b> yet', $affiliate->rejection_reason);
		$this->assertFalse($affiliate->getIsActive());

		$mails = $this->mails();
		$this->assertCount(1, $mails);
		$this->assertStringContainsString('Rejected Pop Media SRL', $mails[0]);
		$this->assertStringContainsString('No &lt;b&gt;activity&lt;/b&gt; yet', $mails[0], 'the reason is HTML-encoded');

		$this->expectException(UserException::class);
		$service->approve($affiliate);
	}
}
