<?php

namespace console\controllers;

use common\models\Workspace;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Health checks over the installed workspaces' scheduler infrastructure.
 *
 * Each tenant's `schedule/run` cron stamps `console/runtime/schedule-heartbeat.txt`
 * and appends its tasks' output to `console/runtime/logs/scheduled-tasks.log`
 * (see the workspace app's ScheduleBootstrap). This command turns those artifacts
 * into an actionable report, so a dead cron or silently failing scheduled task is
 * noticed instead of rotting for months.
 *
 * Meant to run from master's own cron, e.g. every 15 minutes:
 *   php yii workspace-health/check
 */
class WorkspaceHealthController extends Controller
{
	/**
	 * @var int Minutes after which a missing/stale heartbeat is reported (the tenant
	 * cron runs every minute, so a few missed ticks already indicate trouble).
	 */
	public $heartbeatMaxAge = 15;

	/**
	 * @var int How many bytes from the end of each task log are scanned for errors.
	 */
	public $logTailBytes = 65536;

	/**
	 * @var string|null Recipient of the alert emails. Defaults to the `healthAlert.email`
	 * param; an empty value disables emailing (the report still prints and logs).
	 */
	public $alertEmail;

	/**
	 * @var int Minimum hours between two alert emails for the SAME problem set, so a
	 * 15-minute cron does not flood the inbox. A changed problem set always sends.
	 */
	public $alertRepeatHours = 6;

	/**
	 * @inheritdoc
	 */
	public function options($actionID)
	{
		return array_merge(parent::options($actionID), ['heartbeatMaxAge', 'logTailBytes', 'alertEmail', 'alertRepeatHours']);
	}

	/**
	 * Checks every active installed workspace and reports scheduler problems.
	 * Exits non-zero when any problem is found, so the master cron line itself can
	 * be wired to an alert (email on output / non-zero exit).
	 *
	 * @return int
	 */
	public function actionCheck()
	{
		$problems = [];
		$checked = 0;

		$workspaces = Workspace::find()
			->where([
				'status' => Workspace::STATUS_ACTIVE,
				'deleted' => Workspace::NO,
			])
			->all();

		foreach ($workspaces as $workspace) {
			$dirPath = $workspace->getDirectoryPath();
			// Skip workspaces that are not installed on this environment
			if (!$dirPath || !is_dir($dirPath)) {
				continue;
			}
			$checked++;

			$heartbeatFile = "{$dirPath}/console/runtime/schedule-heartbeat.txt";
			if (!is_file($heartbeatFile)) {
				$problems[] = "{$workspace->code}: no scheduler heartbeat found — the cron has never stamped {$heartbeatFile}.";
			} else {
				$ageMinutes = (time() - filemtime($heartbeatFile)) / 60;
				if ($ageMinutes > $this->heartbeatMaxAge) {
					$problems[] = sprintf(
						'%s: scheduler heartbeat is %d minutes old (limit %d) — the cron looks dead.',
						$workspace->code,
						(int) $ageMinutes,
						$this->heartbeatMaxAge
					);
				}
			}

			$taskLog = "{$dirPath}/console/runtime/logs/scheduled-tasks.log";
			foreach ($this->findRecentLogErrors($taskLog) as $line) {
				$problems[] = "{$workspace->code}: scheduled task error — {$line}";
			}
		}

		$this->stdout(sprintf("Checked %d installed workspace(s).\n", $checked));

		if (empty($problems)) {
			$this->stdout("OK: schedulers healthy, no task errors in the log tails.\n", Console::FG_GREEN);
			$this->notifyRecoveredIfNeeded();
			return ExitCode::OK;
		}

		foreach ($problems as $problem) {
			$this->stdout("PROBLEM: {$problem}\n", Console::FG_RED);
		}
		Yii::warning(['message' => 'Workspace scheduler problems found.', 'problems' => $problems], __METHOD__);
		$this->notifyProblems($problems);

		return ExitCode::UNSPECIFIED_ERROR;
	}

	/**
	 * Emails the problem report, throttled: a repeat of the same problem set is sent at
	 * most once every [[alertRepeatHours]], while a changed set always goes out. The last
	 * alert is tracked in a state file, which also drives the recovery notice.
	 *
	 * @param string[] $problems
	 */
	protected function notifyProblems(array $problems)
	{
		$email = $this->getAlertEmail();
		if (!$email) {
			return;
		}

		$hash = sha1(implode("\n", $problems));
		$state = $this->readAlertState();
		if ($state && $state['hash'] === $hash && (time() - $state['sentAt']) < $this->alertRepeatHours * 3600) {
			$this->stdout("Alert email throttled (same problems already reported).\n");
			return;
		}

		$body = "The workspace health check found problems:\n\n- " . implode("\n- ", $problems)
			. "\n\nChecked at " . date('Y-m-d H:i:s') . " on " . gethostname()
			. ".\nThis alert repeats every {$this->alertRepeatHours}h while the problems persist, and immediately when they change.";

		if ($this->sendAlertEmail($email, '[' . Yii::$app->name . '] Workspace health: ' . count($problems) . ' problem(s)', $body)) {
			$this->writeAlertState(['hash' => $hash, 'sentAt' => time()]);
			$this->stdout("Alert email sent to {$email}.\n");
		}
	}

	/**
	 * Sends a one-time all-clear notice when a previously alerted problem set is gone.
	 */
	protected function notifyRecoveredIfNeeded()
	{
		$email = $this->getAlertEmail();
		if (!$email || !$this->readAlertState()) {
			return;
		}

		if ($this->sendAlertEmail(
			$email,
			'[' . Yii::$app->name . '] Workspace health: recovered',
			"All previously reported workspace health problems are gone.\n\nChecked at " . date('Y-m-d H:i:s') . " on " . gethostname() . '.'
		)) {
			@unlink($this->getAlertStateFile());
			$this->stdout("Recovery email sent to {$email}.\n");
		}
	}

	/**
	 * @param string $email
	 * @param string $subject
	 * @param string $body
	 * @return bool
	 */
	protected function sendAlertEmail($email, $subject, $body)
	{
		try {
			return Yii::$app->mailer->compose()
				->setTo($email)
				->setSubject($subject)
				->setTextBody($body)
				->send();
		} catch (\Exception $e) {
			Yii::error(['message' => 'Cannot send the workspace health alert email.', 'error' => $e->getMessage()], __METHOD__);
			$this->stdout("Cannot send the alert email: {$e->getMessage()}\n", Console::FG_YELLOW);
			return false;
		}
	}

	/**
	 * @return string|null
	 */
	protected function getAlertEmail()
	{
		$email = $this->alertEmail !== null ? $this->alertEmail : (Yii::$app->params['healthAlert.email'] ?? null);
		return $email && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
	}

	/**
	 * @return string
	 */
	protected function getAlertStateFile()
	{
		return Yii::getAlias('@console/runtime/workspace-health-alert.json');
	}

	/**
	 * @return array|null `['hash' => string, 'sentAt' => int]`
	 */
	protected function readAlertState()
	{
		$file = $this->getAlertStateFile();
		if (!is_file($file)) {
			return null;
		}
		$state = json_decode((string) file_get_contents($file), true);
		return isset($state['hash'], $state['sentAt']) ? $state : null;
	}

	/**
	 * @param array $state
	 */
	protected function writeAlertState(array $state)
	{
		@file_put_contents($this->getAlertStateFile(), json_encode($state));
	}

	/**
	 * Scans the tail of a task log for error markers and returns distinct matches.
	 *
	 * @param string $file
	 * @return string[]
	 */
	protected function findRecentLogErrors($file)
	{
		if (!is_file($file) || !($size = filesize($file))) {
			return [];
		}

		$handle = fopen($file, 'r');
		if ($handle === false) {
			return [];
		}
		fseek($handle, max(0, $size - $this->logTailBytes));
		$tail = stream_get_contents($handle);
		fclose($handle);

		$errors = [];
		foreach (preg_split('/\r?\n/', $tail) as $line) {
			if (preg_match('/\b(error|exception|fatal|failed|stuck)\b/i', $line) && !preg_match('/0 failed/i', $line)) {
				$errors[trim($line)] = true;
			}
		}

		return array_slice(array_keys($errors), 0, 10);
	}
}
