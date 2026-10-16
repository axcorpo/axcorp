<?php

namespace console\controllers;

use common\helpers\UuidHelper;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Connection;
use yii\db\Query;
use yii\helpers\Console;

/**
 * Converts existing INT AUTO_INCREMENT primary keys to UUID v7 stored as BINARY(16).
 *
 * The conversion is driven entirely by `information_schema`:
 *  - every table whose primary key is a single integer `id` column is a candidate;
 *  - every column with a declared foreign key to a converted table is remapped;
 *  - well-known undeclared references are remapped through heuristics
 *    (`created_by` / `updated_by` / `deleted_by` -> `user`, `parent_id` -> same table,
 *    `<table>_id` -> `<table>`) plus the explicit [[$map]] entries;
 *  - string reference columns (e.g. `auth_assignment.user_id` VARCHAR) keep their type
 *    and are remapped in place to the canonical UUID string.
 *
 * Usage:
 * ```
 * php yii uuid-convert/plan            # read-only: shows what would be converted
 * php yii uuid-convert/run             # converts every candidate table
 * php yii uuid-convert/run user,order  # converts only the given tables
 * ```
 *
 * ALWAYS take a full backup before `run`. The command keeps every index and foreign
 * key (recreated on the new BINARY(16) columns with the same names and rules), but it
 * rewrites primary keys in place and cannot be rolled back without a backup.
 *
 * UUIDs are generated in PHP (UUID v7, time-ordered), never with MySQL's `UUID()`.
 */
class UuidConvertController extends Controller
{
	/**
	 * @var bool Print the DDL of the swap phase without executing anything.
	 */
	public $dryRun = false;

	/**
	 * @var string Comma-separated table names to exclude. Framework/vendor-owned tables
	 * whose code expects integer keys are excluded by default.
	 */
	public $exclude = 'migration,language_source,language_translate,session,cache,mutex,queue';

	/**
	 * @var array Explicit `referencing_table.column => referenced_table` mappings for
	 * references that cannot be derived from constraints or naming conventions.
	 */
	public $map = [
		// Item::getSubscription(): hasOne(Subscription::class, ['id' => 'item_id']) — the
		// `<table>_id` heuristic would wrongly remap it against `item`.
		'item.item_id' => 'subscription',
	];

	/**
	 * @var int Number of rows per UPDATE batch while backfilling UUIDs.
	 */
	public $batchSize = 500;

	/**
	 * @inheritdoc
	 */
	public function options($actionID)
	{
		return array_merge(parent::options($actionID), ['dryRun', 'exclude', 'batchSize']);
	}

	/**
	 * @inheritdoc
	 */
	public function optionAliases()
	{
		return array_merge(parent::optionAliases(), ['n' => 'dryRun']);
	}

	/**
	 * Shows the conversion plan without touching the database.
	 *
	 * @param string|null $tables Comma-separated table names (defaults to every candidate).
	 * @return int
	 */
	public function actionPlan($tables = null)
	{
		$plan = $this->buildPlan($tables);
		$this->printPlan($plan);
		return ExitCode::OK;
	}

	/**
	 * Converts the given tables (or every candidate) to BINARY(16) UUID v7 primary keys.
	 *
	 * @param string|null $tables Comma-separated table names (defaults to every candidate).
	 * @return int
	 */
	public function actionRun($tables = null)
	{
		$plan = $this->buildPlan($tables);
		$this->printPlan($plan);

		if (empty($plan['tables'])) {
			$this->stdout("Nothing to convert.\n", Console::FG_YELLOW);
			return ExitCode::OK;
		}

		if (!$this->dryRun) {
			$this->stdout("\nThis will REWRITE the primary keys of the tables above and cannot be undone.\n", Console::FG_RED);
			if (!$this->confirm('Did you take a full backup and do you want to continue?')) {
				return ExitCode::OK;
			}
		}

		$db = $this->getDb();

		// Phase 1: add + backfill `id__uuid` on every converted table
		foreach (array_keys($plan['tables']) as $table) {
			$this->backfillTableUuid($table);
		}

		// Phase 2: backfill the referencing columns
		foreach ($plan['references'] as $reference) {
			$this->backfillReference($reference);
		}

		// Phase 3: swap columns, primary keys, indexes and foreign keys
		$statements = $this->buildSwapStatements($plan);
		if ($this->dryRun) {
			$this->stdout("\n-- Swap phase DDL (dry run):\n", Console::FG_CYAN);
			foreach ($statements as $sql) {
				$this->stdout($sql . ";\n");
			}
			return ExitCode::OK;
		}

		$this->stdout("\nSwapping columns, primary keys, indexes and foreign keys...\n", Console::FG_CYAN);
		$db->createCommand('SET FOREIGN_KEY_CHECKS = 0')->execute();
		try {
			foreach ($statements as $sql) {
				$this->stdout("  {$sql}\n");
				$db->createCommand($sql)->execute();
			}
		} finally {
			$db->createCommand('SET FOREIGN_KEY_CHECKS = 1')->execute();
		}

		$db->getSchema()->refresh();
		$this->stdout("\nDone. Remember to flush the application caches (php yii cache/flush-all)\n", Console::FG_GREEN);
		$this->stdout("and to log everyone out: sessions and \"remember me\" cookies hold the old integer ids.\n", Console::FG_GREEN);

		return ExitCode::OK;
	}

