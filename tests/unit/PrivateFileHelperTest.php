<?php

namespace tests\unit;

use common\helpers\PrivateFileHelper;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\base\InvalidArgumentException;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

/**
 * The private document storage (affiliate invoices, payment proofs): only PDF/JPG/PNG
 * whose content matches, random names, and no way out of the base directory.
 */
class PrivateFileHelperTest extends TestCase
{
	/**
	 * @var string
	 */
	private $storage;

	/**
	 * @var string[]
	 */
	private $tempFiles = [];

	/**
	 * @var mixed
	 */
	private $previousPath;

	protected function setUp(): void
	{
		parent::setUp();
		$this->previousPath = Yii::$app->params['privateUploads.path'] ?? null;
		$this->storage = sys_get_temp_dir() . '/private-file-test-' . bin2hex(random_bytes(4));
		Yii::$app->params['privateUploads.path'] = $this->storage;
	}

	protected function tearDown(): void
	{
		Yii::$app->params['privateUploads.path'] = $this->previousPath;
		FileHelper::removeDirectory($this->storage);
		foreach ($this->tempFiles as $path) {
			@unlink($path);
		}
		parent::tearDown();
	}

	/**
	 * @param string $name client file name
	 * @param string $contents
	 * @return UploadedFile
	 */
	private function upload($name, $contents)
	{
		$path = tempnam(sys_get_temp_dir(), 'private');
		file_put_contents($path, $contents);
		$this->tempFiles[] = $path;

		return new UploadedFile([
			'name' => $name,
			'tempName' => $path,
			'type' => 'application/pdf',
			'size' => strlen($contents),
			'error' => UPLOAD_ERR_OK,
		]);
	}

	/**
	 * @return string a minimal PDF
	 */
	private function pdfBytes()
	{
		return "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
	}

	/**
	 * @return string a one-pixel PNG
	 */
	private function pngBytes()
	{
		return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
	}

	/**
	 * @dataProvider badFileProvider
	 * @param string $name
	 * @param string $contents
	 */
	public function testBadFilesAreRejected($name, $contents)
	{
		$file = $this->upload($name, $contents);

		$this->assertNotEmpty(PrivateFileHelper::validate($file));
		$this->expectException(InvalidArgumentException::class);
		PrivateFileHelper::save($file, 'test', false);
	}

	/**
	 * @return array
	 */
	public function badFileProvider()
	{
		return [
			'php script' => ['invoice.php', '<?php echo shell_exec($_GET["c"]); ?>'],
			'php renamed to pdf' => ['invoice.pdf', '<?php echo shell_exec($_GET["c"]); ?>'],
			'html' => ['invoice.html', '<html><script>alert(1)</script></html>'],
			'svg' => ['invoice.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
			'double extension' => ['invoice.pdf.php', '%PDF-1.4'],
			'office document' => ['invoice.docx', 'PK'],
		];
	}

	public function testAValidPdfIsStoredUnderARandomNameAndResolved()
	{
		$name = PrivateFileHelper::save($this->upload('My Invoice.PDF', $this->pdfBytes()), 'affiliate-payout/abc', false);

		$this->assertMatchesRegularExpression('/^[a-f0-9]{32}\.pdf$/', $name);
		$path = PrivateFileHelper::resolve('affiliate-payout/abc', $name);
		$this->assertNotNull($path);
		$this->assertStringStartsWith(FileHelper::normalizePath($this->storage), $path);
		$this->assertEquals($this->pdfBytes(), file_get_contents($path));

		$this->assertTrue(PrivateFileHelper::delete('affiliate-payout/abc', $name));
		$this->assertNull(PrivateFileHelper::resolve('affiliate-payout/abc', $name));
	}

	public function testAPngIsAccepted()
	{
		$name = PrivateFileHelper::save($this->upload('scan.png', $this->pngBytes()), 'proofs', false);

		$this->assertStringEndsWith('.png', $name);
	}

	public function testTraversalIsRefused()
	{
		$name = PrivateFileHelper::save($this->upload('a.pdf', $this->pdfBytes()), 'x', false);

		$this->assertNull(PrivateFileHelper::resolve('x/../x', $name));
		$this->assertNull(PrivateFileHelper::resolve('..', $name));
		$this->assertNull(PrivateFileHelper::resolve('x', '../' . $name));
		$this->assertNull(PrivateFileHelper::resolve('x', 'secret.php'));
		$this->expectException(InvalidArgumentException::class);
		PrivateFileHelper::save($this->upload('a.pdf', $this->pdfBytes()), '../outside', false);
	}

	public function testTheDefaultBaseIsOutsideThePublicUploads()
	{
		Yii::$app->params['privateUploads.path'] = null;

		$base = PrivateFileHelper::getBasePath();

		$this->assertEquals(FileHelper::normalizePath(dirname(__DIR__, 2) . '/private_uploads'), $base);
		$this->assertStringNotContainsString(FileHelper::normalizePath(Yii::getAlias('@uploads')), $base);
		$this->assertFileExists($base . '/.htaccess');
	}
}
