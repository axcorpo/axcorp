<?php

namespace console\controllers;

use common\models\Workspace;
use common\helpers\UuidHelper;
use tws\helpers\DbHelper;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Debugging aid for Workspace::install() / uninstall().
 *
 * The install path branches on Workspace::isCPanelConfigured(): a dev box without
 * cPanel credentials creates the tenant database with raw SQL (CREATE DATABASE +
 * GRANT) and routes the tenant through the root .htaccess, while a cPanel host
 * goes through the UAPI (Mysql::create_database, set_privileges_on_database,
 * addon domain, cron line). "Works locally, fails on the server" is almost always
 * one of those cPanel-only steps, and the web UI only ever showed a generic
 * "Cannot reinstall the workspace." message.
 *
 *   php yii workspace-install/diagnose            # every workspace, environment report
 *   php yii workspace-install/diagnose imobiliareazro
 *   php yii workspace-install/run imobiliareazro --reinstall
 *   php yii workspace-install/cpanel-api
 *   php yii workspace-install/params-config [imobiliareazro]   # backfill params-local.php
 *
 * `diagnose` is read-only apart from one probe table it creates and drops again in
 * the tenant database; `run` performs a real (re)install and prints the failing
 * step with its full exception chain.
 */
class WorkspaceInstallController extends Controller
{
	/**
	 * @var bool Reinstall (drops the tenant database and directory first) instead of
	 * a plain install. Only used by the `run` action.
	 */
	public $reinstall = false;

	/**
	 * @var string Name of the throwaway table `diagnose` creates in the tenant database
	 * to prove the master credentials can actually write there.
	 */
	const PROBE_TABLE = '_ws_install_probe';

	/**
	 * @inheritdoc
	 */
	public function options($actionID)
	{
		return array_merge(parent::options($actionID), $actionID === 'run' ? ['reinstall'] : []);
	}

	/**
	 * @inheritdoc
	 */
	public function optionAliases()
	{
		return array_merge(parent::optionAliases(), ['r' => 'reinstall']);
	}

	/**
	 * Reports everything install() depends on, so the environment difference between
	 * the dev box and the server is visible before touching anything.
	 *
	 * @param string|null $code workspace code, url, domain or uuid; all workspaces when omitted
	 * @return int
	 */
	public function actionDiagnose($code = null)
	{
		$this->section('Environment');
		$db = Workspace::getDb();
		$this->line('YII_ENV', YII_ENV . (YII_DEBUG ? ' (debug)' : ''));
		$this->line('PHP', PHP_VERSION . ' — ' . PHP_BINARY);
		$this->line('Master DB', sprintf(
			'%s @ %s (user %s)',
			DbHelper::getDsnAttribute('dbname', $db),
			DbHelper::getDsnAttribute('host', $db) ?: 'localhost',
			$db->username
		));
		$this->line('MySQL', $db->createCommand('SELECT VERSION()')->queryScalar());
		$this->line('Server charset', implode(', ', array_map(function ($row) {
			return "{$row['Variable_name']}={$row['Value']}";
		}, $db->createCommand("SHOW VARIABLES WHERE Variable_name IN ('character_set_server','collation_server')")->queryAll())));

		$this->grantsReport($db);
		$this->cpanelReport();
		$this->filesystemReport();

		$workspaces = $code === null ? $this->findAll() : [$this->findOne($code)];
		if (in_array(null, $workspaces, true)) {
			$this->stderr("No workspace matched \"{$code}\".\n", Console::FG_RED);
			return ExitCode::DATAERR;
		}

		foreach ($workspaces as $workspace) {
			$this->workspaceReport($workspace);
		}

		return ExitCode::OK;
	}

