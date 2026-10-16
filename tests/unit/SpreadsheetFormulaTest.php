<?php

namespace tests\unit;

use common\helpers\SpreadsheetHelper;
use PHPUnit\Framework\TestCase;

/**
 * Formula neutralisation in the exports.
 *
 * Every string in an export is something somebody typed into the application, and a cell
 * beginning `=`, `+`, `-` or `@` is evaluated when the recipient opens the file — which
 * is how a client name becomes a link to somewhere else, or a lookup into the rest of the
 * sheet.
 */
class SpreadsheetFormulaTest extends TestCase
{
	/**
	 * normalizeRow() is protected, and it is the seam every export goes through.
	 *
	 * @param array $row
	 * @return array
	 */
	private function normalize(array $row)
	{
		$method = new \ReflectionMethod(SpreadsheetHelper::class, 'normalizeRow');
		$method->setAccessible(true);

		return $method->invoke(null, $row);
	}

	/**
	 * @dataProvider formulaProvider
	 * @param string $value
	 */
	public function testFormulaBearingValuesAreMarkedAsText($value)
	{
		$this->assertSame("'" . $value, $this->normalize([$value])[0]);
	}

	/**
	 * @return array
	 */
	public function formulaProvider()
	{
		return [
			'equals' => ['=1+1'],
			'plus' => ['+1+1'],
			'at' => ['@SUM(A1:A9)'],
			'a link out' => ['=HYPERLINK("https://attacker.example","Click")'],
			'leading whitespace' => ['   =1+1'],
			'leading tab' => ["\t=1+1"],
			'full-width equals' => ["\u{FF1D}1+1"],
		];
	}

	public function testOrdinaryTextIsLeftAlone()
	{
		$this->assertSame('SC Test SRL', $this->normalize(['SC Test SRL'])[0]);
	}

	/**
	 * @dataProvider numberProvider
	 * @param string $value
	 */
	public function testNumbersPassThroughUntouched($value)
	{
		// Amounts arrive from DECIMAL columns as strings and a negative one starts with a
		// trigger character. Prefixing those would turn every amount into text and break
		// the totals in the invoice exports.
		$this->assertSame($value, $this->normalize([$value])[0]);
	}

	/**
	 * @return array
	 */
	public function numberProvider()
	{
		return [
			'a negative amount' => ['-19.04'],
			'a positive amount' => ['19.04'],
			'signed' => ['+19.04'],
			'an integer' => ['7'],
		];
	}

	public function testNullAndNumericTypesSurvive()
	{
		$row = $this->normalize([null, 42, 4.2, true]);

		$this->assertNull($row[0]);
		$this->assertSame(42, $row[1]);
		$this->assertSame(4.2, $row[2]);
		$this->assertTrue($row[3]);
	}

	public function testDatesAreStillWrittenAsReadableText()
	{
		$row = $this->normalize([new \DateTimeImmutable('2026-07-01 10:00:00')]);

		$this->assertSame('2026-07-01 10:00:00', $row[0]);
	}
}
