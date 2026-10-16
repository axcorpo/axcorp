<?php

namespace common\rbac;

use common\helpers\UuidHelper;

/**
 * RBAC DbManager for `auth_assignment.user_id` stored as BINARY(16) with a foreign
 * key to `user.id`.
 *
 * The application identity keeps exposing the canonical UUID string
 * (`User::getId()`), so every method that receives a user id converts it to the raw
 * 16 bytes before querying, and every returned `Assignment::$userId` (or user id
 * list) is converted back to the canonical string. Non-UUID ids (e.g. `__guest`)
 * pass through unchanged.
 */
class DbManager extends \yii\rbac\DbManager
{
	/**
	 * @var bool|null Whether `auth_assignment.user_id` is a BINARY(16) column.
	 */
	private $_hasBinaryUserId;

	/**
	 * Checks the actual column type, so the manager also works against databases whose
	 * `auth_assignment.user_id` is still a VARCHAR holding the UUID string (tenant
	 * databases receive the conversion migration gradually).
	 *
	 * @return bool
	 */
	protected function hasBinaryUserId()
	{
		if ($this->_hasBinaryUserId === null) {
			$column = null;
			$table = $this->db->getTableSchema($this->assignmentTable);
			if ($table !== null) {
				$column = $table->getColumn('user_id');
			}
			$this->_hasBinaryUserId = $column !== null && $column->type === 'binary' && (int) $column->size === 16;
		}
		return $this->_hasBinaryUserId;
	}

	/**
	 * Converts a canonical UUID string to the BINARY(16) database representation.
	 *
	 * @param string|int $userId
	 * @return string|int
	 */
	protected function normalizeUserId($userId)
	{
		if ($this->hasBinaryUserId() && is_string($userId) && UuidHelper::isValid($userId)) {
			return UuidHelper::toBytes($userId);
		}
		return $userId;
	}

	/**
	 * Converts a BINARY(16) user id back to the canonical UUID string.
	 *
	 * @param mixed $userId
	 * @return mixed
	 */
	protected function denormalizeUserId($userId)
	{
		if (UuidHelper::isBytes($userId)) {
			return UuidHelper::fromBytes($userId);
		}
		return $userId;
	}

	/**
	 * @inheritdoc
	 */
	public function getAssignments($userId)
	{
		$assignments = parent::getAssignments($this->normalizeUserId($userId));
		foreach ($assignments as $assignment) {
			$assignment->userId = $this->denormalizeUserId($assignment->userId);
		}
		return $assignments;
	}

	/**
	 * @inheritdoc
	 */
	public function getAssignment($roleName, $userId)
	{
		$assignment = parent::getAssignment($roleName, $this->normalizeUserId($userId));
		if ($assignment !== null) {
			$assignment->userId = $this->denormalizeUserId($assignment->userId);
		}
		return $assignment;
	}

	/**
	 * @inheritdoc
	 */
	public function getRolesByUser($userId)
	{
		return parent::getRolesByUser($this->normalizeUserId($userId));
	}

	/**
	 * @inheritdoc
	 */
	public function getPermissionsByUser($userId)
	{
		return parent::getPermissionsByUser($this->normalizeUserId($userId));
	}

	/**
	 * @inheritdoc
	 */
	public function assign($role, $userId)
	{
		$assignment = parent::assign($role, $this->normalizeUserId($userId));
		$assignment->userId = $this->denormalizeUserId($assignment->userId);
		return $assignment;
	}

	/**
	 * @inheritdoc
	 */
	public function revoke($role, $userId)
	{
		return parent::revoke($role, $this->normalizeUserId($userId));
	}

	/**
	 * @inheritdoc
	 */
	public function revokeAll($userId)
	{
		return parent::revokeAll($this->normalizeUserId($userId));
	}

	/**
	 * @inheritdoc
	 */
	public function getUserIdsByRole($roleName)
	{
		return array_map([$this, 'denormalizeUserId'], parent::getUserIdsByRole($roleName));
	}
}