	/**
	 * Builds the conversion plan from information_schema.
	 *
	 * @param string|null $tables
	 * @return array `['tables' => [name => meta], 'references' => [meta], 'warnings' => [string]]`
	 */
	protected function buildPlan($tables = null)
	{
		$db = $this->getDb();
		$schemaName = $this->getSchemaName();
		$exclude = array_filter(array_map('trim', explode(',', $this->exclude)));

		// Candidate tables: single-column integer primary key named `id`
		$candidates = (new Query())
			->select(['c.TABLE_NAME', 'c.COLUMN_TYPE', 'c.EXTRA'])
			->from(['c' => 'information_schema.COLUMNS'])
			->innerJoin(['t' => 'information_schema.TABLES'], 't.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME')
			->where([
				'c.TABLE_SCHEMA' => $schemaName,
				'c.COLUMN_NAME' => 'id',
				'c.COLUMN_KEY' => 'PRI',
				't.TABLE_TYPE' => 'BASE TABLE',
			])
			->andWhere(['in', 'c.DATA_TYPE', ['tinyint', 'smallint', 'mediumint', 'int', 'bigint']])
			->all($db);

		$plannedTables = [];
		foreach ($candidates as $row) {
			$table = $row['TABLE_NAME'];
			if (in_array($table, $exclude, true)) {
				continue;
			}
			// Skip composite primary keys (the `id` column must be the whole key)
			if (count($this->getPrimaryKeyColumns($table)) !== 1) {
				continue;
			}
			$plannedTables[$table] = [
				'columnType' => $row['COLUMN_TYPE'],
				'autoIncrement' => stripos($row['EXTRA'], 'auto_increment') !== false,
			];
		}

		if ($tables !== null) {
			$requested = array_filter(array_map('trim', explode(',', $tables)));
			$unknown = array_diff($requested, array_keys($plannedTables));
			if ($unknown) {
				throw new \yii\console\Exception('Not convertible (missing, excluded or no single integer `id` PK): ' . implode(', ', $unknown));
			}
			$plannedTables = array_intersect_key($plannedTables, array_flip($requested));
		}

		return [
			'tables' => $plannedTables,
			'references' => $this->findReferences(array_keys($plannedTables)),
			'schema' => $schemaName,
		];
	}