	/**
	 * Runs the real install for one workspace and prints the failing step with the
	 * whole exception chain — the web UI can only show the collected model errors.
	 *
	 * @param string $code workspace code, url, domain or uuid
	 * @return int
	 */
	public function actionRun($code)
	{
		if (($workspace = $this->findOne($code)) === null) {
			$this->stderr("No workspace matched \"{$code}\".\n", Console::FG_RED);
			return ExitCode::DATAERR;
		}

		$this->section(($this->reinstall ? 'Reinstalling ' : 'Installing ') . $workspace->code);
		$this->line('Database', $workspace->getWorkspaceDbName());
		$this->line('Directory', $workspace->getDirectoryPath());
		$this->line('cPanel path', $workspace->isCPanelConfigured() ? 'yes (UAPI)' : 'no (raw SQL + .htaccess)');

		if ($this->reinstall && !$this->confirm('This DROPS the tenant database and directory. Continue?', true)) {
			return ExitCode::OK;
		}

		$started = microtime(true);
		$ok = $workspace->install($this->reinstall);
		$elapsed = round(microtime(true) - $started, 1);

		if ($ok) {
			$this->stdout("\nOK in {$elapsed}s\n", Console::FG_GREEN, Console::BOLD);
			return ExitCode::OK;
		}

		$this->stdout("\nFAILED after {$elapsed}s\n", Console::FG_RED, Console::BOLD);
		foreach ($workspace->getErrors() as $attribute => $errors) {
			foreach ($errors as $error) {
				$this->stdout('  ' . ($attribute ? "{$attribute}: " : '') . $error . "\n", Console::FG_RED);
			}
		}
		$this->stdout("\nFull stack trace: " . Yii::getAlias('@console/runtime/logs/app.log') . "\n", Console::FG_GREY);

		return ExitCode::UNSPECIFIED_ERROR;
	}

