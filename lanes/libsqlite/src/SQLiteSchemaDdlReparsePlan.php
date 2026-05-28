<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteSchemaDdlReparsePlan
{
    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<string> $ddl
     * @param list<array{id:string,schema_cookie:int,sql:string,target?:string}> $preparedStatements
     * @return array{
     *     status:string,
     *     schema:string,
     *     before_schema_cookie:int,
     *     after_schema_cookie:int,
     *     schema_changed:bool,
     *     operations:list<array<string,mixed>>,
     *     records:list<SQLiteSchemaRecord>,
     *     table_count:int,
     *     index_count:int,
     *     invalidated_prepared:list<string>,
     *     pragma_samples:array<string,array<string,mixed>>,
     *     dependencies:list<string>
     * }
     */
    public static function apply(
        array $records,
        array $ddl,
        int $schemaCookie = 1,
        string $schema = 'main',
        array $preparedStatements = [],
    ): array {
        $schema = self::normalizeIdentifier($schema, 'schema');
        $nextRecords = self::sortRecords($records);
        $operations = [];
        $nextRowId = self::nextRowId($nextRecords);
        $nextRootPage = self::nextRootPage($nextRecords);
        $changed = 0;

        foreach ($ddl as $sql) {
            $operation = self::applyOne($nextRecords, $sql, $nextRowId, $nextRootPage);
            $operations[] = $operation;
            if ($operation['changed']) {
                $changed++;
            }
        }

        $afterCookie = $schemaCookie + $changed;
        $catalog = new SQLitePragmaSchemaCatalog($nextRecords);
        $invalidated = [];
        if ($changed > 0) {
            foreach ($preparedStatements as $statement) {
                if (($statement['schema_cookie'] ?? $schemaCookie) !== $afterCookie) {
                    $invalidated[] = (string) $statement['id'];
                }
            }
        }

        return [
            'status' => 'ok',
            'schema' => $schema,
            'before_schema_cookie' => $schemaCookie,
            'after_schema_cookie' => $afterCookie,
            'schema_changed' => $changed > 0,
            'operations' => $operations,
            'records' => self::sortRecords($nextRecords),
            'table_count' => count(array_filter($nextRecords, static fn (SQLiteSchemaRecord $record): bool => $record->type === 'table')),
            'index_count' => count(array_filter($nextRecords, static fn (SQLiteSchemaRecord $record): bool => $record->type === 'index')),
            'invalidated_prepared' => $invalidated,
            'pragma_samples' => self::pragmaSamples($catalog, $operations),
            'dependencies' => ['schema-sql-reparse', 'sqlite-schema-cookie', 'pragma-schema-catalog'],
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,mixed>
     */
    private static function applyOne(array &$records, string $sql, int &$nextRowId, int &$nextRootPage): array
    {
        $trimmed = trim(rtrim($sql, " \t\r\n;"));
        if ($trimmed === '') {
            throw new InvalidArgumentException('SQLite schema DDL reparse requires non-empty SQL');
        }

        if (preg_match('/^create\s+(?<unique>unique\s+)?index\s+(?:if\s+not\s+exists\s+)?(?<name>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s+on\s+(?<table>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s*\(/i', $trimmed, $matches)) {
            $name = self::unquoteIdentifier($matches['name']);
            $table = self::unquoteIdentifier($matches['table']);
            if (self::findRecordIndex($records, 'index', $name) !== null) {
                return ['kind' => 'create_index', 'name' => $name, 'table' => $table, 'changed' => false, 'reason' => 'index_already_exists'];
            }
            if (self::findRecordIndex($records, 'table', $table) === null) {
                throw new InvalidArgumentException("SQLite schema DDL reparse cannot create index {$name} on missing table {$table}");
            }

            $record = new SQLiteSchemaRecord('index', $name, $table, $nextRootPage++, self::normalizeCreateSql($trimmed), $nextRowId++);
            $records[] = $record;

            return [
                'kind' => 'create_index',
                'name' => $name,
                'table' => $table,
                'rootpage' => $record->rootPage,
                'rowid' => $record->rowId,
                'unique' => isset($matches['unique']) && trim((string) $matches['unique']) !== '',
                'partial' => self::hasTopLevelWhere($trimmed),
                'changed' => true,
            ];
        }

        if (preg_match('/^create\s+table\s+(?:if\s+not\s+exists\s+)?(?<name>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s*\(/i', $trimmed, $matches)) {
            $name = self::unquoteIdentifier($matches['name']);
            if (self::findRecordIndex($records, 'table', $name) !== null) {
                return ['kind' => 'create_table', 'name' => $name, 'changed' => false, 'reason' => 'table_already_exists'];
            }

            $record = new SQLiteSchemaRecord('table', $name, $name, $nextRootPage++, self::normalizeCreateSql($trimmed), $nextRowId++);
            $records[] = $record;

            return [
                'kind' => 'create_table',
                'name' => $name,
                'rootpage' => $record->rootPage,
                'rowid' => $record->rowId,
                'changed' => true,
            ];
        }

        if (preg_match('/^create\s+(?:temp(?:orary)?\s+)?view\s+(?:if\s+not\s+exists\s+)?(?<name>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)(?=\s|$)/i', $trimmed, $matches)) {
            $name = self::unquoteIdentifier($matches['name']);
            if (self::findRecordIndex($records, 'view', $name) !== null) {
                return ['kind' => 'create_view', 'name' => $name, 'changed' => false, 'reason' => 'view_already_exists'];
            }

            $record = new SQLiteSchemaRecord('view', $name, $name, 0, self::normalizeCreateSql($trimmed), $nextRowId++);
            $records[] = $record;

            return [
                'kind' => 'create_view',
                'name' => $name,
                'rootpage' => 0,
                'rowid' => $record->rowId,
                'changed' => true,
            ];
        }

        if (preg_match('/^create\s+(?:temp(?:orary)?\s+)?trigger\s+(?:if\s+not\s+exists\s+)?(?<name>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)(?=\s|$)(?<tail>.*)$/is', $trimmed, $matches)) {
            $name = self::unquoteIdentifier($matches['name']);
            if (self::findRecordIndex($records, 'trigger', $name) !== null) {
                return ['kind' => 'create_trigger', 'name' => $name, 'changed' => false, 'reason' => 'trigger_already_exists'];
            }
            $table = self::parseTriggerTableName((string) $matches['tail']);
            if ($table === null) {
                throw new InvalidArgumentException("SQLite schema DDL reparse cannot determine trigger target for {$name}");
            }
            if (self::findSchemaObjectIndex($records, ['table', 'view'], $table) === null) {
                throw new InvalidArgumentException("SQLite schema DDL reparse cannot create trigger {$name} on missing table or view {$table}");
            }

            $record = new SQLiteSchemaRecord('trigger', $name, $table, 0, self::normalizeCreateSql($trimmed), $nextRowId++);
            $records[] = $record;

            return [
                'kind' => 'create_trigger',
                'name' => $name,
                'table' => $table,
                'rootpage' => 0,
                'rowid' => $record->rowId,
                'changed' => true,
            ];
        }

        if (preg_match('/^drop\s+index\s+(?:if\s+exists\s+)?(?<name>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)$/i', $trimmed, $matches)) {
            $name = self::unquoteIdentifier($matches['name']);
            $index = self::findRecordIndex($records, 'index', $name);
            if ($index === null) {
                return ['kind' => 'drop_index', 'name' => $name, 'changed' => false, 'reason' => 'missing_index'];
            }

            $record = $records[$index];
            array_splice($records, $index, 1);

            return ['kind' => 'drop_index', 'name' => $name, 'table' => $record->tableName, 'freed_rootpage' => $record->rootPage, 'changed' => true];
        }

        if (preg_match('/^drop\s+view\s+(?:if\s+exists\s+)?(?<name>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)$/i', $trimmed, $matches)) {
            $name = self::unquoteIdentifier($matches['name']);
            $index = self::findRecordIndex($records, 'view', $name);
            if ($index === null) {
                return ['kind' => 'drop_view', 'name' => $name, 'changed' => false, 'reason' => 'missing_view'];
            }

            array_splice($records, $index, 1);

            return ['kind' => 'drop_view', 'name' => $name, 'changed' => true];
        }

        if (preg_match('/^drop\s+trigger\s+(?:if\s+exists\s+)?(?<name>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)$/i', $trimmed, $matches)) {
            $name = self::unquoteIdentifier($matches['name']);
            $index = self::findRecordIndex($records, 'trigger', $name);
            if ($index === null) {
                return ['kind' => 'drop_trigger', 'name' => $name, 'changed' => false, 'reason' => 'missing_trigger'];
            }

            $record = $records[$index];
            array_splice($records, $index, 1);

            return ['kind' => 'drop_trigger', 'name' => $name, 'table' => $record->tableName, 'changed' => true];
        }

        if (preg_match('/^drop\s+table\s+(?:if\s+exists\s+)?(?<name>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)$/i', $trimmed, $matches)) {
            $name = self::unquoteIdentifier($matches['name']);
            $removed = [];
            $kept = [];
            foreach ($records as $record) {
                if (
                    ($record->type === 'table' && strcasecmp($record->name, $name) === 0)
                    || ($record->type === 'index' && strcasecmp($record->tableName, $name) === 0)
                    || ($record->type === 'trigger' && strcasecmp($record->tableName, $name) === 0)
                ) {
                    $removed[] = $record;
                    continue;
                }
                $kept[] = $record;
            }
            if ($removed === []) {
                return ['kind' => 'drop_table', 'name' => $name, 'changed' => false, 'reason' => 'missing_table'];
            }
            $records = $kept;

            return [
                'kind' => 'drop_table',
                'name' => $name,
                'removed_records' => array_map(static fn (SQLiteSchemaRecord $record): string => $record->type . ':' . $record->name, $removed),
                'freed_rootpages' => array_values(array_filter(array_map(static fn (SQLiteSchemaRecord $record): ?int => $record->rootPage, $removed), static fn (?int $page): bool => $page !== null && $page > 0)),
                'changed' => true,
            ];
        }

        if (preg_match('/^alter\s+table\s+(?<old>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s+rename\s+to\s+(?<new>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)$/i', $trimmed, $matches)) {
            $old = self::unquoteIdentifier($matches['old']);
            $new = self::unquoteIdentifier($matches['new']);
            $table = self::findRecordIndex($records, 'table', $old);
            if ($table === null) {
                throw new InvalidArgumentException("SQLite schema DDL reparse cannot rename missing table {$old}");
            }
            if (self::findRecordIndex($records, 'table', $new) !== null) {
                throw new InvalidArgumentException("SQLite schema DDL reparse cannot rename {$old} to existing table {$new}");
            }

            $rewritten = [];
            foreach ($records as $offset => $record) {
                if (!self::recordMayReferenceTable($record, $old)) {
                    continue;
                }

                $nextSql = $record->sql === null ? null : SQLiteAlterTableRenamePlan::renameTableSql($record->sql, $old, $new);
                if ($record->type === 'table' && strcasecmp($record->name, $old) === 0) {
                    $records[$offset] = new SQLiteSchemaRecord(
                        'table',
                        $new,
                        $new,
                        $record->rootPage,
                        $nextSql,
                        $record->rowId,
                    );
                    $rewritten[] = 'table:' . $record->name;
                    continue;
                }

                if ($nextSql === $record->sql && strcasecmp($record->tableName, $old) !== 0) {
                    continue;
                }

                $records[$offset] = new SQLiteSchemaRecord(
                    $record->type,
                    $record->name,
                    strcasecmp($record->tableName, $old) === 0 ? $new : $record->tableName,
                    $record->rootPage,
                    $nextSql,
                    $record->rowId,
                );
                $rewritten[] = $record->type . ':' . $record->name;
            }

            return [
                'kind' => 'alter_table_rename',
                'old_name' => $old,
                'new_name' => $new,
                'rewritten_records' => $rewritten,
                'dependent_reparse_count' => count(array_values(array_filter(
                    $rewritten,
                    static fn (string $entry): bool => !str_starts_with($entry, 'table:'),
                ))),
                'changed' => true,
            ];
        }

        if (preg_match('/^alter\s+table\s+(?<table>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s+rename\s+column\s+(?<old>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s+to\s+(?<new>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)$/i', $trimmed, $matches)) {
            $tableName = self::unquoteIdentifier($matches['table']);
            $old = self::unquoteIdentifier($matches['old']);
            $new = self::unquoteIdentifier($matches['new']);
            $table = self::findRecordIndex($records, 'table', $tableName);
            if ($table === null) {
                throw new InvalidArgumentException("SQLite schema DDL reparse cannot rename a column on missing table {$tableName}");
            }

            $columns = self::tableColumnNames($records, $tableName);
            if (!self::hasColumn($columns, $old)) {
                throw new InvalidArgumentException("SQLite schema DDL reparse cannot rename missing column {$old} on {$tableName}");
            }
            if (self::hasColumn($columns, $new)) {
                throw new InvalidArgumentException("SQLite schema DDL reparse cannot rename {$old} to existing column {$new} on {$tableName}");
            }

            $rewritten = [];
            foreach ($records as $offset => $record) {
                if ($record->sql === null || !self::recordMayReferenceTable($record, $tableName)) {
                    continue;
                }

                $nextSql = SQLiteAlterTableRenamePlan::renameColumnSql($record->sql, $tableName, $old, $new);
                if ($nextSql === $record->sql) {
                    continue;
                }

                $records[$offset] = new SQLiteSchemaRecord(
                    $record->type,
                    $record->name,
                    $record->tableName,
                    $record->rootPage,
                    $nextSql,
                    $record->rowId,
                );
                $rewritten[] = $record->type . ':' . $record->name;
            }

            return [
                'kind' => 'alter_table_rename_column',
                'table' => $tableName,
                'old_name' => $old,
                'new_name' => $new,
                'rewritten_records' => $rewritten,
                'changed' => true,
            ];
        }

        throw new InvalidArgumentException("Unsupported SQLite schema DDL reparse SQL: {$sql}");
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function nextRowId(array $records): int
    {
        $max = 0;
        foreach ($records as $record) {
            $max = max($max, $record->rowId);
        }

        return $max + 1;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function nextRootPage(array $records): int
    {
        $max = 1;
        foreach ($records as $record) {
            if ($record->rootPage !== null) {
                $max = max($max, $record->rootPage);
            }
        }

        return $max + 1;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function findRecordIndex(array $records, string $type, string $name): ?int
    {
        foreach ($records as $index => $record) {
            if ($record->type === $type && strcasecmp($record->name, $name) === 0) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<string> $types
     */
    private static function findSchemaObjectIndex(array $records, array $types, string $name): ?int
    {
        foreach ($records as $index => $record) {
            if (in_array($record->type, $types, true) && strcasecmp($record->name, $name) === 0) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<string>
     */
    private static function tableColumnNames(array $records, string $tableName): array
    {
        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = $catalog->execute('PRAGMA table_xinfo("' . str_replace('"', '""', $tableName) . '")')['rows'];

        return array_values(array_map(static fn (array $row): string => (string) $row['name'], $rows));
    }

    /**
     * @param list<string> $columns
     */
    private static function hasColumn(array $columns, string $column): bool
    {
        foreach ($columns as $candidate) {
            if (strcasecmp($candidate, $column) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function recordMayReferenceTable(SQLiteSchemaRecord $record, string $tableName): bool
    {
        if (
            ($record->type === 'table' && strcasecmp($record->name, $tableName) === 0)
            || (in_array($record->type, ['index', 'trigger'], true) && strcasecmp($record->tableName, $tableName) === 0)
        ) {
            return true;
        }

        if ($record->sql === null) {
            return false;
        }

        return preg_match('/(?<![A-Za-z0-9_$])' . preg_quote($tableName, '/') . '(?![A-Za-z0-9_$])/i', $record->sql) === 1;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<SQLiteSchemaRecord>
     */
    private static function sortRecords(array $records): array
    {
        usort($records, static fn (SQLiteSchemaRecord $a, SQLiteSchemaRecord $b): int => $a->rowId <=> $b->rowId);

        return $records;
    }

    private static function normalizeIdentifier(string $name, string $label): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException("SQLite schema DDL reparse requires a {$label}");
        }

        return strtolower($name);
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            throw new InvalidArgumentException('SQLite schema DDL reparse requires a non-empty identifier');
        }

        $first = $identifier[0];
        $last = $identifier[strlen($identifier) - 1];
        if ($first === '"' && $last === '"') {
            return str_replace('""', '"', substr($identifier, 1, -1));
        }
        if (($first === '`' && $last === '`') || ($first === '[' && $last === ']')) {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }

    private static function normalizeCreateSql(string $sql): string
    {
        return rtrim(trim($sql), ';');
    }

    private static function hasTopLevelWhere(string $sql): bool
    {
        $depth = 0;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'" || $char === '"' || $char === '`') {
                $i = self::skipQuoted($sql, $i, $char);
                continue;
            }
            if ($char === '[') {
                $end = strpos($sql, ']', $i + 1);
                $i = $end === false ? $length : $end;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')' && $depth > 0) {
                $depth--;
                continue;
            }
            if ($depth === 0 && strncasecmp(substr($sql, $i, 5), 'where', 5) === 0) {
                $before = $i === 0 ? ' ' : $sql[$i - 1];
                $after = $sql[$i + 5] ?? ' ';
                if (!self::isIdentifierChar($before) && !self::isIdentifierChar($after)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function skipQuoted(string $sql, int $offset, string $quote): int
    {
        $length = strlen($sql);
        for ($i = $offset + 1; $i < $length; $i++) {
            if ($sql[$i] !== $quote) {
                continue;
            }
            if (($sql[$i + 1] ?? null) === $quote) {
                $i++;
                continue;
            }

            return $i;
        }

        return $length - 1;
    }

    private static function isIdentifierChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_';
    }

    private static function parseTriggerTableName(string $tail): ?string
    {
        if (!preg_match('/\bon\s+(?<table>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)(?:\s|$)/i', $tail, $matches)) {
            return null;
        }

        return self::unquoteIdentifier($matches['table']);
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function pragmaSamples(SQLitePragmaSchemaCatalog $catalog, array $operations): array
    {
        $samples = [];
        foreach ($operations as $operation) {
            if (($operation['changed'] ?? false) !== true) {
                continue;
            }
            if (($operation['kind'] ?? '') === 'create_index' || ($operation['kind'] ?? '') === 'drop_index') {
                $table = (string) ($operation['table'] ?? '');
                if ($table !== '') {
                    $samples["index_list:{$table}"] = $catalog->execute("PRAGMA index_list(\"{$table}\")");
                }
            }
            if (($operation['kind'] ?? '') === 'create_table' || ($operation['kind'] ?? '') === 'alter_table_rename' || ($operation['kind'] ?? '') === 'alter_table_rename_column') {
                $table = (string) (($operation['new_name'] ?? null) ?: ($operation['name'] ?? ''));
                if (($operation['kind'] ?? '') === 'alter_table_rename_column') {
                    $table = (string) ($operation['table'] ?? '');
                }
                if ($table !== '') {
                    $samples["table_xinfo:{$table}"] = $catalog->execute("PRAGMA table_xinfo(\"{$table}\")");
                }
            }
        }

        return $samples;
    }
}