	/**
	 * Finds every column that references a converted table, via declared foreign keys,
	 * naming heuristics and the explicit [[$map]].
	 *
	 * @param string[] $targets Converted table names.
	 * @return array Each item: table, column, target, type (int|string), nullable, declaredFk.
	 */
	protected function findReferences(array $targets)
	{
		if (!$targets) {
			return [];
		}
		$db = $this->getDb();
		$schemaName = $this->getSchemaName();
		$references = [];
		$seen = [];

		// 1. Declared foreign keys
		$rows = (new Query())
			->select(['k.TABLE_NAME', 'k.COLUMN_NAME', 'k.REFERENCED_TABLE_NAME'])
			->from(['k' => 'information_schema.KEY_COLUMN_USAGE'])
			->where([
				'k.TABLE_SCHEMA' => $schemaName,
				'k.REFERENCED_TABLE_SCHEMA' => $schemaName,
				'k.REFERENCED_COLUMN_NAME' => 'id',
			])
			->andWhere(['in', 'k.REFERENCED_TABLE_NAME', $targets])
			->all($db);
		foreach ($rows as $row) {
			$key = $row['TABLE_NAME'] . '.' . $row['COLUMN_NAME'];
			$seen[$key] = true;
			$references[] = $this->describeReference($row['TABLE_NAME'], $row['COLUMN_NAME'], $row['REFERENCED_TABLE_NAME'], true);
		}

		// 2. Heuristics + explicit map for references without a declared constraint
		$columns = (new Query())
			->select(['c.TABLE_NAME', 'c.COLUMN_NAME'])
			->from(['c' => 'information_schema.COLUMNS'])
			->innerJoin(['t' => 'information_schema.TABLES'], 't.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME')
			->where(['c.TABLE_SCHEMA' => $schemaName, 't.TABLE_TYPE' => 'BASE TABLE'])
			->all($db);
		foreach ($columns as $row) {
			$table = $row['TABLE_NAME'];
			$column = $row['COLUMN_NAME'];
			$key = "{$table}.{$column}";
			if (isset($seen[$key]) || ($column === 'id' && in_array($table, $targets, true))) {
				continue;
			}

			$target = null;
			if (isset($this->map[$key])) {
				$target = $this->map[$key];
			} elseif (in_array($column, ['created_by', 'updated_by', 'deleted_by'], true)) {
				$target = 'user';
			} elseif ($column === 'parent_id') {
				$target = $table;
			} elseif (substr($column, -3) === '_id' && in_array(substr($column, 0, -3), $targets, true)) {
				$target = substr($column, 0, -3);
			}

			if ($target !== null && in_array($target, $targets, true)) {
				$seen[$key] = true;
				$references[] = $this->describeReference($table, $column, $target, false);
			}
		}

		return array_values(array_filter($references));
	}

