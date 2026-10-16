<?php

namespace common\db;

/**
 * DB command with a binary-safe query cache key.
 *
 * The stock {@see \yii\db\Command::getCacheKey()} runs the bound params through
 * `json_encode()`, which returns `false` for any value that is not valid UTF-8 —
 * such as UUID v7 primary keys stored as BINARY(16). With binary params, every
 * query sharing the same SQL text would collapse onto a single cache key and the
 * query cache would serve the wrong result set (observed as infinite recursion in
 * category/menu tree builders after the UUID migration).
 *
 * `serialize()` is binary-safe and produces a distinct key per distinct params.
 */
class Command extends \yii\db\Command
{
	/**
	 * @inheritdoc
	 */
	protected function getCacheKey($method, $fetchMode, $rawSql)
	{
		$params = $this->params;
		ksort($params);
		return [
			__CLASS__,
			$method,
			$fetchMode,
			$this->db->dsn,
			$this->db->username,
			$this->getSql(),
			serialize($params),
		];
	}
}
