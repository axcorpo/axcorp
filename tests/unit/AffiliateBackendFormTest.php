<?php

namespace tests\unit;

use backend\modules\affiliate\models\AffiliateForm;
use backend\modules\affiliate\models\PayoutCreateForm;
use backend\modules\affiliate\models\PayoutPaidForm;
use backend\modules\affiliate\models\PayoutProofForm;
use backend\modules\affiliate\models\PayoutRejectForm;
use common\models\Affiliate;
use common\models\User;
use tests\DatabaseTestCase;
use tests\support\FakeSettings;
use Yii;
use yii\db\Query;

/**
 * The admin affiliate form: creating an affiliate creates its master user account
 * without any RBAC assignment (an affiliate must never reach the backend), reuses a plain
 * front-end account with the same e-mail, refuses an administration account's e-mail, and
 * — like every admin form since the audit — only loads the fields it renders.
 */
class AffiliateBackendFormTest extends DatabaseTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		// Automatic activation: no activation e-mail (it needs the frontend `page` rows).
		$settings = new FakeSettings();
		$settings->values['general'] = ['userAccountActivation' => User::ACCOUNT_ACTIVATION_AUTOMATIC];
		Yii::$app->set('settings', $settings);
		Yii::$app->set('mailer', [
			'class' => 'yii\symfonymailer\Mailer',
			'useFileTransport' => true,
			'fileTransportPath' => sys_get_temp_dir() . '/affiliate-form-test-mail',
		]);
	}

	protected function tearDown(): void
	{
		Yii::$app->clear('settings');
		Yii::$app->clear('mailer');
		parent::tearDown();
	}

	/**
	 * @param array $attributes
	 * @return AffiliateForm
	 */
	private function form(array $attributes = [])
	{
		$form = new AffiliateForm();
		$form->applyDefaults();
		$form->load(['AffiliateForm' => array_merge([
			'email' => 'new.affiliate@example.test',
			'first_name' => 'Ana',
			'last_name' => 'Pop',
			'commission_percentage' => '12.5',
			'status' => (string) Affiliate::STATUS_ACTIVE,
		], $attributes)]);
		return $form;
	}

	/**
	 * @param string $userId raw bytes
	 * @return int
	 */
	private function assignments($userId)
	{
		return (int) (new Query())->from('auth_assignment')->where(['user_id' => $userId])->count();
	}

	public function testCreatingAnAffiliateCreatesItsUserWithoutAnyRole()
	{
		$form = $this->form(['code' => 'promo2026']);

		$this->assertTrue($form->save(), json_encode($form->getErrors()));
		$this->assertFalse($form->linkedExistingUser);

		$affiliate = Affiliate::findOne(['id' => $form->getAttribute('id')]);
		$this->assertNotNull($affiliate);
		$this->assertSame('PROMO2026', $affiliate->code);
		$this->assertEquals(12.5, (float) $affiliate->commission_percentage);

		$user = User::findOne(['id' => $affiliate->user_id]);
		$this->assertNotNull($user);
		$this->assertSame('new.affiliate@example.test', $user->email);
		$this->assertSame('Ana', $user->first_name);
		$this->assertEquals(User::STATUS_ACTIVE, (int) $user->status);
		$this->assertNotEmpty($user->password_hash, 'a random password is set');
		$this->assertNotEmpty($user->auth_key);
		$this->assertSame(0, $this->assignments($user->id), 'an affiliate must not get any RBAC assignment');
		$this->assertTrue($user->getIsAffiliate());
	}

	public function testTheCodeIsGeneratedWhenLeftEmpty()
	{
		$form = $this->form(['code' => '']);

		$this->assertTrue($form->save(), json_encode($form->getErrors()));
		$this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', Affiliate::findOne(['id' => $form->getAttribute('id')])->code);
	}

	public function testAPlainFrontendAccountWithTheEmailIsLinkedAsIs()
	{
		$userId = $this->insertRow('user', [
			'email' => 'existing@example.test',
			'first_name' => 'Ion',
			'last_name' => 'Ionescu',
			'password_hash' => 'kept-hash',
			'status' => User::STATUS_ACTIVE,
			'deleted' => 0,
		]);

		$form = $this->form(['email' => 'existing@example.test', 'first_name' => 'Other']);

		$this->assertTrue($form->save(), json_encode($form->getErrors()));
		$this->assertTrue($form->linkedExistingUser);
		$this->assertSame($userId, Affiliate::findOne(['id' => $form->getAttribute('id')])->user_id);
		$user = User::findOne(['id' => $userId]);
		$this->assertSame('Ion', $user->first_name, 'the linked account is not modified');
		$this->assertSame('kept-hash', $user->password_hash);
		$this->assertSame(1, (int) (new Query())->from('user')->where(['email' => 'existing@example.test'])->count());
	}

	public function testTheEmailOfAnAdministrationAccountIsRefused()
	{
		$userId = $this->insertRow('user', [
			'email' => 'admin@example.test',
			'status' => User::STATUS_ACTIVE,
			'deleted' => 0,
		]);
		$this->insertRow('auth_assignment', ['item_name' => 'superAdmin', 'user_id' => $userId, 'created_at' => time()]);

		$form = $this->form(['email' => 'admin@example.test']);

		$this->assertFalse($form->save());
		$this->assertTrue($form->hasErrors('email'));
		$this->assertSame(0, (int) Affiliate::find()->where(['user_id' => $userId])->count());
	}

	public function testAUserCannotBecomeAffiliateTwice()
	{
		$this->assertTrue($this->form()->save());

		$second = $this->form(['code' => 'OTHER123']);
		$this->assertFalse($second->save());
		$this->assertTrue($second->hasErrors('email'));
	}

	public function testUpdateChangesTheAffiliateAndItsUser()
	{
		$form = $this->form();
		$this->assertTrue($form->save());

		$update = AffiliateForm::findOne(['id' => $form->getAttribute('id')]);
		$this->assertSame('new.affiliate@example.test', $update->email, 'user fields are loaded for editing');
		$update->load(['AffiliateForm' => ['last_name' => 'Popescu', 'commission_months' => '6']]);
		$this->assertTrue($update->save(), json_encode($update->getErrors()));

		$affiliate = Affiliate::findOne(['id' => $form->getAttribute('id')]);
		$this->assertSame(6, (int) $affiliate->commission_months);
		$this->assertSame('Popescu', $affiliate->user->last_name);
		$this->assertSame(0, $this->assignments($affiliate->user_id));
	}

	public function testTheAffiliateFormLoadsOnlyTheRenderedFields()
	{
		$form = new AffiliateForm();
		$this->assertEqualsCanonicalizing([
			'email', 'phone', 'gender', 'first_name', 'middle_name', 'last_name',
			'code', 'commission_percentage', 'commission_months', 'status', 'notes',
		], $form->safeAttributes());

		$victim = $this->insertRow('user', ['email' => 'victim@example.test', 'status' => 1, 'deleted' => 0]);
		$form->load(['AffiliateForm' => [
			'user_id' => \common\helpers\UuidHelper::fromBytes($victim),
			'deleted' => 1,
			'created_by' => \common\helpers\UuidHelper::fromBytes($victim),
			'created_at' => '2000-01-01 00:00:00',
		]]);
		$this->assertNull($form->user_id);
		$this->assertNotEquals(1, $form->deleted);
		$this->assertNull($form->created_by);
		$this->assertNull($form->created_at);
	}

	public function testThePayoutFormsLoadOnlyTheirFields()
	{
		$this->assertEqualsCanonicalizing(['affiliate_id', 'currency', 'notes'], (new PayoutCreateForm())->safeAttributes());
		$this->assertEqualsCanonicalizing(['paid_at', 'withoutInvoice'], (new PayoutPaidForm())->safeAttributes());
		$this->assertEqualsCanonicalizing(['reason'], (new PayoutRejectForm())->safeAttributes());
		$this->assertEqualsCanonicalizing(['proofFiles'], (new PayoutProofForm())->safeAttributes());

		$paid = new PayoutPaidForm();
		$paid->paid_at = date('Y-m-d', strtotime('+1 day'));
		$this->assertFalse($paid->validate(), 'a payment cannot be dated in the future');
	}
}
