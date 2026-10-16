<?php

namespace tests\unit;

use backend\modules\affiliate\models\AffiliateReportForm;
use backend\modules\affiliate\models\PayoutPaidForm;
use common\helpers\DateHelper;
use tests\WebTestCase;
use Yii;

/**
 * The affiliate date inputs are DateTimePickers in the `datetimeFormat` setting
 * (dd.MM.yyyy HH:mm:ss on the dev database): what they send is parsed strictly to
 * "Y-m-d H:i:s" before validation, and anything else is a validation error, never an
 * exception. The report period is a pair of days in the `dateFormat` setting, as the list
 * date filters, and keeps AffiliateStatsService's exclusive end.
 */
class AffiliateDateInputTest extends WebTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		Yii::$app->settings->set('datetimeFormat', 'dd.MM.yyyy HH:mm:ss');
		Yii::$app->settings->set('dateFormat', 'dd.MM.yyyy');
	}

	/**
	 * @return array
	 */
	public function validProvider()
	{
		return [
			'settings datetime' => ['01.10.2026 13:45:07', '2026-10-01 13:45:07'],
			'settings date' => ['01.10.2026', '2026-10-01 00:00:00'],
			'surrounding spaces' => [' 01.10.2026 13:45:07 ', '2026-10-01 13:45:07'],
			'database datetime' => ['2026-10-01 23:59:59', '2026-10-01 23:59:59'],
			'database date' => ['2026-10-01', '2026-10-01 00:00:00'],
		];
	}

	/**
	 * @dataProvider validProvider
	 * @param string $input
	 * @param string $expected
	 */
	public function testParseDateTimeReadsTheSettingsAndDatabaseFormats($input, $expected)
	{
		$this->assertSame($expected, DateHelper::parseDateTime($input));
	}

	/**
	 * @return array
	 */
	public function invalidProvider()
	{
		return [
			'no such day' => ['31.02.2026 10:00:00'],
			'no such hour' => ['01.10.2026 25:00:00'],
			'month first' => ['10/01/2026'],
			'relative' => ['next monday'],
			'garbage' => ['x'],
			'markup' => ['<script>'],
		];
	}

	/**
	 * @dataProvider invalidProvider
	 * @param string $input
	 */
	public function testParseDateTimeRejectsAnythingElse($input)
	{
		$this->assertNull(DateHelper::parseDateTime($input));
	}

	public function testParseDateTimeRoundTripsTheFormatter()
	{
		// What the views display (formatter->asDatetime) is what the forms read back.
		Yii::$app->formatter->datetimeFormat = 'dd.MM.yyyy HH:mm:ss';
		$shown = Yii::$app->formatter->asDatetime('2026-03-09 08:05:00');

		$this->assertSame('09.03.2026 08:05:00', $shown);
		$this->assertSame('2026-03-09 08:05:00', DateHelper::parseDateTime($shown));
		$this->assertNull(DateHelper::parseDateTime(''));
		$this->assertNull(DateHelper::parseDateTime(null));
	}

	// ------------------------------------------------------------------------ report

	public function testTheReportDefaultsAreTheLastTwelveMonthsUpToToday()
	{
		$form = new AffiliateReportForm();

		$this->assertTrue($form->validate(), json_encode($form->getErrors()));
		$this->assertSame((new \DateTime('first day of this month'))->modify('-11 months')->format('Y-m-d'), $form->from);
		$this->assertSame(date('Y-m-d'), $form->to);
	}

	public function testTheReportPeriodIsReadInTheSettingsDateFormat()
	{
		$form = new AffiliateReportForm();
		$form->load(['from' => '01.01.2026', 'to' => '30.06.2026']);

		$this->assertTrue($form->validate(), json_encode($form->getErrors()));
		$this->assertSame('2026-01-01', $form->from);
		$this->assertSame('2026-06-30', $form->to);

		// The "to" day is included whole; the service's end is exclusive.
		$stats = $form->createStats();
		$this->assertSame('2026-01-01 00:00:00', $stats->from);
		$this->assertSame('2026-07-01 00:00:00', $stats->to);
	}

	public function testAReportPeriodWithATimeKeepsOnlyTheDay()
	{
		// Older bookmarks carry the datetime format: the time is dropped, the day kept.
		$form = new AffiliateReportForm();
		$form->load(['from' => '01.01.2026 13:00:00', 'to' => '2026-06-30 18:30:00']);

		$this->assertTrue($form->validate(), json_encode($form->getErrors()));
		$this->assertSame('2026-01-01', $form->from);
		$this->assertSame('2026-06-30', $form->to);
	}

	public function testAnInvalidReportPeriodIsAValidationError()
	{
		$form = new AffiliateReportForm();
		$form->load(['from' => '31.02.2026', 'to' => 'x']);

		$this->assertFalse($form->validate());
		$this->assertTrue($form->hasErrors('from'));
		$this->assertTrue($form->hasErrors('to'));
		$this->assertNull($form->createStats());
		$this->assertSame([], $form->getRows());
	}

	// --------------------------------------------------------------------- mark paid

	public function testThePaymentMomentIsReadInTheSettingsFormat()
	{
		$form = new PayoutPaidForm();
		$form->load(['PayoutPaidForm' => ['paid_at' => '15.09.2026 09:30:00']]);

		$this->assertTrue($form->validate(), json_encode($form->getErrors()));
		$this->assertSame('2026-09-15 09:30:00', $form->getPaidAt());
	}

	public function testThePaymentMomentDefaultsToNow()
	{
		$form = new PayoutPaidForm();

		$this->assertTrue($form->validate(), json_encode($form->getErrors()));
		$this->assertEqualsWithDelta(time(), strtotime($form->getPaidAt()), 5);
	}

	public function testAnInvalidOrFuturePaymentMomentIsAValidationError()
	{
		foreach (['31.09.2026 10:00:00', 'yesterday', date('d.m.Y H:i:s', strtotime('+2 days'))] as $value) {
			$form = new PayoutPaidForm();
			$form->load(['PayoutPaidForm' => ['paid_at' => $value]]);

			$this->assertFalse($form->validate(), $value);
			$this->assertTrue($form->hasErrors('paid_at'), $value);
		}
	}
}