	/**
	 * Gives installed workspaces their own common/config/params-local.php (from the master's
	 * params, e.g. facebook.*) and regenerates their apps' config/main.php so they load it —
	 * what a fresh install now does. An existing params-local.php is left untouched.
	 *
	 * @param string|null $code workspace code, url, domain or uuid; every workspace when omitted
	 * @return int
	 */
	public function actionParamsConfig($code = null)
	{
		if ($code === null) {
			$workspaces = $this->findAll();
		} elseif (($workspace = $this->findOne($code)) !== null) {
			$workspaces = [$workspace];
		} else {
			$this->stderr("No workspace matched \"{$code}\".
", Console::FG_RED);
			return ExitCode::DATAERR;
		}

		$failed = 0;
		foreach ($workspaces as $workspace) {
			if ($workspace->writeTenantParamsConfig()) {
				$this->line($workspace->code, 'OK ' . $workspace->getDirectoryPath(), Console::FG_GREEN);
			} else {
				$failed++;
				$this->line($workspace->code, 'SKIPPED (no tenant directory, or not writable) ' . $workspace->getDirectoryPath(), Console::FG_YELLOW);
			}
		}

		return $failed ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
	}

	/**
	 * Asks this cPanel which domain-creation calls it still answers.
	 *
	 * ensureCpanelAddonDomain() goes through API 2 (AddonDomain::addaddondomain). When
	 * the same domain can be created by hand in the cPanel interface but the API call
	 * comes back "Access denied", the account is fine and the call is the problem —
	 * either API 2 is closed off for this account, or this cPanel is new enough that
	 * addon domains were folded into the unified Domains interface and the old module
	 * is gone. Both look identical from the outside, so ask.
	 *
	 * Every function below is called WITHOUT parameters on purpose: a live function
	 * answers by complaining about the missing domain name, a function that is gone
	 * says so. Nothing is created.
	 *
	 * @return int
	 */
	public function actionCpanelApi()
	{
		try {
			$cpanel = Yii::$app->get('cPanel');
		} catch (\Throwable $e) {
			$this->stderr('cPanel component not usable: ' . $e->getMessage() . "\n", Console::FG_RED);
			return ExitCode::CONFIG;
		}

		$this->section('cPanel API probe');
		$this->stdout("  Read-only. Creation calls are made with no parameters, so they fail\n", Console::FG_GREY);
		$this->stdout("  validation instead of creating anything.\n\n", Console::FG_GREY);

		$this->rawHttpReport($cpanel);
		$this->stdout("\n");

		$probes = [
			'UAPI  DomainInfo::list_domains' => function () use ($cpanel) {
				return $cpanel->uapi->DomainInfo->list_domains();
			},
			'UAPI  Domains::create_domain' => function () use ($cpanel) {
				return $cpanel->uapi->Domains->create_domain();
			},
			'UAPI  SubDomain::addsubdomain' => function () use ($cpanel) {
				return $cpanel->uapi->SubDomain->addsubdomain();
			},
			'UAPI  AddonDomain::addaddondomain' => function () use ($cpanel) {
				return $cpanel->uapi->AddonDomain->addaddondomain();
			},
			'API2  AddonDomain::addaddondomain' => function () use ($cpanel) {
				return $cpanel->api2->AddonDomain->addaddondomain();
			},
			'API2  AddonDomain::listaddondomains' => function () use ($cpanel) {
				return $cpanel->api2->AddonDomain->listaddondomains();
			},
		];

		foreach ($probes as $label => $probe) {
			try {
				$response = $probe();
			} catch (\Throwable $e) {
				$this->line($label, 'threw: ' . $this->firstLine($e->getMessage()), Console::FG_RED);
				continue;
			}

			if ($response === false) {
				$this->line($label, 'HTTP request failed (non-2xx; API 2 refusals land here)', Console::FG_RED);
				continue;
			}
			if (!is_array($response)) {
				$this->line($label, 'unreadable response', Console::FG_YELLOW);
				continue;
			}

			$this->line($label, $this->summarizeApiResponse($response), Console::FG_YELLOW);
		}

		$this->stdout("\n  A function that answers \"missing/invalid parameter\" is alive and usable.\n", Console::FG_GREY);
		$this->stdout("  One that answers \"does not exist\" or \"Access denied\" is not.\n", Console::FG_GREY);

		return ExitCode::OK;
	}

	/**
	 * The component reports every non-2xx answer as one undifferentiated failure, which
	 * cannot tell a wrong password from a closed port from an account whose API access
	 * was revoked. This repeats the simplest UAPI call by hand and prints what actually
	 * came back: the TCP reachability of the cPanel port, the HTTP status, and the first
	 * line of the body.
	 *
	 * @param object $cpanel
	 */
	protected function rawHttpReport($cpanel)
	{
		$baseUrl = rtrim((string) ($cpanel->baseUrl ?? ''), '/');
		$username = (string) ($cpanel->username ?? '');
		$password = (string) ($cpanel->password ?? '');
		$parts = parse_url($baseUrl);
		$host = $parts['host'] ?? '';
		$port = $parts['port'] ?? 2083;

		// A refused or filtered port is a different problem from a refused login, and
		// only one of the two is fixed by changing the configuration.
		$errno = 0;
		$errstr = '';
		$socket = @fsockopen(($parts['scheme'] ?? 'https') === 'https' ? "ssl://{$host}" : $host, (int) $port, $errno, $errstr, 5);
		if ($socket) {
			fclose($socket);
			$this->line('TCP ' . $port, 'reachable', Console::FG_GREEN);
		} else {
			$this->line('TCP ' . $port, "unreachable: {$errstr} ({$errno})", Console::FG_RED);
		}

		// Whatever the component would send: an API token when one is configured, Basic
		// auth otherwise. Probing with the password while the app authenticates by token
		// would report a 401 that says nothing about the app's own calls.
		if (method_exists($cpanel, 'getAuthorizationHeader')) {
			$authorization = $cpanel->getAuthorizationHeader();
		} else {
			$authorization = 'Basic ' . base64_encode("{$username}:{$password}");
		}
		$this->line('Auth method', strncmp($authorization, 'cpanel ', 7) === 0 ? 'API token' : 'password (Basic)');

		// Same call over the loopback interface: cPanel listens there too, and it is not
		// subject to whatever the public interface is filtered by.
		foreach ([$baseUrl, "https://127.0.0.1:{$port}"] as $target) {
			$this->rawUapiCall($target, $host, $authorization);
		}
	}

	/**
	 * Performs one unauthenticated-by-Guzzle-standards GET and reports the raw outcome.
	 *
	 * @param string $baseUrl
	 * @param string $host the Host header to send, so a loopback call still hits the
	 *                     right virtual host
	 * @param string $authorization the Authorization header the app itself would send
	 */
	protected function rawUapiCall(string $baseUrl, string $host, string $authorization)
	{
		$label = strpos($baseUrl, '127.0.0.1') !== false ? 'loopback' : 'public';

		try {
			$client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 15]);
			$response = $client->get(rtrim($baseUrl, '/') . '/execute/DomainInfo/list_domains', [
				'headers' => [
					'Authorization' => $authorization,
					'Accept' => 'application/json',
					'Host' => $host,
				],
				'http_errors' => false,
			]);
		} catch (\Throwable $e) {
			$this->line("HTTP {$label}", 'transport error: ' . $this->firstLine($e->getMessage()), Console::FG_RED);
			return;
		}

