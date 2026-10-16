<?php

namespace tests\unit;

use backend\modules\setting\models\AffiliateSettingForm;
use common\models\AffiliateSettings;
use common\models\Setting;
use tests\DatabaseTestCase;

/**
 * The `affiliate` settings screen: saves exactly the keys AffiliateSettings reads, as the
 * app setting row `affiliate`, validates the ranges and loads nothing else.
 */
class AffiliateSettingFormTest extends DatabaseTestCase
{
	public function testSavingStoresTheAffiliateGroup()
	{
		$form = new AffiliateSettingForm();
		$this->assertTrue($form->load(['AffiliateSettingForm' => [
			'holdDays' => '15',
			'defaultPercentage' => '12.5',
			'minimumPayout' => '100',
			'cookieDays' => '45',
		]]));
		$this->assertNotNull($form->saveModel(), json_encode($form->getErrors()));

		$row = Setting::findOne(['name' => AffiliateSettings::GROUP]);
		$this->assertNotNull($row);
		$this->assertEquals(Setting::TYPE_APP, (int) $row->type);
		$this->assertEquals(Setting::STATUS_ACTIVE, (int) $row->status);
		$this->assertSame([
			AffiliateSettings::HOLD_DAYS => 15,
			AffiliateSettings::DEFAULT_PERCENTAGE => 12.5,
			AffiliateSettings::MINIMUM_PAYOUT => 100.0,
			AffiliateSettings::COOKIE_DAYS => 45,
		], unserialize($row->setting));

		// Reopening the screen shows the saved values.
		$reloaded = AffiliateSettingForm::findOne(['name' => AffiliateSettings::GROUP]);
		$this->assertEquals(15, $reloaded->holdDays);
		$this->assertEquals(12.5, $reloaded->defaultPercentage);
		$this->assertEquals(100, $reloaded->minimumPayout);
		$this->assertEquals(45, $reloaded->cookieDays);

		// A second save updates the same row.
		$reloaded->load(['AffiliateSettingForm' => ['holdDays' => '0', 'defaultPercentage' => '10', 'minimumPayout' => '0', 'cookieDays' => '1']]);
		$this->assertNotNull($reloaded->saveModel());
		$this->assertSame(1, (int) Setting::find()->where(['name' => AffiliateSettings::GROUP])->count());
		$this->assertSame(0, unserialize(Setting::findOne(['name' => AffiliateSettings::GROUP])->setting)[AffiliateSettings::HOLD_DAYS]);
	}

	public function testTheCookieLifetimeIsOneDayToAYear()
	{
		$form = new AffiliateSettingForm();
		$form->load(['AffiliateSettingForm' => ['holdDays' => '5', 'defaultPercentage' => '5', 'minimumPayout' => '0', 'cookieDays' => '90']]);
		$this->assertNotNull($form->saveModel(), json_encode($form->getErrors()));

		$form = new AffiliateSettingForm();
		$form->load(['AffiliateSettingForm' => ['holdDays' => '5', 'defaultPercentage' => '5', 'minimumPayout' => '0', 'cookieDays' => '366']]);
		$this->assertNull($form->saveModel());
		$this->assertTrue($form->hasErrors('cookieDays'));
	}

	public function testANewFormShowsTheDefaults()
	{
		$form = new AffiliateSettingForm();
		$this->assertEquals(AffiliateSettings::DEFAULTS[AffiliateSettings::HOLD_DAYS], $form->holdDays);
		$this->assertEquals(AffiliateSettings::DEFAULTS[AffiliateSettings::DEFAULT_PERCENTAGE], $form->defaultPercentage);
		$this->assertEquals(AffiliateSettings::DEFAULTS[AffiliateSettings::MINIMUM_PAYOUT], $form->minimumPayout);
		$this->assertEquals(AffiliateSettings::DEFAULTS[AffiliateSettings::COOKIE_DAYS], $form->cookieDays);
	}

	public function testOutOfRangeValuesAreRefused()
	{
		$form = new AffiliateSettingForm();
		$form->load(['AffiliateSettingForm' => ['holdDays' => '-1', 'defaultPercentage' => '101', 'minimumPayout' => '-5', 'cookieDays' => '0']]);

		$this->assertNull($form->saveModel());
		$this->assertTrue($form->hasErrors('holdDays'));
		$this->assertTrue($form->hasErrors('defaultPercentage'));
		$this->assertTrue($form->hasErrors('minimumPayout'));
		$this->assertTrue($form->hasErrors('cookieDays'));
		$this->assertNull(Setting::findOne(['name' => AffiliateSettings::GROUP]));
	}

	public function testOnlyTheAffiliateSettingsAreAssignable()
	{
		$form = new AffiliateSettingForm();
		$this->assertEqualsCanonicalizing(['holdDays', 'defaultPercentage', 'minimumPayout', 'cookieDays'], $form->safeAttributes());
		// The form saves exactly the keys AffiliateSettings reads.
		$this->assertEqualsCanonicalizing(array_keys(AffiliateSettings::DEFAULTS), $form->safeAttributes());

		$form->load(['AffiliateSettingForm' => ['name' => 'general', 'setting' => 'x', 'status' => 0, 'holdDays' => '5', 'defaultPercentage' => '5', 'minimumPayout' => '5', 'cookieDays' => '30']]);
		$this->assertNotNull($form->saveModel());
		$this->assertSame(AffiliateSettings::GROUP, $form->name);
		$this->assertNull(Setting::findOne(['name' => 'general']));
	}
}
