<?php

namespace tests\unit;

use backend\modules\workspace\models\WorkspaceForm;
use common\helpers\UuidHelper;
use PHPUnit\Framework\TestCase;
use Yii;

/**
 * The workspace `code` at creation: prefilled with a generated value, editable only
 * then, and validated as a MySQL identifier (the code names the tenant database).
 */
class WorkspaceFormCodeTest extends TestCase
{
	/**
	 * @var \yii\db\Transaction
	 */
	private $transaction;

	protected function setUp(): void
	{
		parent::setUp();
		$this->transaction = Yii::$app->db->beginTransaction();
	}

	protected function tearDown(): void
	{
		$this->transaction->rollBack();
		parent::tearDown();
	}

	public function testNewFormPrefillsAValidCode()
	{
		$form = new WorkspaceForm();

		$this->assertNotEmpty($form->code);
		$this->assertMatchesRegularExpression('/^[a-zA-Z][a-zA-Z0-9_]{2,63}$/', $form->code);
		$this->assertTrue($form->validate(['code']), print_r($form->errors, true));
	}

	public function testTwoNewFormsGetDifferentCodes()
	{
		$this->assertNotEquals((new WorkspaceForm())->code, (new WorkspaceForm())->code);
	}

	public function testValidIdentifierCodesAreAccepted()
	{
		foreach (['automarket', 'My_Workspace3', 'abc'] as $code) {
			$form = new WorkspaceForm();
			$form->code = $code;
			$this->assertTrue($form->validate(['code']), "'{$code}' should be accepted: " . print_r($form->errors, true));
		}
	}

	public function testInvalidIdentifierCodesAreRejected()
	{
		foreach (['a b', '1abc', 'ab', 'no-dashes', 'ăîșț', str_repeat('x', 65)] as $code) {
			$form = new WorkspaceForm();
			$form->code = $code;
			$this->assertFalse($form->validate(['code']), "'{$code}' should be rejected");
		}
	}

	public function testDuplicateCodeIsRejected()
	{
		Yii::$app->db->createCommand()->insert('workspace', [
			'id' => UuidHelper::uuid7Bytes(),
			'code' => 'takencode',
			'url' => 'taken.ro',
			'status' => 1,
			'deleted' => 0,
		])->execute();

		$form = new WorkspaceForm();
		$form->code = 'takencode';

		$this->assertFalse($form->validate(['code']));
		$this->assertArrayHasKey('code', $form->errors);
	}

	public function testExistingRecordKeepsItsStoredCodeAfterFind()
	{
		Yii::$app->db->createCommand()->insert('workspace', [
			'id' => UuidHelper::uuid7Bytes(),
			'code' => 'storedcode',
			'url' => 'stored.ro',
			'status' => 1,
			'deleted' => 0,
		])->execute();

		$form = WorkspaceForm::findOne(['code' => 'storedcode']);

		// init() must never overwrite an existing record's code with a generated one
		$this->assertSame('storedcode', $form->code);
	}
}
