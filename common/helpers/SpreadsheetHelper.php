<?php

namespace common\helpers;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Options as CsvReaderOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Yii;
use yii\helpers\FileHelper;

/**
 * Single place where the application writes spreadsheets.
 *
 * Every export in the app produces the same shape — a header row plus rows of
 * scalars — so the writer lives here instead of being copied into each
 * controller that offers a download. That also keeps the openspout API in one
 * file: the project runs openspout 4, whose API is nothing like the abandoned
 * box/spout 2 the exports were originally written against (`WriterFactory`,
 * `StyleBuilder`, `addRowWithStyle` are all gone).
 */
class SpreadsheetHelper
{
	const TYPE_XLSX = 'xlsx';
	const TYPE_CSV = 'csv';

	/**
	 * Writes a table to the given absolute file path.
	 *
	 * @param string $type self::TYPE_XLSX or self::TYPE_CSV
	 * @param string $path absolute path of the file to create
	 * @param string[] $header first row; written bold for XLSX unless disabled
	 * @param array[] $rows data rows, in the same column order as $header
	 * @param array $options
	 *   - bool `boldHeader` (XLSX, default true)
	 *   - bool `shouldUseInlineStrings` (XLSX, default true)
	 *   - bool `shouldCreateNewSheetsAutomatically` (XLSX, default true)
	 *   - string `fieldDelimiter` (CSV, default ',')
	 *   - string `fieldEnclosure` (CSV, default '"')
	 *   - bool `shouldAddBom` (CSV, default true — Excel needs it for UTF-8)
	 * @return string the path it wrote to
	 * @throws \OpenSpout\Common\Exception\IOException
	 */
	public static function write($type, $path, array $header, array $rows, array $options = [])
	{
		$writer = static::createWriter($type, $options);
		$writer->openToFile($path);

		if ($header) {
			$style = ($type === self::TYPE_XLSX && ($options['boldHeader'] ?? true))
				? (new Style())->setFontBold()
				: null;
			$writer->addRow(Row::fromValues(static::normalizeRow($header), $style));
		}
		foreach ($rows as $row) {
			$writer->addRow(Row::fromValues(static::normalizeRow($row)));
		}
		$writer->close();

		return $path;
	}

	/**
	 * Writes an XLSX into the current user's export folder and returns its URL —
	 * the shape every "download this grid" button needs.
	 *
	 * Only one file is kept per user: the previous export is removed, so the
	 * uploads folder does not grow with every download.
	 *
	 * @param string $title becomes part of the file name
	 * @param string[] $header
	 * @param array[] $rows
	 * @param array $options see [[write()]]
	 * @return string the file URL
	 * @throws \yii\base\Exception
	 */
	public static function writeXlsx($title, array $header, array $rows, array $options = [])
	{
		list($filePath, $fileName) = static::prepareUserFile($title, self::TYPE_XLSX);
		static::write(self::TYPE_XLSX, Yii::getAlias("@uploads/{$filePath}/{$fileName}"), $header, $rows, $options);

		return UploadHelper::getFileUrl("{$filePath}/{$fileName}");
	}

	/**
	 * Same as [[writeXlsx()]], for a comma-separated file.
	 *
	 * @param string $title
	 * @param string[] $header
	 * @param array[] $rows
	 * @param array $options see [[write()]]
	 * @return string the file URL
	 * @throws \yii\base\Exception
	 */
	public static function writeCsv($title, array $header, array $rows, array $options = [])
	{
		list($filePath, $fileName) = static::prepareUserFile($title, self::TYPE_CSV);
		static::write(self::TYPE_CSV, Yii::getAlias("@uploads/{$filePath}/{$fileName}"), $header, $rows, $options);

		return UploadHelper::getFileUrl("{$filePath}/{$fileName}");
	}

	/**
	 * Builds the file name of an export and makes sure its folder exists and holds
	 * nothing else.
	 *
	 * @param string $title
	 * @param string $extension
	 * @return array{0: string, 1: string} [folder relative to @uploads, file name]
	 * @throws \yii\base\Exception
	 */
	public static function prepareUserFile($title, $extension)
	{
		$filePath = 'export/' . Yii::$app->user->id;
		$fileName = Inflector::slug(implode('-', [$title, Yii::$app->name, date('d.m.Y')])) . '.' . $extension;
		FileHelper::removeDirectory(Yii::getAlias("@uploads/{$filePath}"));
		UploadHelper::ensureDirectoryTree($filePath);

		return [$filePath, $fileName];
	}

	/**
	 * Reader for an existing file, picked by extension.
	 *
	 * The caller still opens and closes it — the import wizard keeps a reader open
	 * across several steps. Sheet and row iterators are 1-based, exactly like the
	 * box/spout 2 API this replaces, so sheet numbers and header row indices
	 * already stored in `import_sheet` keep pointing at the same place.
	 *
	 * @param string $path
	 * @param array $options
	 *   - string `fieldDelimiter` (CSV, default ',')
	 *   - string `fieldEnclosure` (CSV, default '"')
	 *   - bool `shouldPreserveEmptyRows` (CSV, default false)
	 * @return ReaderInterface
	 */
	public static function createReader($path, array $options = [])
	{
		switch (mb_strtolower((string) pathinfo($path, PATHINFO_EXTENSION))) {
			case self::TYPE_CSV:
				$csv = new CsvReaderOptions();
				$csv->FIELD_DELIMITER = (string) ($options['fieldDelimiter'] ?? ',');
				$csv->FIELD_ENCLOSURE = (string) ($options['fieldEnclosure'] ?? '"');
				$csv->SHOULD_PRESERVE_EMPTY_ROWS = (bool) ($options['shouldPreserveEmptyRows'] ?? false);

				return new CsvReader($csv);
			case 'ods':
				return new OdsReader();
			default:
				// Necunoscut inseamna XLSX: uploadul e limitat oricum la aceste trei tipuri.
				return new XlsxReader();
		}
	}