	/**
	 * Describes a referencing column (or `null` when the column type cannot hold a reference).
	 *
	 * @param string $table
	 * @param string $column
	 * @param string $target
	 * @param bool $declaredFk
	 * @return array|null
	 */
	protected function describeReference($table, $column, $target, $declaredFk)
	{
		$row = (new Query())
			->select(['DATA_TYPE', 'IS_NULLABLE', 'COLUMN_TYPE'])
			->from('information_schema.COLUMNS')
			->where(['TABLE_SCHEMA' => $this->getSchemaName(), 'TABLE_NAME' => $table, 'COLUMN_NAME' => $column])
			->one($this->getDb());
		if (!$row) {
			return null;
		}
		if (in_array($row['DATA_TYPE'], ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'], true)) {
			$type = 'int';
		} elseif (in_array($row['DATA_TYPE'], ['char', 'varchar'], true)) {
			$type = 'string';
		} else {
			return null;
		}
		return [
			'table' => $table,
			'column' => $column,
			'target' => $target,
			'type' => $type,
			'nullable' => $row['IS_NULLABLE'] === 'YES',
			'declaredFk' => $declaredFk,
		];
	}

	/**
	 * Prints the plan summary.
	 *
	 * @param array $plan
	 */
	protected function printPlan(array $plan)
	{
		$this->stdout(sprintf("Schema: %s\n\n", $plan['schema']), Console::BOLD);
		$this->stdout(sprintf("Tables to convert to BINARY(16) UUID v7 primary keys (%d):\n", count($plan['tables'])), Console::FG_CYAN);
		foreach ($plan['tables'] as $table => $meta) {
			$this->stdout(sprintf("  %-40s id %s%s\n", $table, $meta['columnType'], $meta['autoIncrement'] ? ' AUTO_INCREMENT' : ''));
		}
		$this->stdout(sprintf("\nReferencing columns to remap (%d):\n", count($plan['references'])), Console::FG_CYAN);
		foreach ($plan['references'] as $reference) {
			$this->stdout(sprintf(
				"  %-55s -> %-25s [%s%s]\n",
				$reference['table'] . '.' . $reference['column'],
				$reference['target'] . '.id',
				$reference['declaredFk'] ? 'FK' : 'heuristic',
				$reference['type'] === 'string' ? ', string remap in place' : ''
			));
		}
	}

	/**
	 * Adds the temporary `id__uuid` column and fills it with time-ordered UUID v7 values.
	 *
	 * @param string $table
	 */
	protected function backfillTableUuid($table)
	{
		$db = $this->getDb();
		$count = (new Query())->from($table)->count('*', $db);
		$this->stdout(sprintf("Backfilling %s (%d rows)...\n", $table, $count), Console::FG_CYAN);
		if ($this->dryRun) {
			return;
		}

		if (!$this->columnExists($table, 'id__uuid')) {
			$db->createCommand("ALTER TABLE `{$table}` ADD COLUMN `id__uuid` BINARY(16) NULL AFTER `id`")->execute();
		}

		// Assign UUIDs in primary key order so the new ids keep the original insert order
		$query = (new Query())->select('id')->from($table)->where(['id__uuid' => null])->orderBy('id');
		foreach ($query->batch($this->batchSize, $db) as $rows) {
			$case = '';
			$params = [];
			$ids = [];
			foreach ($rows as $index => $row) {
				$case .= " WHEN :id{$index} THEN :uuid{$index}";
				$params[":id{$index}"] = $row['id'];
				$params[":uuid{$index}"] = UuidHelper::uuid7Bytes();
				$ids[] = $row['id'];
			}
			$db->createCommand(
				"UPDATE `{$table}` SET `id__uuid` = CASE `id`{$case} END WHERE `id` IN (" . implode(',', array_map('intval', $ids)) . ')',
				$params
			)->execute();
		}
	}

	/**
	 * Backfills one referencing column from the target table's `id__uuid`.
	 *
	 * @param array $reference
	 */
	protected function backfillReference(array $reference)
	{
		$db = $this->getDb();
		$table = $reference['table'];
		$column = $reference['column'];
		$target = $reference['target'];
		$this->stdout(sprintf("Remapping %s.%s -> %s.id...\n", $table, $column, $target), Console::FG_CYAN);
		if ($this->dryRun) {
			return;
		}

		if ($reference['type'] === 'string') {
			// String columns (e.g. auth_assignment.user_id) get the canonical UUID string in place.
			// The join compares numerically to stay collation-agnostic; the REGEXP guard keeps the
			// update idempotent (already-remapped UUID strings are never cast and matched again).
			$uuidExpression = "LOWER(CONCAT(SUBSTR(HEX(t.`id__uuid`), 1, 8), '-', SUBSTR(HEX(t.`id__uuid`), 9, 4), '-', SUBSTR(HEX(t.`id__uuid`), 13, 4), '-', SUBSTR(HEX(t.`id__uuid`), 17, 4), '-', SUBSTR(HEX(t.`id__uuid`), 21, 12)))";
			$db->createCommand(
				"UPDATE `{$table}` r INNER JOIN `{$target}` t ON CAST(r.`{$column}` AS UNSIGNED) = t.`id`"
				. " SET r.`{$column}` = {$uuidExpression}"
				. " WHERE r.`{$column}` REGEXP '^[0-9]+$'"
			)->execute();
			return;
		}

		$temporary = "{$column}__uuid";
		if (!$this->columnExists($table, $temporary)) {
			$db->createCommand("ALTER TABLE `{$table}` ADD COLUMN `{$temporary}` BINARY(16) NULL AFTER `{$column}`")->execute();
		}
		$db->createCommand(
			"UPDATE `{$table}` r INNER JOIN `{$target}` t ON r.`{$column}` = t.`id` SET r.`{$temporary}` = t.`id__uuid`"
		)->execute();

		$orphans = (new Query())
			->from($table)
			->where(['not', [$column => null]])
			->andWhere([$temporary => null])
			->count('*', $db);
		if ($orphans > 0) {
			$this->stdout(sprintf(
				"  WARNING: %d rows in %s.%s reference missing %s rows; they become NULL.\n",
				$orphans,
				$table,
				$column,
				$target
			), Console::FG_YELLOW);
		}
	}

	/**
	 * Builds the DDL for the swap phase: drop foreign keys, replace the old columns with
	 * the backfilled BINARY(16) ones, restore primary keys, indexes and foreign keys.
	 *
	 * @param array $plan
	 * @return string[]
	 */
	protected function buildSwapStatements(array $plan)
	{
		$targets = array_keys($plan['tables']);

		// Group converted columns per table: table => [old column => nullable]
		$converted = [];
		$deleteOrphans = [];
		foreach ($targets as $table) {
			$converted[$table]['id'] = false;
		}
		foreach ($plan['references'] as $reference) {
			if ($reference['type'] === 'int') {
				$orphans = $this->dryRun ? 0 : (new Query())
					->from($reference['table'])
					->where(['not', [$reference['column'] => null]])
					->andWhere(["{$reference['column']}__uuid" => null])
					->count('*', $this->getDb());
				if (in_array($reference['column'], $this->getPrimaryKeyColumns($reference['table']), true)) {
					// Primary key members must stay NOT NULL; orphan rows (references to
					// missing target rows) cannot become NULL, so they are dropped instead.
					if ($orphans > 0) {
						$this->stdout(sprintf(
							"  WARNING: deleting %d orphan rows from %s (%s references missing %s rows).\n",
							$orphans,
							$reference['table'],
							$reference['column'],
							$reference['target']
						), Console::FG_YELLOW);
						$deleteOrphans[] = "DELETE FROM `{$reference['table']}` WHERE `{$reference['column']}` IS NOT NULL AND `{$reference['column']}__uuid` IS NULL";
					}
					$converted[$reference['table']][$reference['column']] = false;
				} else {
					$converted[$reference['table']][$reference['column']] = $reference['nullable'] || $orphans > 0;
				}
			}
		}

		$dropForeignKeys = [];
		$addForeignKeys = [];
		$alterTables = [];
		$createIndexes = [];

		foreach ($converted as $table => $columns) {
			$columnNames = array_keys($columns);

			// Foreign keys touching any converted column (either side) are dropped and recreated
			foreach ($this->getForeignKeys($table, $columnNames, $targets) as $foreignKey) {
				$dropForeignKeys[] = "ALTER TABLE `{$foreignKey['table']}` DROP FOREIGN KEY `{$foreignKey['name']}`";
				$addForeignKeys[] = $foreignKey['createSql'];
			}

			$clauses = [];
			$primaryKey = $this->getPrimaryKeyColumns($table);
			$primaryKeyTouched = (bool) array_intersect($primaryKey, $columnNames);
			if ($primaryKeyTouched) {
				$clauses[] = 'DROP PRIMARY KEY';
			}
			foreach ($columns as $column => $nullable) {
				$null = $nullable ? 'NULL DEFAULT NULL' : 'NOT NULL';
				$clauses[] = "DROP COLUMN `{$column}`";
				$clauses[] = "CHANGE `{$column}__uuid` `{$column}` BINARY(16) {$null}";
			}
			if ($primaryKeyTouched) {
				$clauses[] = 'ADD PRIMARY KEY (`' . implode('`, `', $primaryKey) . '`)';
			}
			$alterTables[] = "ALTER TABLE `{$table}` " . implode(', ', $clauses);

			// Secondary indexes on converted columns disappear with the dropped column
			foreach ($this->getIndexes($table, $columnNames) as $index) {
				$createIndexes[] = $index;
			}
		}

		// De-duplicate foreign keys collected from both of their sides
		$dropForeignKeys = array_values(array_unique($dropForeignKeys));
		$addForeignKeys = array_values(array_unique($addForeignKeys));

		return array_merge($deleteOrphans, $dropForeignKeys, $alterTables, $createIndexes, $addForeignKeys);
	}

	/**
	 * Foreign keys of `$table` that involve one of `$columns`, or that point from any
	 * table to a converted target — with the DDL to recreate them.
	 *
	 * @param string $table
	 * @param string[] $columns
	 * @param string[] $targets
	 * @return array
	 */
	protected function getForeignKeys($table, array $columns, array $targets)
	{
		$rows = (new Query())
			->select([
				'k.CONSTRAINT_NAME',
				'k.TABLE_NAME',
				'k.COLUMN_NAME',
				'k.REFERENCED_TABLE_NAME',
				'k.REFERENCED_COLUMN_NAME',
				'r.UPDATE_RULE',
				'r.DELETE_RULE',
			])
			->from(['k' => 'information_schema.KEY_COLUMN_USAGE'])
			->innerJoin(
				['r' => 'information_schema.REFERENTIAL_CONSTRAINTS'],
				'r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME'
			)
			->where(['k.TABLE_SCHEMA' => $this->getSchemaName()])
			->andWhere(['not', ['k.REFERENCED_TABLE_NAME' => null]])
			->andWhere([
				'or',
				['k.TABLE_NAME' => $table, 'k.COLUMN_NAME' => $columns],
				['k.REFERENCED_TABLE_NAME' => $table, 'k.REFERENCED_COLUMN_NAME' => $columns],
			])
			->all($this->getDb());

		$foreignKeys = [];
		foreach ($rows as $row) {
			$name = $row['CONSTRAINT_NAME'];
			$foreignKeys[$name] = [
				'name' => $name,
				'table' => $row['TABLE_NAME'],
				'createSql' => sprintf(
					'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s ON UPDATE %s',
					$row['TABLE_NAME'],
					$name,
					$row['COLUMN_NAME'],
					$row['REFERENCED_TABLE_NAME'],
					$row['REFERENCED_COLUMN_NAME'],
					$row['DELETE_RULE'],
					$row['UPDATE_RULE']
				),
			];
		}
		return array_values($foreignKeys);
	}

	/**
	 * Secondary (non-PRIMARY) indexes of `$table` involving any of `$columns`,
	 * as `CREATE INDEX` DDL to run after the column swap.
	 *
	 * @param string $table
	 * @param string[] $columns
	 * @return string[]
	 */
	protected function getIndexes($table, array $columns)
	{
		$rows = (new Query())
			->select(['INDEX_NAME', 'SEQ_IN_INDEX', 'COLUMN_NAME', 'NON_UNIQUE', 'SUB_PART'])
			->from('information_schema.STATISTICS')
			->where(['TABLE_SCHEMA' => $this->getSchemaName(), 'TABLE_NAME' => $table])
			->andWhere(['!=', 'INDEX_NAME', 'PRIMARY'])
			->orderBy(['INDEX_NAME' => SORT_ASC, 'SEQ_IN_INDEX' => SORT_ASC])
			->all($this->getDb());

		$indexes = [];
		foreach ($rows as $row) {
			$indexes[$row['INDEX_NAME']]['unique'] = !$row['NON_UNIQUE'];
			$indexes[$row['INDEX_NAME']]['columns'][] = $row['COLUMN_NAME']
				. ($row['SUB_PART'] ? "({$row['SUB_PART']})" : '');
			$indexes[$row['INDEX_NAME']]['plainColumns'][] = $row['COLUMN_NAME'];
		}

		$statements = [];
		foreach ($indexes as $name => $index) {
			if (!array_intersect($index['plainColumns'], $columns)) {
				continue;
			}
			$columnList = implode(', ', array_map(function ($column) {
				if (preg_match('/^(\w+)\((\d+)\)$/', $column, $matches)) {
					return "`{$matches[1]}`({$matches[2]})";
				}
				return "`{$column}`";
			}, $index['columns']));
			$statements[] = sprintf(
				'CREATE %sINDEX `%s` ON `%s` (%s)',
				$index['unique'] ? 'UNIQUE ' : '',
				$name,
				$table,
				$columnList
			);
		}
		return $statements;
	}

	/**
	 * @param string $table
	 * @return string[] Primary key column names.
	 */
	protected function getPrimaryKeyColumns($table)
	{
		return (new Query())
			->select('COLUMN_NAME')
			->from('information_schema.KEY_COLUMN_USAGE')
			->where([
				'TABLE_SCHEMA' => $this->getSchemaName(),
				'TABLE_NAME' => $table,
				'CONSTRAINT_NAME' => 'PRIMARY',
			])
			->orderBy('ORDINAL_POSITION')
			->column($this->getDb());
	}

	/**
	 * @param string $table
	 * @param string $column
	 * @return bool
	 */
	protected function columnExists($table, $column)
	{
		return (new Query())
			->from('information_schema.COLUMNS')
			->where(['TABLE_SCHEMA' => $this->getSchemaName(), 'TABLE_NAME' => $table, 'COLUMN_NAME' => $column])
			->exists($this->getDb());
	}

	/**
	 * @return Connection
	 */
	protected function getDb()
	{
		return Yii::$app->getDb();
	}

	/**
	 * @return string Current schema (database) name.
	 */
	protected function getSchemaName()
	{
		$db = $this->getDb();
		if (preg_match('/dbname=([^;]+)/', $db->dsn, $matches)) {
			return $matches[1];
		}
		return (new Query())->select(new \yii\db\Expression('DATABASE()'))->scalar($db);
	}
}
