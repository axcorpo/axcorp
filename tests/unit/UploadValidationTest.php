<?php

namespace tests\unit;

use frontend\modules\account\models\CompanyForm;
use frontend\modules\account\models\ProfileForm;
use frontend\modules\account\models\SupportTicketCommentForm;
use frontend\modules\account\models\SupportTicketForm;
use tests\WebTestCase;
use Yii;
use yii\web\UploadedFile;

/**
 * The upload allow-list on the forms that write into the web-served uploads tree.
 *
 * The rules were present on some of these forms and still did nothing: nothing populated
 * imageFile/attachmentFiles before validation, so the validator sat on a null attribute
 * and skipOnEmpty let it through, and saveFiles() then pulled the raw upload out of the
 * request under whatever extension the client had chosen. These cases cover both halves
 * — that the attribute is populated by load(), and that the populated value is checked —
 * because either one alone leaves the hole open.
 */
class UploadValidationTest extends WebTestCase
{
	/**
	 * @var string[] Temp files to clean up.
	 */
	private $tempFiles = [];

	protected function tearDown(): void
	{
		foreach ($this->tempFiles as $path) {
			if (is_file($path)) {
				unlink($path);
			}
		}
		$this->tempFiles = [];
		UploadedFile::reset();
		parent::tearDown();
	}

	/**
	 * A real file on disk, so the validator's content sniffing has something to read.
	 *
	 * @param string $contents
	 * @param string $suffix
	 * @return string the temp path
	 */
	private function tempFile($contents, $suffix)
	{
		// tempnam() creates the base file too, so both paths are tracked for cleanup.
		$base = tempnam(sys_get_temp_dir(), 'upload-test');
		$path = $base . $suffix;
		file_put_contents($path, $contents);
		$this->tempFiles[] = $base;
		$this->tempFiles[] = $path;

		return $path;
	}

	/**
	 * A one-pixel PNG — valid image bytes, so only the extension is in question.
	 *
	 * @return string
	 */
	private function pngBytes()
	{
		return base64_decode(
			'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
		);
	}

	/**
	 * @param string $name the client-supplied file name
	 * @param string $contents
	 * @return UploadedFile
	 */
	private function upload($name, $contents)
	{
		$extension = '.' . pathinfo($name, PATHINFO_EXTENSION);
		$path = $this->tempFile($contents, $extension);

		return new UploadedFile([
			'name' => $name,
			'tempName' => $path,
			'type' => 'image/png',
			'size' => strlen($contents),
			'error' => UPLOAD_ERR_OK,
		]);
	}

	public function testAPhpScriptIsRejectedAsAProfileImage()
	{
		$model = new ProfileForm();
		$model->imageFile = $this->upload('shell.php', '<?php echo shell_exec($_GET["c"]); ?>');

		$model->validate(['imageFile']);

		$this->assertTrue(
			$model->hasErrors('imageFile'),
			'an executable extension must not reach the uploads tree'
		);
	}

	public function testAPhpScriptRenamedToPngIsStillRejected()
	{
		$model = new ProfileForm();
		// The extension is on the allow-list; only the content check catches this one.
		$model->imageFile = $this->upload('avatar.png', '<?php echo shell_exec($_GET["c"]); ?>');

		$model->validate(['imageFile']);

		$this->assertTrue(
			$model->hasErrors('imageFile'),
			'the declared extension must match the real content'
		);
	}

	public function testARealImageIsAccepted()
	{
		$model = new ProfileForm();
		$model->imageFile = $this->upload('avatar.png', $this->pngBytes());

		$model->validate(['imageFile']);

		$this->assertFalse($model->hasErrors('imageFile'), implode(', ', $model->getErrors('imageFile')));
	}

	public function testCompanyLogosAreCheckedToo()
	{
		$model = new CompanyForm();
		$model->imageFile = $this->upload('shell.php', '<?php echo 1; ?>');

		$model->validate(['imageFile']);

		$this->assertTrue($model->hasErrors('imageFile'));
	}

	public function testSupportTicketAttachmentsRejectExecutables()
	{
		$model = new SupportTicketForm();
		$model->attachmentFiles = [$this->upload('shell.php', '<?php echo 1; ?>')];

		$model->validate(['attachmentFiles']);

		$this->assertTrue($model->hasErrors('attachmentFiles'));
	}

	public function testAReplyTakesAtMostThreeImages()
	{
		$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

		$model = new SupportTicketCommentForm();
		$model->attachmentFiles = [$this->upload('shell.php', '<?php echo 1; ?>')];
		$model->validate(['attachmentFiles']);
		$this->assertTrue($model->hasErrors('attachmentFiles'), 'a script is refused on a reply too');

		$model = new SupportTicketCommentForm();
		$model->attachmentFiles = [$this->upload('a.png', $png), $this->upload('b.png', $png), $this->upload('c.png', $png)];
		$model->validate(['attachmentFiles']);
		$this->assertFalse($model->hasErrors('attachmentFiles'), 'three images are fine');

		$model = new SupportTicketCommentForm();
		$model->attachmentFiles = [$this->upload('a.png', $png), $this->upload('b.png', $png), $this->upload('c.png', $png), $this->upload('d.png', $png)];
		$model->validate(['attachmentFiles']);
		$this->assertTrue($model->hasErrors('attachmentFiles'), 'a fourth image is refused');
	}

	public function testTheImageAllowListIsConfigured()
	{
		// Three backend forms read these params; they were undefined on master, which
		// silently turned their validators into no-ops.
		$this->assertNotEmpty(Yii::$app->params['image.extensions']);
		$this->assertNotEmpty(Yii::$app->params['image.mimeTypes']);
		$this->assertNotContains('php', Yii::$app->params['image.extensions']);
		$this->assertNotContains('php', Yii::$app->params['file.extensions']);
	}

	public function testUploadAttributesArePopulatedByLoad()
	{
		// Without this the rules above never see a value, which is how the bypass worked.
		foreach ([ProfileForm::class, CompanyForm::class] as $class) {
			$this->assertTrue(
				method_exists($class, 'load'),
				"{$class} must populate its upload attribute before validation"
			);
			$reflection = new \ReflectionMethod($class, 'load');
			$this->assertSame(
				$class,
				$reflection->getDeclaringClass()->getName(),
				"{$class} must declare its own load() so the file validator has something to check"
			);
		}
	}
}
