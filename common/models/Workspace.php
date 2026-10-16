<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use tws\helpers\DbHelper;
use common\helpers\FileHelper;
use yii\httpclient\Client;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\db\Query;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%workspace}}".
 *
 * @property int $id
 * @property int $subscription_id
 * @property string $code
 * @property string $url
 * @property string $domain
 * @property int $type
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property Subscription $subscription
 * @property WorkspaceHasSubscriptionFeature[] $workspaceHasSubscriptionFeatures
 * @property SubscriptionFeature[] $subscriptionFeatures
 * @property WorkspaceHasUser[] $workspaceHasUsers
 * @property User[] $users
 * @property User $creator
 * @property User $updater
 */
class Workspace extends UuidActiveRecord
{
	const TYPE_GENERAL = 1;
	const TYPE_AUTO = 2;
	const TYPE_REAL_ESTATE = 3;
	const TYPE_JOBS = 4;
	const TYPE_SERVICES = 5;
	const TYPE_MACHINERY = 6;
	const TYPE_NICHE = 7;

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%workspace}}';
	}

	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'BlameableBehavior' => [
				'class' => BlameableBehavior::class,
			],
			'TimestampBehavior' => [
				'class' => TimestampBehavior::class,
				'value' => (new \DateTime)->format('Y-m-d H:i:s'),
			],
			'SoftDeleteBehavior' => [
				'class' => SoftDeleteBehavior::class,
				'softDeleteAttributeValues' => [
					'deleted' => static::YES,
				],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['subscription_id', 'type', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['code', 'url', 'status'], 'required'],
			[['created_at', 'updated_at'], 'safe'],
			[['code', 'url', 'domain'], 'string', 'max' => 255],
			[['subscription_id'], 'exist', 'skipOnError' => true, 'targetClass' => Subscription::class, 'targetAttribute' => ['subscription_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'subscription_id' => Yii::t('label', 'Subscription ID'),
			'code' => Yii::t('label', 'Code'),
			'url' => Yii::t('label', 'Url'),
			'domain' => Yii::t('label', 'Domain'),
			'type' => Yii::t('label', 'Type'),
			'created_by' => Yii::t('label', 'Created By'),
			'updated_by' => Yii::t('label', 'Updated By'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
			'status' => Yii::t('label', 'Status'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscription()
	{
		return $this->hasOne(Subscription::class, ['id' => 'subscription_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getWorkspaceHasSubscriptionFeatures()
	{
		return $this->hasMany(WorkspaceHasSubscriptionFeature::class, ['workspace_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriptionFeatures()
	{
		return $this->hasMany(SubscriptionFeature::class, ['id' => 'subscription_feature_id'])->viaTable('{{%workspace_has_subscription_feature}}', ['workspace_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getWorkspaceHasUsers()
	{
		return $this->hasMany(WorkspaceHasUser::class, ['workspace_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUsers()
	{
		return $this->hasMany(User::class, ['id' => 'user_id'])->viaTable('{{%workspace_has_user}}', ['workspace_id' => 'id']);
	}

    /**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCreator()
	{
		return $this->hasOne(User::class, ['id' => 'created_by']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUpdater()
	{
		return $this->hasOne(User::class, ['id' => 'updated_by']);
	}

	/**
	 * Model type labels.
	 *
	 * @return array
	 */
	public static function getTypeLabels()
	{
		return [
			static::TYPE_GENERAL => Yii::t('label', 'General marketplace'),
			static::TYPE_AUTO => Yii::t('label', 'Auto'),
			static::TYPE_REAL_ESTATE => Yii::t('label', 'Real estate'),
			static::TYPE_JOBS => Yii::t('label', 'Jobs'),
			static::TYPE_SERVICES => Yii::t('label', 'Services'),
			static::TYPE_MACHINERY => Yii::t('label', 'Machinery & construction'),
			static::TYPE_NICHE => Yii::t('label', 'Specialized niches'),
		];
	}

	/**
	 * Gets the absoluteUrl.
	 *
	 * The platform's own domain is its canonical address, so every link the master
	 * shows points there whenever a domain is configured; the path URL under the
	 * master host remains the fallback for workspaces without one.
	 *
	 * @param bool $administration Flag that indicates if the administration URL should be returned.
	 * @return string
	 */
	public function getAbsoluteUrl($administration = false)
	{
		$segments = [$this->domain
			? 'https://' . $this->domain
			: implode('/', [Yii::$app->urlManager->hostInfo, $this->url])];

		if ($administration === true) {
			$segments[] = 'admin';
		}

		return implode('/', $segments);
	}

	/**
	 * Gets if isDefault.
	 *
	 * @return bool
	 */
	public function getIsDefault()
	{
		return WorkspaceHasUser::find()
			->where([
				'workspace_id' => $this->id,
				'default' => WorkspaceHasUser::YES,
			])
			->exists();
	}

	/**
	 * Gets the WorkspaceHasSubscriptionFeature model by SubscriptionFeature name property.
	 *
	 * @param string $featureName
	 * @return \yii\db\ActiveRecord|WorkspaceHasSubscriptionFeature|null
	 */
	public function getWorkspaceSubscriptionFeature($featureName)
	{
		return $this->getWorkspaceHasSubscriptionFeatures()
			->alias('whsa')
			->joinWith([
				'subscriptionFeature sa' => function (ActiveQuery $query) use ($featureName) {
					$query->andWhere([
						'sa.subscription_id' => $this->subscription_id,
						'sa.name' => $featureName,
						'sa.deleted' => SubscriptionFeature::NO,
					]);
				},
			])
			->one();
	}

	/**
	 * Generates an unique code.
	 *
	 * @param int $length
	 * @return string
	 * @throws \yii\base\Exception
	 */
	public static function generateUniqueCode($length = 8)
	{
		$code = Yii::$app->security->generateRandomString($length);

		// Ensure that the generated string is alphanumeric
		if (!preg_match('/^[a-zA-Z0-9]*$/', $code)) {
			return static::generateUniqueCode($length);
		}

		// Ensure that the generated string is unique
		if (static::find()->where(['code' => $code])->limit(1)->exists()) {
			return static::generateUniqueCode($length);
		}

		return $code;
	}

	/**
	 * Finds all records by user model ID.
	 *
	 * @param int $user_id
	 * @return array|\yii\db\ActiveRecord[]|static[]
	 */
	public static function findAllWorkspacesByUser($user_id)
	{
		return static::find()
			->alias('w')
			->joinWith([
				'workspaceHasUsers whu' => function (\yii\db\ActiveQuery $query) use ($user_id) {
					$query->andWhere([
						'whu.user_id' => $user_id,
						'whu.status' => WorkspaceHasUser::STATUS_ACTIVE,
						'whu.deleted' => WorkspaceHasUser::NO,
					]);
				}
			], false)
			->andWhere([
				'w.status' => static::STATUS_ACTIVE,
				'w.deleted' => static::NO,
			])
			->all();
	}

	//region Workspace Config
	/**
	 * Gets the Workspace directory path.
	 *
	 * @return bool|string
	 */
	public function getDirectoryPath()
	{
		if ($this->isNewRecord) {
			return null;
		}

		$domain = strtolower($this->domain ?: $this->url);

		return Yii::getAlias("@base/workspaces/{$domain}");
	}

	/**
	 * Gets the workspace directory path relative to the web root (for .htaccess/cPanel).
	 *
	 * @return string
	 */
	public function getRelativeDirectoryPath()
	{
		return 'workspaces/' . strtolower($this->domain ?: $this->url);
	}

	/**
	 * Gets the Workspace database name.
	 *
	 * @return string|null
	 */
	public function getWorkspaceDbName()
	{
		if ($this->isNewRecord) {
			return null;
		}

		$dbNameParts = preg_split('/_|-/', DbHelper::getDsnAttribute('dbname', static::getDb()));
		$dbNameParts[count($dbNameParts) - 1] = $this->code;

		return implode('_', $dbNameParts);
	}

	/**
	 * Gets the Workspace database instance.
	 *
	 * @return null|object|\yii\db\Connection
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getWorkspaceDb()
	{
		if ($this->isNewRecord) {
			return null;
		}

		$db = static::getDb();

		return Yii::createObject([
			'class' => 'yii\db\Connection',
			'dsn' => str_replace(DbHelper::getDsnAttribute('dbname', $db), $this->getWorkspaceDbName(), $db->dsn),
			'username' => $db->username,
			'password' => $db->password,
			'charset' => $db->charset,
		]);
	}

	/**
	 * Prepares the rows that need to be imported to Workspace Database.
	 * This method alters the metadata attributes (creator, updater etc.)
	 *
	 * @param array $rows
	 * @return array
	 * @throws \Exception
	 */
	protected function prepareImportRows($rows)
	{
		$currentDate = new \DateTime();
		$user = $this->subscription->subscriber->user;
		$metadataRow = [
			'created_by' => $user->id,
			'updated_by' => $user->id,
			'created_at' => $currentDate->format('Y-m-d H:i:s'),
			'updated_at' => $currentDate->format('Y-m-d H:i:s'),
		];

		return array_map(function ($row) use ($metadataRow) {
			return array_merge($row, $metadataRow);
		}, $rows);
	}

	/**
	 * True when the cPanel API is available and properly configured.
	 *
	 * The cPanel component is always declared in the config, but on dev hosts it holds placeholder
	 * values (e.g. "CPANEL_BASE_URL"), which makes its init() throw an InvalidConfigException. A
	 * successful instantiation therefore means cPanel is real and should be used instead of raw,
	 * privilege-hungry SQL (CREATE DATABASE / GRANT) that managed hosts reject. Unlike the previous
	 * `YII_ENV_DEV && request IP === 127.0.0.1` check, this also works from console/cron/webhook
	 * contexts where there is no HTTP request.
	 *
	 * @return bool
	 */
	public function isCPanelConfigured()
	{
		try {
			return Yii::$app->get('cPanel') instanceof \tws\cpanel\CPanel;
		} catch (\Throwable $e) {
			return false;
		}
	}

	/**
	 * Installs the Workspace database.
	 *
	 * @return bool
	 */
	protected function installDatabase()
	{
		$db = static::getDb();
		$grantFailure = null;

		try {
			// Create the database
			if (!$this->isCPanelConfigured()) {
				$sql = [
					"CREATE DATABASE IF NOT EXISTS {$this->getWorkspaceDbName()} CHARACTER SET utf8 COLLATE utf8_unicode_ci",
					"GRANT ALL ON `{$this->getWorkspaceDbName()}`.* TO '{$db->username}'@'%' IDENTIFIED BY '{$db->password}'",
					"FLUSH PRIVILEGES",
				];
				$db->createCommand(implode(";\n", $sql))->execute();
			} else {
				// Create the database using the cPanel API. Failures are logged but not
				// fatal here: "already exists" on a reinstall is benign, and a truly
				// unusable database makes the import below fail loudly anyway.
				$response = Yii::$app->cPanel->uapi->Mysql->create_database(['name' => $this->getWorkspaceDbName()]);
				$this->logCpanelUapiFailure('Mysql::create_database', $response);
				$response = Yii::$app->cPanel->uapi->Mysql->set_privileges_on_database([
					'user' => $db->username,
					'database' => $this->getWorkspaceDbName(),
					'privileges' => 'ALL PRIVILEGES',
				]);
				$this->logCpanelUapiFailure('Mysql::set_privileges_on_database', $response);
				if (!is_array($response) || (int) ($response['status'] ?? 0) !== 1) {
					// Not fatal on its own — the user may already hold the grant from an
					// earlier install — but remembered, so the connection check below can
					// name the real cause instead of leaving a bare "Access denied".
					$grantFailure = $this->describeCpanelFailure($response);
				}

				// cPanel's create_database uses the server default charset (latin1 on the
				// production host). Every CREATE TABLE in _01_structure.sql pins its own
				// utf8/utf8_unicode_ci, so the import no longer depends on that default and
				// this only realigns the database for anything created later. Best effort:
				// a shared host withholds ALTER on the database itself (ERROR 1044) from a
				// user that only holds table-level grants, and that used to abort the
				// whole install on a database that was in fact perfectly usable.
				try {
					$db->createCommand("ALTER DATABASE `{$this->getWorkspaceDbName()}` CHARACTER SET utf8 COLLATE utf8_unicode_ci")->execute();
				} catch (\Exception $e) {
					Yii::warning([
						'message' => 'Cannot set the workspace database default charset; the tables pin their own.',
						'workspace' => $this->code,
						'error' => $e->getMessage(),
					], 'cpanel');
				}
			}

			// Get the database instance
			$workspaceDb = $this->getWorkspaceDb();

			// Fail here, with the cause, rather than midway through the first import: the
			// cPanel grant call above only warns, so without this the operator sees PDO's
			// bare "Access denied for user ... to database ..." and nothing about why.
			try {
				$workspaceDb->open();
			} catch (\Exception $e) {
				throw new \Exception(sprintf(
					'The workspace database %s is not reachable with the master credentials (%s): %s %s',
					$this->getWorkspaceDbName(),
					$db->username,
					$e->getMessage(),
					$grantFailure === null
						? 'Check the cPanel MySQL privileges for this database.'
						: "cPanel refused the privilege grant: {$grantFailure}"
				), 0, $e);
			}

			$workspaceDbPath = Yii::getAlias("@workspace/install/db");

			// Import the database structure and data
			$this->importSqlFile($workspaceDb, "{$workspaceDbPath}/_01_structure.sql");
			$this->importSqlFile($workspaceDb, "{$workspaceDbPath}/_02_permissions.sql");
			$this->importSqlFile($workspaceDb, "{$workspaceDbPath}/_03_common.sql");
			$this->importSqlFile($workspaceDb, "{$workspaceDbPath}/_04_translations.sql");
			$this->importSqlFile($workspaceDb, "{$workspaceDbPath}/_05_data.sql");

			// Vertical-specific seeds (install/db/<type>/*.sql): e.g. install/db/2
			// holds the auto-marketplace taxonomy (TYPE_AUTO). Imported after the
			// shared data so the files can reference seeded rows (actions, languages).
			foreach (glob("{$workspaceDbPath}/{$this->type}/*.sql") ?: [] as $typeSqlFile) {
				$this->importSqlFile($workspaceDb, $typeSqlFile);
			}

			// Create the super admin user by copying the subscriber's master account into
			// the tenant (same BINARY(16) id and password hash, so the credentials match).
			if ($user = $this->subscription->subscriber->user ?? null) {
				$attributes = $user->attributes;
				$attributes['parent_id'] = null;

				$workspaceDb->createCommand()->insert('{{%user}}', $attributes)->execute();
				$workspaceDb->createCommand()->insert('{{%auth_assignment}}', [
					'item_name' => 'superAdmin',
					'user_id' => $user->id,
					'created_at' => time(),
				])->execute();
			}

			// Create related records, if any
//			if ($rows = WsTemplate::findAllForImport()) {
//				$workspaceDb->createCommand()->batchInsert('{{%template}}', array_keys($rows[0]), $this->prepareImportRows($rows))->execute();
//				if ($translationRows = WsTemplateTranslation::findAllByTemplate(array_column($rows, 'id'), true)) {
//					$workspaceDb->createCommand()->batchInsert('{{%template_translation}}', array_keys($translationRows[0]), $translationRows)->execute();
//				}
//			}

			return true;
		} catch (\Exception $e) {
			Yii::error(['message' => $e->getMessage(), 'workspace' => $this->code, 'exception' => (string) $e], __METHOD__);
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * Imports a multi-statement SQL file into the given connection, failing on ANY
	 * broken statement.
	 *
	 * PDO only throws for the first statement of a multi-statement string; when a
	 * later statement fails, execution stops there and the rest of the file is
	 * silently skipped, leaving a half-imported schema behind. Draining every
	 * result set via nextRowset() surfaces the real error.
	 *
	 * @param \yii\db\Connection $db
	 * @param string $filePath
	 * @throws \Exception when the file cannot be read or any statement fails
	 */
	protected function importSqlFile($db, string $filePath): void
	{
		$sql = file_get_contents($filePath);
		if ($sql === false) {
			throw new \Exception("Cannot read the SQL file: {$filePath}");
		}

		$db->open();
		$statement = $db->pdo->prepare($sql);
		$statement->execute();

		try {
			while ($statement->nextRowset()) {
				// advance through every statement's result set
			}
		} catch (\PDOException $e) {
			throw new \Exception('SQL import failed in ' . basename($filePath) . ': ' . $e->getMessage(), 0, $e);
		}

		// Some driver versions report the failed statement via errorInfo() instead
		// of throwing from nextRowset().
		$error = $statement->errorInfo();
		if (!in_array($error[0] ?? '00000', ['00000', null], true)) {
			throw new \Exception('SQL import failed in ' . basename($filePath) . ': ' . ($error[2] ?? $error[0]));
		}
	}

	/**
	 * Turns a failed cPanel UAPI response into one readable sentence, for the errors
	 * shown to the operator.
	 *
	 * @param mixed $response
	 * @return string
	 */
	protected function describeCpanelFailure($response): string
	{
		if (!is_array($response)) {
			// CPanel::makeRequest() returns false for any non-2xx response, so this is a
			// wrong base URL, wrong credentials or a blocked port — not a cPanel refusal.
			return 'the HTTP request failed (check the cPanel base URL, credentials and firewall)';
		}

		$errors = $response['errors'] ?? null;

		return $errors ? implode('; ', (array) $errors) : 'no error message returned';
	}

	/**
	 * Logs a cPanel UAPI response when it reports failure (status != 1) or came back
	 * malformed. Logging instead of aborting keeps reinstalls idempotent ("database
	 * already exists" is benign) while leaving a trace for real failures.
	 *
	 * @param string $operation
	 * @param mixed $response
	 */
	protected function logCpanelUapiFailure(string $operation, $response): void
	{
		if (is_array($response) && (int)($response['status'] ?? 0) === 1) {
			return;
		}

		Yii::warning([
			'message' => "cPanel {$operation} failed.",
			'workspace' => $this->code,
			'response' => $response,
		], 'cpanel');
	}

	/**
	 * Clears the tenant application's runtime file cache, so a direct write to the tenant
	 * database (e.g. a settings push) is not masked by the tenant's cached values. The
	 * settings are cached via a TagDependency with no expiry, so without this the tenant
	 * would keep serving the old values.
	 */
	public function flushTenantCache()
	{
		$dir = $this->getDirectoryPath();
		if (!$dir) {
			return;
		}
		foreach (['backend', 'frontend', 'api'] as $app) {
			$cacheDir = "{$dir}/{$app}/runtime/cache";
			if (!is_dir($cacheDir)) {
				continue;
			}
			foreach (glob("{$cacheDir}/*") as $path) {
				if (is_dir($path)) {
					\yii\helpers\FileHelper::removeDirectory($path);
				} elseif (is_file($path)) {
					@unlink($path);
				}
			}
		}
	}

	/**
	 * The tenant's own logo, served from its directory through the same rewrite as the
	 * uploads. Resolved the way the tenant itself resolves it: the uploaded `appLogo`
	 * when the settings carry one, otherwise the active theme's logo, which is what
	 * every workspace falls back to (none of them uploads a logo today).
	 *
	 * @return string|null Null when the workspace has no public url or no readable logo.
	 */
	public function getLogoUrl()
	{
		if (!$this->url) {
			return null;
		}

		$settings = $this->getTenantSettings('general');
		$directory = $this->getDirectoryPath();
		$base = '/' . strtolower($this->url);

		if (!empty($settings['appLogo'])) {
			$file = $directory . '/uploads/' . $settings['appLogo'];
			if (is_file($file)) {
				return $base . '/uploads/' . $settings['appLogo'];
			}
		}

		// Theme logos live in the shared frontend assets the tenant directory links to,
		// each theme falling back to `default` for the files it does not override.
		foreach (array_filter([$settings['theme'] ?? null, 'default']) as $theme) {
			$path = "/tpl/{$theme}/img/logo.png";
			if (is_file($directory . '/frontend/web' . $path)) {
				return $base . $path;
			}
		}

		return null;
	}

	/**
	 * One category of the tenant settings as the tenant itself holds them, read from its
	 * own database.
	 *
	 * Cached like the other tenant reads, so drawing a page does not depend on ten
	 * databases answering.
	 *
	 * @param string $category e.g. `general`, `contact`, `seo`.
	 * @return array Empty when the tenant database cannot be reached or lacks the category.
	 */
	public function getTenantSettings($category)
	{
		return Yii::$app->cache->getOrSet(['workspace-settings', $this->uuid, $category], function () use ($category) {
			if ($db = $this->getWorkspaceDb()) {
				try {
					$blob = (new Query())
						->select('setting')
						->from('{{%setting}}')
						->where(['name' => $category, 'user_id' => null, 'deleted' => static::NO])
						->scalar($db);
					$settings = $blob ? @unserialize($blob) : null;
					if (is_array($settings)) {
						return $settings;
					}
				} catch (\Exception $e) {
					Yii::warning("Workspace settings unavailable for {$this->domain}: {$e->getMessage()}", __METHOD__);
				}
			}

			return [];
		}, 600);
	}

	/**
	 * The workspace's subscriptions counted per status, read live from the tenant
	 * database, shaped for the flot pie the dashboard uses (label / data / color, the
	 * status palette of the Subscription model — identical in both apps). INCOMPLETE
	 * is left out: those rows are checkout shells, not subscriptions anybody holds.
	 *
	 * Cached briefly, so the workspace view does not depend on the tenant answering;
	 * an unreachable database charts as empty.
	 *
	 * @return array[]
	 */
	public function getSubscriptionStatusStats()
	{
		return Yii::$app->cache->getOrSet(['workspace-subscription-statuses', $this->uuid], function () {
			$db = $this->getWorkspaceDb();
			if (!$db) {
				return [];
			}

			try {
				$totals = (new Query())
					->select(['status', 'total' => 'COUNT(*)'])
					->from('{{%subscription}}')
					->where(['deleted' => static::NO])
					->andWhere(['!=', 'status', Subscription::STATUS_INCOMPLETE])
					->groupBy('status')
					->indexBy('status')
					->all($db);
			} catch (\Exception $e) {
				Yii::warning("Workspace subscription stats unavailable for {$this->domain}: {$e->getMessage()}", __METHOD__);

				return [];
			}

			if (!$totals) {
				return [];
			}

			$data = [];
			foreach (Subscription::getStatusLabels() as $status => $labels) {
				if ($status == Subscription::STATUS_INCOMPLETE) {
					continue;
				}
				$data[] = [
					'label' => $labels['label'],
					'data' => (int) ($totals[$status]['total'] ?? 0),
					'color' => $labels['hexColor'],
				];
			}

			return $data;
		}, 300);
	}

	/**
	 * How much is live on this workspace: the announcements it publishes and the people
	 * subscribed to it. Read from the tenant's own database.
	 *
	 * Cached, because drawing a page must not depend on ten tenant databases answering:
	 * a workspace whose database is unreachable reports nulls and the card simply omits
	 * the counters.
	 *
	 * @return array{announcements: int|null, subscribers: int|null}
	 */
	public function getStats()
	{
		return Yii::$app->cache->getOrSet(['workspace-stats', $this->uuid], function () {
			$empty = ['announcements' => null, 'subscribers' => null];
			$db = $this->getWorkspaceDb();
			if (!$db) {
				return $empty;
			}

			try {
				return [
					'announcements' => (int) (new Query())
						->from('{{%announcement}}')
						->where(['status' => static::STATUS_ACTIVE, 'deleted' => static::NO])
						->count('*', $db),
					'subscribers' => (int) (new Query())
						->from('{{%subscriber}}')
						->where(['deleted' => static::NO])
						->count('*', $db),
				];
			} catch (\Exception $e) {
				Yii::warning("Workspace stats unavailable for {$this->domain}: {$e->getMessage()}", __METHOD__);

				return $empty;
			}
		}, 600);
	}

	/**
	 * The subscription packages this workspace sells, read from its own database with the
	 * three countable features flattened onto each row (announcements, promotions and the
	 * promotion duration). Active and not deleted only — what a visitor could buy today.
	 *
	 * @return array[] name, price, currency, billing_period, billing_cycle, type,
	 *                 recommended, announcements, promotions, promotion_days.
	 */
	public function getPackages()
	{
		return Yii::$app->cache->getOrSet(['workspace-packages', $this->uuid, Yii::$app->language], function () {
			$db = $this->getWorkspaceDb();
			if (!$db) {
				return [];
			}

			try {
				return (new Query())
					->select([
						'name' => 'pt.name',
						'content' => 'pt.content',
						'price' => 'p.price',
						'currency' => 'p.currency',
						'billing_period' => 'p.billing_period',
						'billing_cycle' => 'p.billing_cycle',
						'type' => 'p.type',
						'recommended' => 'p.recommended',
						'announcements' => new Expression("MAX(CASE WHEN pf.[[name]] = 'announcements' THEN pf.[[value]] END)"),
						'promotions' => new Expression("MAX(CASE WHEN pf.[[name]] = 'promotions' THEN pf.[[value]] END)"),
						'promotion_days' => new Expression("MAX(CASE WHEN pf.[[name]] = 'promotion_days' THEN pf.[[value]] END)"),
					])
					->from(['p' => '{{%package}}'])
					->innerJoin(['pt' => '{{%package_translation}}'], 'pt.[[package_id]] = p.[[id]] AND pt.[[language_id]] = :language', [':language' => Yii::$app->language])
					->leftJoin(['pf' => '{{%package_feature}}'], 'pf.[[package_id]] = p.[[id]] AND pf.[[deleted]] = 0')
					->where(['p.status' => static::STATUS_ACTIVE, 'p.deleted' => static::NO])
					->groupBy(['p.id', 'pt.name', 'pt.content', 'p.price', 'p.currency', 'p.billing_period', 'p.billing_cycle', 'p.type', 'p.recommended'])
					->orderBy(['p.price' => SORT_ASC])
					->all($db);
			} catch (\Exception $e) {
				Yii::warning("Workspace packages unavailable for {$this->domain}: {$e->getMessage()}", __METHOD__);

				return [];
			}
		}, 600);
	}

	/**
	 * The packages to show on the public site. Every workspace is meant to sell the same
	 * ones, so the first installed workspace that has any is the source — a workspace
	 * whose catalog is still empty (a fresh install) simply hands over to the next.
	 *
	 * @return array[] See [[getPackages()]].
	 */
	public static function findShowcasePackages()
	{
		foreach (static::findInstalledWorkspaces() as $workspace) {
			if ($packages = $workspace->getPackages()) {
				return $packages;
			}
		}

		return [];
	}

	/**
	 * The workspaces to show publicly: installed, active and not deleted, ordered by domain.
	 *
	 * @return static[]
	 */
	public static function findInstalledWorkspaces()
	{
		return static::find()
			->where([
				'status' => static::STATUS_ACTIVE,
				'deleted' => static::NO,
			])
			->andWhere(['not', ['url' => null]])
			->andWhere(['<>', 'url', ''])
			->orderBy(['domain' => SORT_ASC])
			->all();
	}

	/**
	 * Builds the map of shared @workspace directories that are linked into the tenant
	 * directory (source => destination). Used both when installing (to create the
	 * directory junctions) and when uninstalling (to remove them safely).
	 *
	 * Includes the shared source directories resolved at runtime via @app/... (modules,
	 * views) and the static web assets served from the tenant document root.
	 *
	 * @return array
	 */
	public function getSymlinkMap(): array
	{
		$dirPath = $this->getDirectoryPath();
		if (!$dirPath) {
			return [];
		}

		$relativePaths = [
			// Shared source directories referenced at runtime through @app/...
			'frontend/modules',
			'frontend/views',
			'backend/modules',
			'backend/views',
			// Static web assets served directly from the tenant document root
			'backend/web/assets',
			'backend/web/audio',
			'backend/web/img',
			'backend/web/css',
			'backend/web/js',
			'frontend/web/assets',
			'frontend/web/img',
			'frontend/web/css',
			'frontend/web/fonts',
			'frontend/web/plugins',
			'frontend/web/js',
			// Visual theme (tpl/<theme>/css|img) referenced by AppAsset and the views;
			// without it the theme styles 404 and the UI renders unstyled.
			'frontend/web/tpl',
		];

		$map = [];
		foreach ($relativePaths as $relativePath) {
			$source = Yii::getAlias("@workspace/{$relativePath}");
			// The shop workspace app ships fewer shared directories than the marketplace
			// one (e.g. no frontend/web/tpl theme); linking a missing source would only
			// leave a dangling symlink in the tenant.
			if (!is_dir($source)) {
				continue;
			}
			$map[$source] = "{$dirPath}/{$relativePath}";
		}

		return $map;
	}

	/**
	 * Installs the Workspace directory.
	 *
	 * @return bool
	 */
	/**
	 * Ensures the workspace has a unique API token (the Bearer secret the tenant uses to
	 * publish announcements to the master hub). Generates and persists one if missing.
	 *
	 * @return string the token
	 */
	public function ensureApiToken()
	{
		if (!$this->api_token) {
			do {
				$token = Yii::$app->security->generateRandomString(64);
			} while (static::find()->where(['api_token' => $token])->exists());
			$this->updateAttributes(['api_token' => $token]);
		}
		return $this->api_token;
	}

	/**
	 * The master hub URL a tenant posts its announcements to.
	 *
	 * @return string
	 */
	public static function getMasterApiUrl()
	{
		return Yii::$app->params['masterApi.url'] ?? '';
	}

	/**
	 * Regenerates the tenant's common/config/main.php from the template, so an already-installed
	 * workspace gets the master hub api config (masterApi.url + its api_token) written into it.
	 * Same output as a fresh install; safe to run repeatedly. Used to backfill existing tenants.
	 *
	 * @return bool
	 */
	public function writeTenantApiConfig()
	{
		$file = $this->getDirectoryPath() . '/common/config/main.php';
		$template = Yii::getAlias('@workspace/install/dir/common/config/main.php');
		if (!is_file($file) || !is_file($template)) {
			return false;
		}
		$content = strtr(file_get_contents($template), $this->getConfigPlaceholders());
		return file_put_contents($file, $content) !== false;
	}

	/**
	 * The tenant apps whose config/main.php is generated from install/dir.
	 */
	const TENANT_APPS = ['api', 'backend', 'frontend', 'console'];

	/**
	 * Gives an already-installed workspace its own common/config/params-local.php, and
	 * regenerates its apps' config/main.php from the templates so they load it. Same output
	 * as a fresh install. An existing params-local.php is kept as it is: it may hold values
	 * edited for this platform. Safe to run repeatedly. Used to backfill existing tenants
	 * (`php yii workspace-install/params-config`).
	 *
	 * @return bool
	 */
	public function writeTenantParamsConfig()
	{
		$dirPath = $this->getDirectoryPath();
		if (!$dirPath || !is_dir($dirPath)) {
			return false;
		}
		$placeholders = $this->getConfigPlaceholders();

		$paramsLocal = "{$dirPath}/common/config/params-local.php";
		if (!is_file($paramsLocal)) {
			$template = Yii::getAlias('@workspace/install/dir/common/config/params-local.php');
			if (!is_file($template) || file_put_contents($paramsLocal, strtr(file_get_contents($template), $placeholders)) === false) {
				return false;
			}
		}

		foreach (static::TENANT_APPS as $app) {
			$file = "{$dirPath}/{$app}/config/main.php";
			$template = Yii::getAlias("@workspace/install/dir/{$app}/config/main.php");
			if (is_file($file) && is_file($template) && file_put_contents($file, strtr(file_get_contents($template), $placeholders)) === false) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The values of the install/dir config templates' placeholders, for this workspace.
	 *
	 * @return array placeholder => value
	 */
	public function getConfigPlaceholders()
	{
		$db = static::getDb();
		// On cPanel the workspace is served from its addon domain's document root, so the
		// app baseUrls lose the "/<url>" path prefix ("" / "/admin" / "/api"). Without
		// cPanel the workspace is served as a path under the master domain and keeps it.
		$baseUrlPrefix = $this->isCPanelConfigured() ? '' : '/' . $this->url;
		// The Facebook values land inside single-quoted PHP strings in params-local.php.
		$quote = function ($value) {
			return addcslashes((string) $value, "'\\");
		};
		$params = Yii::$app->params;

		// strtr tries the longest keys first, so "/{{URL}}" wins over "{{URL}}"
		// inside the baseUrl templates while "{{URL}}" still covers plain uses.
		return [
			'{{DB_HOST}}' => DbHelper::getDsnAttribute('host', $db) ?: 'localhost',
			'{{DB_NAME}}' => $this->getWorkspaceDbName(),
			'{{DB_USERNAME}}' => $db->username,
			'{{DB_PASSWORD}}' => $db->password,
			// The canonical UUID string, never the raw BINARY(16) bytes
			'{{ID}}' => $this->uuid,
			'{{NAME}}' => $this->code,
			'/{{URL}}' => $baseUrlPrefix,
			'{{URL}}' => $this->url,
			// Master hub API config.
			'{{API_TOKEN}}' => $this->api_token,
			'{{MASTER_API_URL}}' => static::getMasterApiUrl(),
			// The Facebook app every platform connects its page through (params-local.php).
			'{{FACEBOOK_APP_ID}}' => $quote($params['facebook.appId'] ?? ''),
			'{{FACEBOOK_APP_SECRET}}' => $quote($params['facebook.appSecret'] ?? ''),
			'{{FACEBOOK_CONFIG_ID}}' => $quote($params['facebook.configId'] ?? ''),
			'{{FACEBOOK_GRAPH_VERSION}}' => $quote($params['facebook.graphVersion'] ?? 'v26.0'),
			// The app visitors log in with ("Log in with Facebook"); empty = the page app.
			'{{FACEBOOK_LOGIN_APP_ID}}' => $quote($params['facebook.loginAppId'] ?? ''),
			'{{FACEBOOK_LOGIN_APP_SECRET}}' => $quote($params['facebook.loginAppSecret'] ?? ''),
		];
	}

	protected function installDirectory()
	{
		$dirPath = $this->getDirectoryPath();

		try {
			FileHelper::createDirectory($dirPath, 0755);

			// The vertical overlays (install/dir/<type>/) are not part of the shared
			// skeleton — exclude every known type directory from the base copy. The
			// pattern is deliberately unanchored ("1/", not "/1/"): this Yii build's
			// matchPathname never matches anchored directory patterns at the copy root.
			$typeExcludes = array_map(function ($type) {
				return "{$type}/";
			}, array_keys(static::getTypeLabels()));
			FileHelper::copyDirectory(Yii::getAlias("@workspace/install/dir"), $dirPath, [
				'dirMode' => 0755,
				'except' => $typeExcludes,
			]);

			// Vertical-specific directory overlay (install/dir/<type>/), the filesystem
			// counterpart of the install/db/<type>/ seeds: whatever it contains is copied
			// over the skeleton just laid down — e.g. install/dir/<type>/uploads/ ships
			// the seed images (packages, carousels) the type's SQL seeds reference.
			$typeDirPath = Yii::getAlias("@workspace/install/dir/{$this->type}");
			if (is_dir($typeDirPath)) {
				FileHelper::copyDirectory($typeDirPath, $dirPath, ['dirMode' => 0755]);
			}

			// Link the shared source/asset directories from the @workspace app into the tenant.
			FileHelper::symlink($this->getSymlinkMap());

			// Update the configuration files, this platform's params-local.php included
			$filePaths = [
				"{$dirPath}/common/config/main.php",
				"{$dirPath}/common/config/params-local.php",
				"{$dirPath}/api/config/main.php",
				"{$dirPath}/backend/config/main.php",
				"{$dirPath}/frontend/config/main.php",
				"{$dirPath}/console/config/main.php",
			];
			$placeholders = $this->getConfigPlaceholders();

			foreach ($filePaths as $filePath) {
				if (is_file($filePath)) {
					file_put_contents($filePath, strtr(file_get_contents($filePath), $placeholders));
				}
			}

			return true;
		} catch (\Exception $e) {
			Yii::error(['message' => $e->getMessage(), 'workspace' => $this->code, 'exception' => (string) $e], __METHOD__);
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * Updates the crontab file.
	 *
	 * @param bool $remove
	 * @return bool
	 */
	protected function updateCrontab($remove = false)
	{
		if (!$this->isCPanelConfigured()) {
			return true;
		}

		$cronJob = [
			'minute' => '*',
			'hour' => '*',
			'day' => '*',
			'month' => '*',
			'weekday' => '*',
			'command' => "/usr/local/bin/php " . $this->getDirectoryPath() . "/yii schedule/run >/dev/null 2>&1",
		];

		if ($remove === false) {
			Yii::$app->cPanel->api2->Cron->add_line($cronJob);
		} else {
			$response = Yii::$app->cPanel->api2->Cron->fetchcron();
			if ($response && is_array($response['cpanelresult']['data'])) {
				foreach ($response['cpanelresult']['data'] as $line) {
					if ($line['type'] == 'command' && $line['command'] == $cronJob['command']) {
						Yii::$app->cPanel->api2->Cron->remove_line(['linekey' => $line['linekey']]);
						break;
					}
				}
			}
		}

		return true;
	}

	/**
	 * Updates the root .htaccess file.
	 *
	 * @param bool $remove
	 * @return bool
	 */
	protected function updateHtaccess($remove = false)
	{
		$htaccess = \tws\textfile\TextFile::load(Yii::getAlias('@base/.htaccess'));
		$target = $this->getRelativeDirectoryPath();
		$line = "\tRewriteRule ^{$this->url}/?(.*)$ {$target}/$1 [NC,L]";

		if ($remove === true) {
			return $htaccess->deleteLine($line);
		}

		// Delete similar rewrite rules with the same workspace directory
		if ($workspaceLines = $htaccess->getLines("{$target}/$1")) {
			$htaccess->deleteLines($workspaceLines);
		}

		// The rule goes at the top of the Workspace Rules block. This used to branch on
		// Workspace::findOne(['LIKE', 'url', $this->url]) — an operator-format condition
		// handed to findOne(), which Yii reads as a list of primary key values and answers
		// with null every time, so the second branch has never run and reading ->url on
		// that null was a notice on PHP 7.4 and a fatal error from PHP 8 on. The block
		// order in the shipped .htaccess (newest tenant first) is what this produced.
		return $htaccess
			->addLine($line)
			->afterLine('# BEGIN Workspace Rules')
			->save();
	}

	/**
	 * Installs the Workspace database and its directory structure.
	 *
	 * @return bool
	 */
	public function install($reinstall = false)
	{
		try {
			if ($reinstall) {
				if (!$this->uninstall()) {
					throw new \Exception('Cannot reinstall the workspace.');
				}
			}
			// Ensure the workspace has its API token before the tenant config is written,
			// so it can be injected into the tenant's params (used to authenticate to master).
			if (!$this->ensureApiToken()) {
				throw new \Exception('Cannot generate the workspace API token.');
			}
			if (!$this->installDatabase()) {
				throw new \Exception('Cannot create the workspace database.');
			}
			if (!$this->installDirectory()) {
				throw new \Exception('Cannot create the workspace directory.');
			}
			if (!$this->ensureCpanelAddonDomain()) {
				throw new \Exception('Cannot create the cPanel addon domain.');
			}
			if (!$this->updateCrontab()) {
				throw new \Exception('Cannot update the crontab file.');
			}
			if (!$this->updateHtaccess()) {
				throw new \Exception('Cannot update the .htaccess file.');
			}
			return true;
		} catch (\Exception $e) {
			Yii::error(['message' => $e->getMessage(), 'workspace' => $this->code, 'modelErrors' => $this->errors, 'exception' => (string) $e], __METHOD__);
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * Uninstalls the Workspace database and its directory structure.
	 *
	 * @return bool
	 */
	public function uninstall()
	{
		try {
			if (!$this->isCPanelConfigured()) {
				static::getDb()->createCommand("DROP DATABASE IF EXISTS {$this->getWorkspaceDbName()}")->execute();
			} else {
				$response = Yii::$app->cPanel->uapi->Mysql->delete_database(['name' => $this->getWorkspaceDbName()]);
				$this->logCpanelUapiFailure('Mysql::delete_database', $response);
			}

			if (!$this->updateCrontab(true)) {
				throw new \Exception('Cannot update the crontab file.');
			}
			if (!$this->updateHtaccess(true)) {
				throw new \Exception('Cannot update the .htaccess file.');
			}

			// Remove the directory junctions first so the recursive delete below never
			// traverses into the shared @workspace sources. On Windows PHP is_link()
			// does not detect junctions, so FileHelper::removeDirectory() would otherwise
			// follow them; rmdir() removes the junction itself without touching the target.
			foreach (array_values($this->getSymlinkMap()) as $linkPath) {
				if (is_dir($linkPath)) {
					@rmdir($linkPath);
				}
			}

			FileHelper::removeDirectory($this->getDirectoryPath());
			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * Ensures the cPanel addon domain exists for this workspace.
	 * The addon domain name is the workspace url (e.g. "scp1").
	 * No-op when the cPanel component is missing or not configured.
	 *
	 * @return bool
	 */
	protected function ensureCpanelAddonDomain(): bool
	{
		// On non-cPanel hosts (e.g. local dev) routing is handled by the root .htaccess
		// rewrite rule instead of an addon domain, so this is a safe no-op. We use
		// isCPanelConfigured() rather than has('cPanel') because the component is always
		// declared in the config and accessing it with placeholder values throws on init().
		if (!$this->isCPanelConfigured()) {
			return true;
		}

		$cpanel   = Yii::$app->cPanel;
		$baseUrl  = rtrim((string) ($cpanel->baseUrl ?? ''), '/');
		$username = (string) ($cpanel->username ?? '');
		$password = (string) ($cpanel->password ?? '');
		$apiToken = (string) ($cpanel->apiToken ?? '');

		// No usable credentials, or the CPANEL_* placeholders a dev environment ships
		// with: the workspace is served through the root .htaccess instead, so there is
		// nothing to create here.
		if (empty($baseUrl) || empty($username) || (empty($password) && empty($apiToken))) {
			return true;
		}
		foreach ([$baseUrl, $username, $password, $apiToken] as $value) {
			if (stripos($value, 'CPANEL_') !== false) {
				return true;
			}
		}

		// The addon domain is the workspace `domain` column — a real registrable name
		// with a TLD (e.g. "eautomarket.ro"). The `url` column is only the path slug
		// ("eautomarketro"), which cPanel rejects ("must have a valid TLD label").
		$addonDomain = strtolower(trim((string) $this->domain));
		if ($addonDomain === '' || strpos($addonDomain, '.') === false) {
			$this->addError('', Yii::t('common', 'The workspace domain must be a full domain name (with TLD) to create the cPanel addon domain.'));
			return false;
		}

		$existingDomains = array_map('strtolower', $this->getCpanelAddonDomains());
		if (in_array($addonDomain, $existingDomains, true)) {
			return true;
		}

		$dirPath = $this->getCpanelDirectoryPath();
		if (empty($dirPath)) {
			$this->addError('', Yii::t('common', 'Missing cPanel document root.'));
			return false;
		}

		// API2 AddonDomain::addaddondomain parameters:
		// - newdomain: the real domain with extension (workspace domain, e.g. "eautomarket.ro")
		// - subdomain: the addon domain name without extension (e.g. "eautomarket")
		// - dir: document root
		// - pass: password for the addon domain FTP user
		// `pass` is the password of the FTP user cPanel creates alongside the addon
		// domain. It used to be the cPanel ACCOUNT password, which API2 then carried in
		// the request query string — straight into the logs and, on failure, into the
		// error shown in the backend. Nothing reads this account afterwards, so a fresh
		// random secret does the job without putting the real one on the wire.
		$payload = [
			'newdomain' => $addonDomain,
			'subdomain' => $this->getCpanelSubdomain(),
			'dir'       => $dirPath,
			'pass'      => Yii::$app->security->generateRandomString(24) . 'aA1!',
		];

		$response = $this->cpanelRequestApi2('AddonDomain', 'addaddondomain', $payload);

		// API2 signals two layers: event.result says the call executed, while the
		// function's own outcome is in data[0].result/reason — a failed creation
		// (domain limit, disabled feature, invalid domain) still has event.result = 1.
		$cpRes    = $response['cpanelresult'] ?? [];
		$event    = $cpRes['event'] ?? [];
		$data     = $cpRes['data'][0] ?? [];
		$ok       = (int)($event['result'] ?? 0) === 1 && (int)($data['result'] ?? 0) === 1;
		$errText  = (string)($cpRes['error'] ?? '');
		if ($errText === '' && (int)($data['result'] ?? 0) !== 1) {
			$errText = (string)($data['reason'] ?? '');
		}

		if (!$ok) {
			$error = $errText !== '' ? $errText : Yii::t('common', 'Unknown cPanel error.');

			Yii::error([
				'message'  => 'cPanel addon domain create failed (API2).',
				'workspace' => $this->url,
				'payload'  => $payload,
				'response' => $response,
			], 'cpanel');

			$this->addError('', $error);
			return false;
		}

		return true;
	}

	/**
	 * Executes a cPanel API2 request.
	 *
	 * @param string $module
	 * @param string $func
	 * @param array $params
	 * @return array|null
	 */
	protected function cpanelRequestApi2(string $module, string $func, array $params = []): ?array
	{
		$cpanel   = Yii::$app->cPanel;
		$baseUrl  = rtrim((string) ($cpanel->baseUrl ?? ''), '/');
		$username = (string) ($cpanel->username ?? '');
		$authorization = $this->getCpanelAuthorization();
		if ($authorization === null) {
			return null;
		}

		$query = array_merge([
			'cpanel_jsonapi_user'       => $username,
			'cpanel_jsonapi_apiversion' => 2,
			'cpanel_jsonapi_module'     => $module,
			'cpanel_jsonapi_func'       => $func,
		], $params);

		$url = $baseUrl . '/json-api/cpanel?' . http_build_query($query);

		$client = new \GuzzleHttp\Client(['verify' => false]);

		try {
			// http_errors off on purpose: cPanel answers a refusal with 403 AND a JSON
			// body that names the reason, which the callers already read. Letting Guzzle
			// throw instead would replace that with an exception whose message quotes the
			// full request URL, secrets included.
			$res = $client->get($url, [
				'headers' => [
					'Authorization' => $authorization,
					'Accept' => 'application/json',
				],
				'http_errors' => false,
			]);
		} catch (\Throwable $e) {
			Yii::error([
				'message' => 'cPanel API2 request failed.',
				'workspace' => $this->code,
				'module' => $module,
				'func' => $func,
				'error' => static::redactCpanelSecrets($e->getMessage()),
			], 'cpanel');

			return null;
		}

		$json = json_decode((string)$res->getBody(), true);

		return is_array($json) ? $json : null;
	}

	/**
	 * The Authorization header value for a direct cPanel call, or null when the component
	 * holds nothing usable. An API token is preferred over the account password: it is the
	 * only thing that works for an account with two-factor authentication, and it keeps
	 * the real password out of the request.
	 *
	 * @return string|null
	 */
	protected function getCpanelAuthorization(): ?string
	{
		$cpanel = Yii::$app->cPanel;

		$token = (string) ($cpanel->apiToken ?? '');
		$username = (string) ($cpanel->username ?? '');
		$password = (string) ($cpanel->password ?? '');

		if (empty($username)) {
			return null;
		}
		if (!empty($token)) {
			return "cpanel {$username}:{$token}";
		}
		if (!empty($password)) {
			return 'Basic ' . base64_encode("{$username}:{$password}");
		}

		return null;
	}

	/**
	 * Masks the credentials cPanel expects as query parameters, so a request URL can
	 * be logged or shown without handing out the password with it.
	 *
	 * @param string $text
	 * @return string
	 */
	protected static function redactCpanelSecrets(string $text): string
	{
		return (string) preg_replace('/\b(pass|password)=[^&\s"\']*/i', '$1=***', $text);
	}

	/**
	 * Fetches the existing addon domains from cPanel.
	 *
	 * @return array
	 */
	protected function getCpanelAddonDomains(): array
	{
		$response = $this->cpanelRequest('DomainInfo/list_domains');
		if (!$response || (int)($response['status'] ?? 0) !== 1) {
			Yii::error([
				'message' => 'cPanel list domains failed.',
				'workspace' => $this->url,
				'response' => $response,
			], 'cpanel');
			return [];
		}

		$addons = $response['data']['addon_domains'] ?? [];
		if (!is_array($addons)) {
			return [];
		}

		return array_values(array_filter($addons));
	}

	/**
	 * Executes a cPanel UAPI request.
	 *
	 * @param string $endpoint
	 * @param array $params
	 * @return array|null
	 */
	protected function cpanelRequest(string $endpoint, array $params = []): ?array
	{
		$cpanel = Yii::$app->cPanel;
		$baseUrl = rtrim((string) ($cpanel->baseUrl ?? ''), '/');
		if (empty($baseUrl) || ($authorization = $this->getCpanelAuthorization()) === null) {
			return null;
		}

		$client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
		$request = $client->createRequest()
			->setMethod('GET')
			->setUrl("{$baseUrl}/execute/" . ltrim($endpoint, '/'))
			->setData($params)
			->setHeaders([
				'Authorization' => $authorization,
				'Accept' => 'application/json',
			]);

		$response = $request->send();
		if (!$response->isOk) {
			Yii::error([
				'message' => 'cPanel request failed.',
				'endpoint' => $endpoint,
				'params' => $params,
				'statusCode' => $response->getStatusCode(),
				'body' => $response->getContent(),
			], 'cpanel');
			return null;
		}

		return $response->getData();
	}

	/**
	 * Gets the cPanel document root for the workspace: public_html/workspaces/<domain>.
	 * The domain is the workspace `domain` column (the addon domain name).
	 *
	 * @return string
	 */
	protected function getCpanelDirectoryPath(): string
	{
		$domain = strtolower($this->domain ?: $this->url);
		if (empty($domain)) {
			return '';
		}

		return 'public_html/' . $this->getRelativeDirectoryPath();
	}

	/**
	 * Gets the root host used for the internal cPanel subdomain.
	 *
	 * @return string
	 */
	protected function getCpanelRootDomain(): string
	{
		$generalSettings = Yii::$app->settings->getCategory('general');
		$host = parse_url($generalSettings['hostInfo'] ?? '', PHP_URL_HOST) ?: '';
		$host = preg_replace('/^www\./i', '', $host);

		return $host;
	}

	/**
	 * Gets the cPanel addon domain name (the name without extension), e.g.
	 * "eautomarket" for the domain "eautomarket.ro".
	 *
	 * @return string
	 */
	protected function getCpanelSubdomain(): string
	{
		$domain = strtolower($this->domain ?: $this->url);

		return explode('.', $domain, 2)[0];
	}
	//endregion Workspace Config
}
