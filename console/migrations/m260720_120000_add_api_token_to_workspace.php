<?php

use common\db\Migration;

/**
 * Adds `api_token` to the workspace table — the per-workspace secret a tenant sends (as a
 * Bearer token) when it publishes announcements to the master hub. Master resolves the token
 * back to the workspace. Nullable (existing workspaces get one via backfill) and unique.
 */
class m260720_120000_add_api_token_to_workspace extends Migration
{
	public function up()
	{
		$table = $this->db->getTableSchema('{{%workspace}}', true);
		if ($table !== null && !isset($table->columns['api_token'])) {
			$this->addColumn('{{%workspace}}', 'api_token', $this->string(255)->null()->after('code'));
			$this->createIndex('workspace_api_token_UNIQUE', '{{%workspace}}', 'api_token', true);
		}
	}

	public function down()
	{
		$table = $this->db->getTableSchema('{{%workspace}}', true);
		if ($table !== null && isset($table->columns['api_token'])) {
			$this->dropIndex('workspace_api_token_UNIQUE', '{{%workspace}}');
			$this->dropColumn('{{%workspace}}', 'api_token');
		}
	}
}
