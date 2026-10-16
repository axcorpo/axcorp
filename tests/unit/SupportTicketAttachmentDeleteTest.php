<?php

namespace tests\unit;

use common\helpers\UuidHelper;
use common\models\User;
use frontend\modules\account\controllers\SupportTicketController;
use tests\WebTestCase;
use Yii;
use yii\helpers\FileHelper;

/**
 * Deleting a support-ticket attachment from the customer account area.
 *
 * The posted `key` was joined into the upload path as-is and unlinked, so any customer
 * owning one ticket could delete any file the web server can write ("../../..."). Only a
 * name the ticket itself lists may be removed now.
 *
 * The action is called directly rather than through runControllerAction(): the account
 * module's beforeAction() needs the session, which a CLI run does not have.
 */
class SupportTicketAttachmentDeleteTest extends WebTestCase
{
	/**
	 * @var string
	 */
	private $ticketDir;

	/**
	 * @var string A file outside the ticket's directory.
	 */
	private $victim;

	/**
	 * @var string raw bytes
	 */
	private $ticketId;

	protected function setUp(): void
	{
		parent::setUp();

		$userId = $this->insertRow('user', [
			'email' => 'owner@example.test',
			'first_name' => 'Test',
			'last_name' => 'User',
			'status' => User::STATUS_ACTIVE,
			'deleted' => User::NO,
		]);
		$this->ticketId = $this->insertRow('support_ticket', [
			'user_id' => $userId,
			'created_by' => $userId,
			'content' => 'Help',
			'attachment' => serialize(['listed.txt']),
			'deleted' => 0,
		]);
		Yii::$app->user->setIdentity(User::findOne(['id' => $userId]));

		$this->ticketDir = Yii::getAlias('@uploads/support-ticket/' . UuidHelper::fromBytes($this->ticketId));
		FileHelper::createDirectory($this->ticketDir);
		file_put_contents($this->ticketDir . '/listed.txt', 'x');
		$this->victim = Yii::getAlias('@uploads/support-ticket/victim-' . bin2hex(random_bytes(4)) . '.txt');
		file_put_contents($this->victim, 'x');

		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
		$_SERVER['REQUEST_METHOD'] = 'POST';
	}

	protected function tearDown(): void
	{
		FileHelper::removeDirectory($this->ticketDir);
		@unlink($this->victim);
		unset($_SERVER['HTTP_X_REQUESTED_WITH']);
		parent::tearDown();
	}

	/**
	 * @param string $key
	 * @return array
	 */
	private function deleteFile($key)
	{
		Yii::$app->request->setBodyParams(['key' => $key]);
		$controller = new SupportTicketController('support-ticket', Yii::$app);

		return $this->jsonPayload($controller->actionDeleteFile(UuidHelper::fromBytes($this->ticketId)));
	}

	public function testATraversingKeyDeletesNothing()
	{
		$result = $this->deleteFile('../' . basename($this->victim));

		$this->assertFalse($result['success']);
		$this->assertFileExists($this->victim);
	}

	public function testAFileTheTicketDoesNotListIsRefused()
	{
		file_put_contents($this->ticketDir . '/unlisted.txt', 'x');

		$result = $this->deleteFile('unlisted.txt');

		$this->assertFalse($result['success']);
		$this->assertFileExists($this->ticketDir . '/unlisted.txt');
	}

	public function testAListedAttachmentIsDeleted()
	{
		$result = $this->deleteFile('listed.txt');

		$this->assertTrue($result['success']);
		$this->assertFileDoesNotExist($this->ticketDir . '/listed.txt');
		$stored = Yii::$app->db->createCommand('SELECT attachment FROM support_ticket WHERE id = :id', [':id' => $this->ticketId])->queryScalar();
		$this->assertSame([], array_values(unserialize($stored)));
	}
}
