<?php

namespace console\controllers;

use common\models\Workspace;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Query;

/**
 * Brings every copy of a user's auth_key (workspace_has_user and the user row inside each
 * workspace database) back in line with the master user row.
 *
 * The key is the "remember me" cookie check and the API bearer token, and each copy is
 * checked by its own application, so a copy left behind keeps an old key working after it
 * was rotated on master. User::syncAuthKey() keeps them together from now on; run this
 * once to repair the accounts that drifted apart before that (e.g. a password changed from
 * the admin profile or the API, which rotated only the master row).
 *
 * Usage:
 *   php yii auth-key/sync        # apply the changes
 *   php yii auth-key/sync 1      # dry-run: only report what would change
 */
class AuthKeyController extends Controller
{
	/**
	 * Copies the master auth_key to workspace_has_user and to every workspace database.
	 *
	 * @param int $dryRun When 1, only reports the copies that differ.
	 * @return int
	 */
	public function actionSync($dryRun = 0)
	{
		$dryRun = (bool) $dryRun;
		$exitCode = ExitCode::OK;
		$totalLinks = 0;
		$totalWorkspaceRows = 0;

		/** @var Workspace $workspace */
		foreach (Workspace::find()->each(50) as $workspace) {
			// Raw rows, keyed by the binary user id, so nothing is converted on the way.
			$rows = (new Query())
				->select(['u.id', 'u.auth_key', 'whu_auth_key' => 'whu.auth_key'])
				->from(['whu' => '{{%workspace_has_user}}'])
				->innerJoin(['u' => '{{%user}}'], 'u.id = whu.user_id')
				->where(['whu.workspace_id' => $workspace->getAttribute('id')])
				->all();
			if (!$rows) {
				continue;
			}

			// workspace_has_user rows that differ from the master key.
			$links = 0;
			$masterKeys = [];
			foreach ($rows as $row) {
				$masterKeys[$row['id']] = $row['auth_key'];
				if ($row['auth_key'] !== null && $row['whu_auth_key'] !== $row['auth_key']) {
					$links++;
					if (!$dryRun) {
						Workspace::getDb()->createCommand()->update('{{%workspace_has_user}}',
							['auth_key' => $row['auth_key']],
							['workspace_id' => $workspace->getAttribute('id'), 'user_id' => $row['id']]
						)->execute();
					}
				}
			}

			// User rows inside the workspace database that differ from the master key.
			$workspaceRows = 0;
			try {
				$workspaceDb = $workspace->getWorkspaceDb();
				$workspaceKeys = (new Query())
					->select(['id', 'auth_key'])
					->from('{{%user}}')
					->where(['id' => array_keys($masterKeys)])
					->all($workspaceDb);
				foreach ($workspaceKeys as $row) {
					$masterKey = $masterKeys[$row['id']];
					if ($masterKey !== null && $row['auth_key'] !== $masterKey) {
						$workspaceRows++;
						if (!$dryRun) {
							$workspaceDb->createCommand()->update('{{%user}}', ['auth_key' => $masterKey], ['id' => $row['id']])->execute();
						}
					}
				}
			} catch (\Exception $e) {
				$this->stderr("Workspace {$workspace->code}: cannot reach its database ({$e->getMessage()})\n");
				$exitCode = ExitCode::UNSPECIFIED_ERROR;
			}

			if ($links || $workspaceRows) {
				$this->stdout("Workspace {$workspace->code}: {$links} workspace_has_user row(s), {$workspaceRows} workspace user row(s) " . ($dryRun ? 'differ' : 'fixed') . "\n");
			}
			$totalLinks += $links;
			$totalWorkspaceRows += $workspaceRows;
		}

		$this->stdout(($dryRun ? '[dry-run] ' : '') . "Total: {$totalLinks} workspace_has_user row(s), {$totalWorkspaceRows} workspace user row(s) " . ($dryRun ? 'differ' : 'fixed') . "\n");
		return $exitCode;
	}
}