		$status = $response->getStatusCode();
		$body = trim((string) $response->getBody());
		$snippet = $this->firstLine(mb_substr($body, 0, 180));
		$colour = $status === 200 ? Console::FG_GREEN : Console::FG_RED;

		$this->line("HTTP {$label}", "{$status} " . $response->getReasonPhrase(), $colour);
		if ($snippet !== '') {
			$this->stdout('                  ' . $snippet . "\n", Console::FG_GREY);
		}
		if ($status === 401) {
			$this->stdout("                  401 = the credentials are rejected. An account with two-factor\n", Console::FG_GREY);
			$this->stdout("                  authentication cannot authenticate by password here; configure apiToken.\n", Console::FG_GREY);
		}
		if ($status === 403) {
			$this->stdout("                  403 = authenticated but refused: API access revoked for the account,\n", Console::FG_GREY);
			$this->stdout("                  a cPHulk block on this IP, or a security policy in front of cPanel.\n", Console::FG_GREY);
		}
	}

	/**
	 * Condenses a UAPI or API 2 response into the one line that says why it refused.
	 *
	 * @param array $response
	 * @return string
	 */
	protected function summarizeApiResponse(array $response)
	{
		// UAPI
		if (array_key_exists('status', $response)) {
			$errors = (array) ($response['errors'] ?? []);
			$status = (int) $response['status'] === 1 ? 'ok' : 'refused';

			return $errors ? "{$status}: " . $this->firstLine(implode('; ', $errors)) : $status;
		}

		// API 2
		$result = $response['cpanelresult'] ?? [];
		$parts = [];
		if (isset($result['error'])) {
			$parts[] = 'error: ' . $result['error'];
		}
		if (isset($result['data']['reason'])) {
			$parts[] = 'reason: ' . $result['data']['reason'];
		}
		if (isset($result['data'][0]['reason'])) {
			$parts[] = 'reason: ' . $result['data'][0]['reason'];
		}

		return $parts ? $this->firstLine(implode(' | ', $parts)) : 'answered without an error';
	}

	/**
	 * Prints the master user's grants. On a cPanel host the tenant databases are
	 * created by the UAPI and the master user is granted on them separately, so a
	 * missing grant here is the ERROR 1044 seen during the import.
	 *
	 * @param \yii\db\Connection $db
	 */
	protected function grantsReport($db)
	{
		$this->section('MySQL grants (current user)');
		try {
			foreach ($db->createCommand('SHOW GRANTS FOR CURRENT_USER()')->queryColumn() as $grant) {
				// The IDENTIFIED BY clause of old MySQL builds would leak the password hash.
				$this->stdout('  ' . preg_replace('/ IDENTIFIED BY .*/i', '', $grant) . "\n");
			}
		} catch (\Throwable $e) {
			$this->stdout('  unavailable: ' . $e->getMessage() . "\n", Console::FG_YELLOW);
		}
	}

	/**
	 * Probes the cPanel component: whether it is configured at all (a dev box keeps
	 * the CPANEL_* placeholders, which makes init() throw and the model fall back to
	 * the raw-SQL path), and whether the credentials actually answer.
	 */
	protected function cpanelReport()
	{
		$this->section('cPanel');
		try {
			$cpanel = Yii::$app->get('cPanel');
		} catch (\Throwable $e) {
			$this->line('Component', 'not usable — ' . $e->getMessage(), Console::FG_YELLOW);
			$this->stdout("  install() therefore uses the raw SQL + .htaccess path.\n", Console::FG_GREY);
			return;
		}

		$this->line('Base URL', (string) $cpanel->baseUrl);
		$this->line('Username', (string) $cpanel->username);
		$this->line('Password', $cpanel->password ? str_repeat('*', 8) : '(empty)');
		$this->line('API token', empty($cpanel->apiToken) ? '(none, password auth)' : str_repeat('*', 8) . ' (used instead of the password)');

		// Read-only call: proves the credentials, the URL and the transport all work.
		// A false return means the HTTP request itself failed (bad host, 401, blocked
		// port 2083); status = 0 means cPanel refused the call.
		$response = $cpanel->uapi->Mysql->list_databases();
		if ($response === false) {
			$this->line('UAPI probe', 'HTTP request failed (URL, credentials or firewall)', Console::FG_RED);
			return;
		}
		if ((int) ($response['status'] ?? 0) !== 1) {
			$this->line('UAPI probe', 'refused: ' . $this->cpanelErrors($response), Console::FG_RED);
			return;
		}

		$databases = array_column($response['data'] ?? [], 'database');
		$this->line('UAPI probe', 'ok — ' . count($databases) . ' database(s) visible', Console::FG_GREEN);
	}

	/**
	 * Checks the paths install() writes to. These are the silent failures on a shared
	 * host: an unwritable .htaccess or workspaces/ directory, or disabled symlink().
	 */
	protected function filesystemReport()
	{
		$this->section('Filesystem');
		$base = Yii::getAlias('@base');
		$this->line('@base', $base);
		$this->line('workspaces/', $this->pathState("{$base}/workspaces"));
		$this->line('.htaccess', $this->pathState("{$base}/.htaccess"));
		$this->line('@workspace', $this->pathState(Yii::getAlias('@workspace/install/dir')));
		$this->line('install/db', $this->pathState(Yii::getAlias('@workspace/install/db')));

		if (($free = @disk_free_space($base)) !== false) {
			$this->line('Free space', round($free / 1024 / 1024 / 1024, 1) . ' GB');
		}

		// Both checks below describe the Linux host only: on Windows FileHelper::symlink()
		// falls back to directory junctions and the tenant crons are not used at all, so
		// probing them on the dev box would report failures that mean nothing.
		if (DIRECTORY_SEPARATOR === '\\') {
			$this->line('symlink()', 'skipped on Windows (junctions are used instead)', Console::FG_GREY);
			$this->line('Cron PHP', 'skipped on Windows', Console::FG_GREY);

			return;
		}

		// FileHelper::symlink() is what links the shared sources into every tenant;
		// some shared hosts disable symlink() through disable_functions.
		if (!function_exists('symlink')) {
			$this->line('symlink()', 'disabled by php.ini (disable_functions)', Console::FG_RED);
		} else {
			$link = "{$base}/workspaces/.symlink-probe";
			@unlink($link);
			$created = @symlink(Yii::getAlias('@workspace/install/dir'), $link);
			$this->line('symlink()', $created ? 'ok' : 'failed: ' . (error_get_last()['message'] ?? 'unknown'), $created ? Console::FG_GREEN : Console::FG_RED);
			@unlink($link);
		}

		// updateCrontab() hardcodes this interpreter into every tenant's cron line.
		$cronPhp = '/usr/local/bin/php';
		$this->line('Cron PHP', is_file($cronPhp) ? "{$cronPhp} (ok)" : "{$cronPhp} MISSING — tenant crons would not run", is_file($cronPhp) ? Console::FG_GREEN : Console::FG_YELLOW);

	}

	/**
	 * Per-workspace report: the tenant database's real state and whether the master
	 * credentials can do to it what the import needs.
	 *
	 * @param Workspace $workspace
	 */
	protected function workspaceReport(Workspace $workspace)
	{
		$db = Workspace::getDb();
		$dbName = $workspace->getWorkspaceDbName();

		$this->section("Workspace {$workspace->code} ({$workspace->uuid})");
		$this->line('Status', $workspace->status == Workspace::STATUS_ACTIVE ? 'active' : (string) $workspace->status);
		$this->line('URL / domain', $workspace->url . ' / ' . ($workspace->domain ?: '(none)'));
		$this->line('Type', $workspace->type . ' — seeds: ' . (implode(', ', array_map('basename', glob(Yii::getAlias("@workspace/install/db/{$workspace->type}") . '/*.sql') ?: [])) ?: 'none'));
		$this->line('Directory', $this->pathState($workspace->getDirectoryPath()));
		$this->line('Tenant DB', $dbName);

		$exists = (bool) $db->createCommand('SHOW DATABASES LIKE :name', [':name' => $dbName])->queryScalar();
		if (!$exists) {
			$this->line('DB exists', 'no — it will be created by install()', Console::FG_GREY);
			return;
		}

		$row = $db->createCommand(
			'SELECT DEFAULT_CHARACTER_SET_NAME cs, DEFAULT_COLLATION_NAME co FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :name',
			[':name' => $dbName]
		)->queryOne();
		$latin = strpos((string) $row['cs'], 'latin') === 0;
		$this->line('DB charset', "{$row['cs']} / {$row['co']}", $latin ? Console::FG_YELLOW : Console::FG_GREEN);

		$tables = (int) $db->createCommand(
			'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :name',
			[':name' => $dbName]
		)->queryScalar();
		$this->line('Tables', (string) $tables);

		// Everything below reproduces, one statement at a time, what installDatabase()
		// does in one go — so the exact statement that is refused is named.
		$workspaceDb = $workspace->getWorkspaceDb();
		try {
			$workspaceDb->open();
			$this->line('Connect', 'ok as ' . $db->username, Console::FG_GREEN);
		} catch (\Throwable $e) {
			$this->line('Connect', 'FAILED — ' . $e->getMessage(), Console::FG_RED);
			$this->stdout("  Grant the master user on this database (cPanel > MySQL Databases > Add user to database, ALL PRIVILEGES).\n", Console::FG_GREY);
			return;
		}

		$probes = [
			'CREATE TABLE' => 'CREATE TABLE IF NOT EXISTS `' . self::PROBE_TABLE . '` (`id` INT NOT NULL) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8 COLLATE = utf8_unicode_ci',
			'ALTER TABLE' => 'ALTER TABLE `' . self::PROBE_TABLE . '` ADD COLUMN `probe` VARCHAR(8) NULL',
			'INSERT' => 'INSERT INTO `' . self::PROBE_TABLE . '` (`id`) VALUES (1)',
			'DROP TABLE' => 'DROP TABLE IF EXISTS `' . self::PROBE_TABLE . '`',
		];
		foreach ($probes as $label => $sql) {
			try {
				$workspaceDb->createCommand($sql)->execute();
				$this->line($label, 'ok', Console::FG_GREEN);
			} catch (\Throwable $e) {
				$this->line($label, 'FAILED — ' . $this->firstLine($e->getMessage()), Console::FG_RED);
			}
		}

		// The statement that aborted the production reinstall. It is idempotent (it sets
		// the charset the tables already use), and is now best-effort inside install().
		try {
			$db->createCommand("ALTER DATABASE `{$dbName}` CHARACTER SET utf8 COLLATE utf8_unicode_ci")->execute();
			$this->line('ALTER DATABASE', 'ok', Console::FG_GREEN);
		} catch (\Throwable $e) {
			$this->line('ALTER DATABASE', 'denied — ' . $this->firstLine($e->getMessage()), Console::FG_YELLOW);
			$this->stdout("  Harmless: every CREATE TABLE in _01_structure.sql pins its own charset.\n", Console::FG_GREY);
		}

		if ($workspace->isCPanelConfigured()) {
			$this->cpanelWorkspaceReport($workspace);
		}
	}

	/**
	 * The cPanel-only install steps: the addon domain that serves the tenant and the
	 * cron line that drives its scheduler. Both are no-ops on a dev box, which is why
	 * a failure here only ever shows up on the server.
	 *
	 * @param Workspace $workspace
	 */
	protected function cpanelWorkspaceReport(Workspace $workspace)
	{
		try {
			$cpanel = Yii::$app->get('cPanel');
		} catch (\Throwable $e) {
			return;
		}

		$domain = strtolower(trim((string) $workspace->domain));
		if ($domain === '' || strpos($domain, '.') === false) {
			$this->line('Addon domain', 'the `domain` column is not a full domain name — ensureCpanelAddonDomain() fails here', Console::FG_RED);
			return;
		}

		$response = $cpanel->uapi->DomainInfo->list_domains();
		if ($response === false || (int) ($response['status'] ?? 0) !== 1) {
			$this->line('Addon domain', 'cannot list the domains: ' . $this->cpanelErrors($response), Console::FG_RED);
		} else {
			$addons = array_map('strtolower', array_filter((array) ($response['data']['addon_domains'] ?? [])));
			$known = in_array($domain, $addons, true);
			$this->line('Addon domain', $domain . ($known ? ' — exists' : ' — missing, install() will create it'), $known ? Console::FG_GREEN : Console::FG_GREY);
			$this->line('Document root', 'public_html/' . $workspace->getRelativeDirectoryPath());
		}

		// The exact line updateCrontab() adds. A stale one left over from an older
		// directory layout is why a tenant scheduler can look installed and still
		// never run: the API2 call that adds it is fire-and-forget.
		$expected = '/usr/local/bin/php ' . $workspace->getDirectoryPath() . '/yii schedule/run >/dev/null 2>&1';
		$response = $cpanel->api2->Cron->fetchcron();
		$lines = $response['cpanelresult']['data'] ?? null;
		if (!is_array($lines)) {
			$this->line('Cron line', 'cannot read the crontab through API2', Console::FG_YELLOW);
			return;
		}

		$commands = array_column(array_filter($lines, function ($line) {
			return ($line['type'] ?? '') === 'command';
		}), 'command');

		if (in_array($expected, $commands, true)) {
			$this->line('Cron line', 'present', Console::FG_GREEN);
			return;
		}

		$this->line('Cron line', 'missing', Console::FG_YELLOW);
		$this->stdout('  expected: ' . $expected . "\n", Console::FG_GREY);
		foreach ($commands as $command) {
			if (strpos($command, strtolower($workspace->domain ?: $workspace->url)) !== false) {
				$this->stdout('  stale?  : ' . $command . "\n", Console::FG_GREY);
			}
		}
	}

	/**
	 * @param string $code code, url, domain or uuid
	 * @return Workspace|null
	 */
	protected function findOne($code)
	{
		// `uuid` is a virtual property over the BINARY(16) primary key, so a uuid
		// argument has to be matched against `id` in its packed form.
		$condition = ['or', ['code' => $code], ['url' => $code], ['domain' => $code]];
		if (UuidHelper::isValid($code)) {
			$condition[] = ['id' => UuidHelper::toBytes($code)];
		}

		return Workspace::find()
			->where(['deleted' => Workspace::NO])
			->andWhere($condition)
			->one();
	}

	/**
	 * @return Workspace[]
	 */
	protected function findAll()
	{
		return Workspace::find()
			->where(['deleted' => Workspace::NO])
			->orderBy(['code' => SORT_ASC])
			->all();
	}

	/**
	 * Describes a path the installer needs: existence, kind and writability.
	 *
	 * @param string|null $path
	 * @return string
	 */
	protected function pathState($path)
	{
		if (!$path) {
			return '(none)';
		}
		if (!file_exists($path)) {
			return "{$path} — MISSING";
		}

		$kind = is_link($path) ? 'symlink' : (is_dir($path) ? 'dir' : 'file');
		$perms = substr(sprintf('%o', @fileperms($path)), -4);

		return "{$path} — {$kind} {$perms} " . (is_writable($path) ? 'writable' : 'NOT writable');
	}

	/**
	 * @param array|false $response a cPanel UAPI response
	 * @return string
	 */
	protected function cpanelErrors($response)
	{
		$errors = is_array($response) ? ($response['errors'] ?? null) : null;

		return $errors ? implode('; ', (array) $errors) : 'no error message returned';
	}

	/**
	 * @param string $message
	 * @return string
	 */
	protected function firstLine($message)
	{
		return trim(strtok($message, "\n"));
	}

	/**
	 * @param string $title
	 */
	protected function section($title)
	{
		$this->stdout("\n" . $title . "\n", Console::FG_CYAN, Console::BOLD);
		$this->stdout(str_repeat('-', strlen($title)) . "\n", Console::FG_CYAN);
	}

	/**
	 * @param string $label
	 * @param string $value
	 * @param int|null $color
	 */
	protected function line($label, $value, $color = null)
	{
		$this->stdout('  ' . str_pad($label, 34) . ' ');
		$this->stdout($value . "\n", ...($color === null ? [] : [$color]));
	}
}
