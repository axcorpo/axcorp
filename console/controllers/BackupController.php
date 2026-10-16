<?php
namespace console\controllers;

use DateTime;
use tws\helpers\ArchiveHelper;
use tws\helpers\DbHelper;
use common\models\Backup;
use common\models\EventLog;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\FileHelper;

class BackupController extends Controller
{
	// TODO 1: Check if backup process blocks access to the app for long time running
	// TODO 2: Consider run backup process as a new thread!
	/**
	 * Run backup.
	 */
	public function actionRun()
	{
		$dbTransaction = Yii::$app->db->beginTransaction();
		try {
			$backup = new Backup();
			$backup->status = Backup::STATUS_RUNNING;
			$backup->save();

			$dbFile = DbHelper::dump(Yii::$app->db, Yii::getAlias('@runtime/db.sql'), [
				'--ignore-table' => [
					preg_replace('/\W/', '', Backup::tableName()),
					preg_replace('/\W/', '', EventLog::tableName()),
				],
			]);
			$backupFile = ArchiveHelper::pack([
				$dbFile,
				Yii::getAlias("@uploads"),
			], Yii::getAlias("@backups/{$backup->id}.zip"));
			FileHelper::unlink($dbFile);

			if (is_file($backupFile)) {
				$backup->status = Backup::STATUS_COMPLETE;
				$backup->file_size = filesize($backupFile);
				$backup->save();

				$dbTransaction->commit();
				$this->stdout('[' . date('Y-m-d H:i:s') . '] Backup ran successfully.');
				return ExitCode::OK;
			} else {
				throw new \Exception();
			}
		} catch (\Exception $e) {
			$dbTransaction->rollBack();
			$this->stdout('[' . date('Y-m-d H:i:s') . '] Backup failed.');
			return ExitCode::UNSPECIFIED_ERROR;
		}
	}

    /**
     * Remove backups older than one month.
     */
    public function actionRemove()
    {
        try {
            $backups = Backup::findAll(['status' => Backup::STATUS_COMPLETE, 'deleted' => Backup::NO]);

            $expiration = 30*24*60*60;
            $currentDate = new DateTime();
            $count = 0;

            foreach ($backups as $backup) {
                $expirationDate = (new DateTime($backup->created_at))->modify("+{$expiration} seconds");
                if ($currentDate >= $expirationDate) {
                    $file = Yii::getAlias("@backups/{$backup->id}.zip");
                    if (is_file($file)) {
                        unlink($file);
                    }
                    $backup->delete(true);
                    $count++;
                }
            }
            $this->stdout("[" . date("Y-m-d H:i:s") . "] Deleted {$count} backup(s).\n");

            return $count;
        } catch (\Exception $e) {
            $this->stderr($e->getMessage());
            return false;
        } catch (\Throwable $e) {
            $this->stderr($e->getMessage());
            return false;
        }
    }
}
