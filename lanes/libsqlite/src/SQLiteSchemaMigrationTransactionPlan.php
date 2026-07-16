<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSchemaMigrationTransactionPlan
{
    /**
     * @param list<array<string, mixed>> $columns
     * @param list<array<string, mixed>> $currentRows
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function plan(string $tableName, array $columns, array $currentRows, array $options = []): array
    {
        $tableName = self::normalizeIdentifier($tableName, 'table name');
        $databasePath = (string) ($options['database_path'] ?? '/tmp/application.sqlite');
        $beginSql = (string) ($options['begin'] ?? 'BEGIN IMMEDIATE');
        $pageSize = (int) ($options['page_size'] ?? 4096);
        $schemaVersion = (int) ($options['schema_version'] ?? 1);
        $foreignKeys = (bool) ($options['foreign_keys'] ?? true);
        $strict = (bool) ($options['strict'] ?? false);
        $withoutRowid = (bool) ($options['without_rowid'] ?? false);
        $indexes = self::normalizeSqlList($options['indexes'] ?? [], 'index');
        $triggers = self::normalizeSqlList($options['triggers'] ?? [], 'trigger');
        $copyExpressions = self::normalizeCopyExpressions($options['copy_expressions'] ?? [], $columns);
        $temporaryName = self::normalizeIdentifier((string) ($options['temporary_name'] ?? '__app_migrate_' . $tableName), 'temporary table name');
        $targetName = self::normalizeIdentifier((string) ($options['target_name'] ?? $tableName), 'target table name');

        if ($databasePath === '' || $databasePath[0] !== '/' || str_contains($databasePath, "\0") || str_contains($databasePath, '..')) {
            throw new \InvalidArgumentException('SQLite Application schema migration requires a safe absolute database path');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite Application schema migration page size must be a power of two at least 512');
        }
        if ($schemaVersion < 0) {
            throw new \InvalidArgumentException('SQLite Application schema migration schema_version must be non-negative');
        }
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite Application schema migration requires at least one column');
        }

        $begin = SQLiteTransactionBeginLockPlan::plan($beginSql, journalMode: 'delete');
        if (!$begin['write_lock_acquired']) {
            throw new \InvalidArgumentException('SQLite Application schema migration requires an immediate or exclusive write transaction');
        }

        $normalizedColumns = [];
        foreach ($columns as $column) {
            $normalizedColumns[] = self::normalizeColumn($column);
        }

        $columnNames = array_column($normalizedColumns, 'name');
        $copyColumns = [];
        foreach ($columnNames as $columnName) {
            $copyColumns[$columnName] = $copyExpressions[$columnName] ?? self::quoteIdentifier($columnName);
        }

        $createSql = self::createTableSql($temporaryName, $normalizedColumns, $strict, $withoutRowid);
        $insertSql = sprintf(
            'INSERT INTO %s (%s) SELECT %s FROM %s',
            self::quoteIdentifier($temporaryName),
            implode(', ', array_map(self::quoteIdentifier(...), array_keys($copyColumns))),
            implode(', ', array_values($copyColumns)),
            self::quoteIdentifier($tableName)
        );
        $dropSql = 'DROP TABLE ' . self::quoteIdentifier($tableName);
        $renameSql = sprintf('ALTER TABLE %s RENAME TO %s', self::quoteIdentifier($temporaryName), self::quoteIdentifier($targetName));

        $rowCount = count($currentRows);
        $estimatedTablePages = max(1, (int) ceil(max(1, $rowCount) / 48));
        $estimatedIndexPages = max(0, count($indexes));
        $dirtyPages = range(2, 1 + $estimatedTablePages + $estimatedIndexPages);
        $journalBytes = 28 + (count($dirtyPages) * ($pageSize + 8));
        $nextSchemaVersion = $schemaVersion + 1;
        $syncSequence = SQLiteVfsSyncPlan::rollbackCommitSequence($databasePath, 'full', false);

        $statements = [];
        if ($foreignKeys) {
            $statements[] = ['op' => 'pragma', 'sql' => 'PRAGMA foreign_keys=OFF', 'reason' => 'disable_fk_during_copy_table_migration'];
        }
        $statements[] = ['op' => 'begin', 'sql' => $beginSql, 'mode' => $begin['mode']];
        $statements[] = ['op' => 'create', 'sql' => $createSql, 'table' => $temporaryName];
        $statements[] = ['op' => 'copy', 'sql' => $insertSql, 'rows' => $rowCount, 'columns' => array_keys($copyColumns)];
        $statements[] = ['op' => 'drop', 'sql' => $dropSql, 'table' => $tableName];
        $statements[] = ['op' => 'rename', 'sql' => $renameSql, 'from' => $temporaryName, 'to' => $targetName];
        foreach ($indexes as $indexSql) {
            $statements[] = ['op' => 'recreate_index', 'sql' => $indexSql];
        }
        foreach ($triggers as $triggerSql) {
            $statements[] = ['op' => 'recreate_trigger', 'sql' => $triggerSql];
        }
        $statements[] = ['op' => 'pragma', 'sql' => 'PRAGMA schema_version=' . $nextSchemaVersion, 'value' => $nextSchemaVersion];
        if ($foreignKeys) {
            $statements[] = ['op' => 'pragma', 'sql' => 'PRAGMA foreign_key_check', 'reason' => 'verify_migrated_rows'];
            $statements[] = ['op' => 'pragma', 'sql' => 'PRAGMA foreign_keys=ON', 'reason' => 'restore_fk_enforcement'];
        }
        foreach ($syncSequence as $sync) {
            $statements[] = [
                'op' => 'sync',
                'target' => $sync['target'],
                'path' => $sync['path'],
                'flags' => $sync['flag_names'],
                'reason' => 'durable_schema_migration_commit',
            ];
        }

        return [
            'status' => 'planned',
            'database_path' => $databasePath,
            'table' => $tableName,
            'temporary_table' => $temporaryName,
            'target_table' => $targetName,
            'begin' => $begin,
            'foreign_keys' => $foreignKeys,
            'strict' => $strict,
            'without_rowid' => $withoutRowid,
            'schema_version_before' => $schemaVersion,
            'schema_version_after' => $nextSchemaVersion,
            'data_version_after' => 2,
            'row_count' => $rowCount,
            'columns' => $normalizedColumns,
            'copy_columns' => $copyColumns,
            'indexes' => $indexes,
            'triggers' => $triggers,
            'dirty_pages' => $dirtyPages,
            'journal_bytes' => $journalBytes,
            'sync_sequence' => $syncSequence,
            'statements' => $statements,
            'rollback' => [
                'drop_temporary_table' => $temporaryName,
                'restore_schema_version' => $schemaVersion,
                'restore_foreign_keys' => $foreignKeys,
                'discarded_statements' => count($statements),
            ],
            'dependencies' => [
                'sqlite-application-schema-migration-transaction',
                'sqlite-begin-transaction-lock-mode',
                'sqlite-rollback-journal-commit',
                'sqlite-vfs-sync-apply',
            ],
        ];
    }

    private static function normalizeIdentifier(string $identifier, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("SQLite Application schema migration {$label} must be a bounded identifier");
        }

        return $identifier;
    }

    /**
     * @param array<string, mixed> $column
     * @return array{name:string,type:string,not_null:bool,default:mixed,primary_key:bool}
     */
    private static function normalizeColumn(array $column): array
    {
        $name = self::normalizeIdentifier((string) ($column['name'] ?? ''), 'column name');
        $type = strtoupper(trim((string) ($column['type'] ?? 'TEXT')));
        if ($type === '' || !preg_match('/^[A-Z0-9_ ()]+$/', $type)) {
            throw new \InvalidArgumentException('SQLite Application schema migration column type must be bounded SQL text');
        }

        return [
            'name' => $name,
            'type' => $type,
            'not_null' => (bool) ($column['not_null'] ?? false),
            'default' => $column['default'] ?? null,
            'primary_key' => (bool) ($column['primary_key'] ?? false),
        ];
    }

    /**
     * @param mixed $sqlList
     * @return list<string>
     */
    private static function normalizeSqlList(mixed $sqlList, string $label): array
    {
        if (!is_array($sqlList)) {
            throw new \InvalidArgumentException("SQLite Application schema migration {$label} list must be an array");
        }

        $normalized = [];
        foreach ($sqlList as $sql) {
            if (!is_string($sql) || trim($sql) === '' || str_contains($sql, "\0")) {
                throw new \InvalidArgumentException("SQLite Application schema migration {$label} SQL must be non-empty text");
            }
            $normalized[] = trim($sql);
        }

        return $normalized;
    }

    /**
     * @param mixed $expressions
     * @param list<array<string,mixed>> $columns
     * @return array<string,string>
     */
    private static function normalizeCopyExpressions(mixed $expressions, array $columns): array
    {
        if (!is_array($expressions)) {
            throw new \InvalidArgumentException('SQLite Application schema migration copy expressions must be an array');
        }

        $known = [];
        foreach ($columns as $column) {
            if (isset($column['name']) && is_string($column['name'])) {
                $known[$column['name']] = true;
            }
        }

        $normalized = [];
        foreach ($expressions as $column => $expression) {
            if (!is_string($column) || !isset($known[$column])) {
                throw new \InvalidArgumentException('SQLite Application schema migration copy expression targets a migrated column');
            }
            if (!is_string($expression) || trim($expression) === '' || str_contains($expression, "\0") || str_contains($expression, ';')) {
                throw new \InvalidArgumentException('SQLite Application schema migration copy expression must be bounded SQL expression text');
            }
            $normalized[$column] = trim($expression);
        }

        return $normalized;
    }

    /**
     * @param list<array{name:string,type:string,not_null:bool,default:mixed,primary_key:bool}> $columns
     */
    private static function createTableSql(string $tableName, array $columns, bool $strict, bool $withoutRowid): string
    {
        $definitions = [];
        foreach ($columns as $column) {
            $definition = self::quoteIdentifier($column['name']) . ' ' . $column['type'];
            if ($column['primary_key']) {
                $definition .= ' PRIMARY KEY';
            }
            if ($column['not_null']) {
                $definition .= ' NOT NULL';
            }
            if ($column['default'] !== null) {
                $definition .= ' DEFAULT ' . self::literal($column['default']);
            }
            $definitions[] = $definition;
        }

        $suffixes = [];
        if ($strict) {
            $suffixes[] = 'STRICT';
        }
        if ($withoutRowid) {
            $suffixes[] = 'WITHOUT ROWID';
        }

        return 'CREATE TABLE ' . self::quoteIdentifier($tableName) . ' (' . implode(', ', $definitions) . ')' . ($suffixes === [] ? '' : ' ' . implode(', ', $suffixes));
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private static function literal(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return "'" . str_replace("'", "''", (string) $value) . "'";
    }
}