	/**
	 * The cell values of one row, as scalars.
	 *
	 * openspout hands over a Row object (box/spout 2 handed over a plain array) and
	 * date cells arrive as DateTimeInterface, which would blow up the string
	 * handling every importer does on the raw row. Both are flattened here.
	 *
	 * @param mixed $row a Row, or an array when a caller already flattened it
	 * @return array
	 */
	public static function rowValues($row)
	{
		$values = is_array($row) ? $row : (is_object($row) && method_exists($row, 'toArray') ? $row->toArray() : []);

		return array_map(function ($value) {
			if ($value instanceof \DateTimeInterface) {
				return $value->format('Y-m-d H:i:s');
			}

			return is_scalar($value) || $value === null ? $value : '';
		}, $values);
	}

	/**
	 * @param string $type
	 * @param array $options
	 * @return WriterInterface
	 */
	protected static function createWriter($type, array $options)
	{
		if ($type === self::TYPE_CSV) {
			$csv = new CsvOptions();
			$csv->FIELD_DELIMITER = (string) ($options['fieldDelimiter'] ?? ',');
			$csv->FIELD_ENCLOSURE = (string) ($options['fieldEnclosure'] ?? '"');
			// Fara BOM, Excel citeste UTF-8 ca ANSI si strica diacriticele.
			$csv->SHOULD_ADD_BOM = (bool) ($options['shouldAddBom'] ?? true);

			return new CsvWriter($csv);
		}

		$xlsx = new XlsxOptions();
		$xlsx->SHOULD_USE_INLINE_STRINGS = (bool) ($options['shouldUseInlineStrings'] ?? true);
		$xlsx->SHOULD_CREATE_NEW_SHEETS_AUTOMATICALLY = (bool) ($options['shouldCreateNewSheetsAutomatically'] ?? true);

		return new XlsxWriter($xlsx);
	}

	/**
	 * openspout accepts only scalars, DateTimeInterface and null in a cell; an
	 * array or object coming from a formatter would throw deep inside the writer,
	 * so values are flattened here where the cause is still obvious. Dates become
	 * text, the same shape [[rowValues()]] returns when reading a file back.
	 *
	 * @param array $row
	 * @return array
	 */
	protected static function normalizeRow(array $row)
	{
		return array_map(function ($value) {
			// Datele se scriu ca text: fara un numFmt de data, openspout le-ar pune ca
			// numar serial Excel, iar la recitire ar iesi "46254.4375" in loc de data.
			if ($value instanceof \DateTimeInterface) {
				return $value->format('Y-m-d H:i:s');
			}
			if ($value === null || is_scalar($value)) {
				return static::defuseFormula($value);
			}
			if (is_object($value) && method_exists($value, '__toString')) {
				return static::defuseFormula((string) $value);
			}

			return static::defuseFormula(
				is_array($value) ? implode(', ', array_filter($value, 'is_scalar')) : ''
			);
		}, array_values($row));
	}

	/**
	 * Neutralises a value a spreadsheet would read as a formula.
	 *
	 * Every string in an export came from somebody typing it into the application, and a
	 * cell beginning `=`, `+`, `-` or `@` is evaluated when the recipient opens the file.
	 * That is how a client name becomes a link to somewhere else, or a lookup into the
	 * rest of the sheet. CSV is where this actually bites -- Excel and LibreOffice parse
	 * the text on open -- but the prefix is applied for both formats so the two cannot
	 * drift apart.
	 *
	 * A leading apostrophe is the conventional "this is text" marker and is dropped again
	 * on display, so the cell still reads as the value that was entered.
	 *
	 * Numbers and booleans are left alone: they are genuine scalars, not text that could
	 * be parsed as an expression.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	protected static function defuseFormula($value)
	{
		if (!is_string($value) || $value === '') {
			return $value;
		}

		// Leading whitespace still counts: a spreadsheet trims it before parsing, and so
		// would a check that did not.
		$trimmed = ltrim($value, " \t\n\r\0\x0B");
		if ($trimmed === '') {
			return $value;
		}

		// Amounts arrive from DECIMAL columns as strings, and a negative one starts with
		// a trigger character. Prefixing those would turn every "-19.04" into text and
		// break the totals in the invoice exports, so a value that is simply a number
		// passes through -- it cannot carry an expression.
		if (is_numeric($trimmed)) {
			return $value;
		}

		// The full-width variants are the same characters to a spreadsheet's parser.
		$triggers = ['=', '+', '-', '@', "\t", "\r", "\n", "\u{FF1D}", "\u{FF0B}", "\u{FF0D}", "\u{FF20}"];
		foreach ($triggers as $trigger) {
			if (mb_strpos($trimmed, $trigger) === 0) {
				return "'" . $value;
			}
		}

		return $value;
	}
}
