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
     * @param array<string,list<array<string,mixed>>> $currentRowsByTable
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
        array $currentRowsByTable = [],
    ): array {
        $schema = self::normalizeIdentifier($schema, 'schema');
        $nextRecords = self::sortRecords($records);
        $operations = [];
        $nextRowId = self::nextRowId($nextRecords);
        $nextRootPage = self::nextRootPage($nextRecords);
        $changed = 0;

        foreach ($ddl as $sql) {
            $operation = self::applyOne($nextRecords, $sql, $nextRowId, $nextRootPage, $currentRowsByTable);
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
                if (($statement['expired_by_rollback'] ?? false) === true || ($statement['schema_cookie'] ?? $schemaCookie) !== $afterCookie) {
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
    private static function applyOne(array &$records, string $sql, int &$nextRowId, int &$nextRootPage, array $currentRowsByTable): array
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
            $indexTerms = self::indexTerms($trimmed);
            self::assertIndexTermsReferenceKnownColumns($records, $table, $indexTerms, $name);
            $records[] = $record;
            $generatedReferences = self::generatedColumnReferences($records, $table, $indexTerms);

            return [
                'kind' => 'create_index',
                'name' => $name,
                'table' => $table,
                'rootpage' => $record->rootPage,
                'rowid' => $record->rowId,
                'unique' => isset($matches['unique']) && trim((string) $matches['unique']) !== '',
                'partial' => self::hasTopLevelWhere($trimmed),
                'terms' => $indexTerms,
                'term_count' => count($indexTerms),
                'expression_terms' => array_values(array_filter($indexTerms, static fn (string $term): bool => self::isExpressionIndexTerm($term))),
                'generated_column_references' => $generatedReferences,
                'generated_column_reference_count' => count($generatedReferences),
                'current_source_reparse' => $generatedReferences !== [] || self::hasTopLevelWhere($trimmed),
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

            $metadata = self::viewReparseMetadata($records, $trimmed, $name);
            $record = new SQLiteSchemaRecord('view', $name, $name, 0, self::normalizeCreateSql($trimmed), $nextRowId++);
            $records[] = $record;

            return [
                'kind' => 'create_view',
                'name' => $name,
                'rootpage' => 0,
                'rowid' => $record->rowId,
                'source_tables' => $metadata['source_tables'],
                'source_views' => $metadata['source_views'],
                'view_references' => $metadata['view_references'],
                'indexed_by' => $metadata['indexed_by'],
                'generated_column_references' => $metadata['generated_column_references'],
                'generated_column_reference_count' => count($metadata['generated_column_references']),
                'generated_index_references' => $metadata['generated_index_references'],
                'generated_index_reference_count' => count($metadata['generated_index_references']),
                'star_expansion_records' => $metadata['star_expansion_records'],
                'current_source_reparse' => $metadata['current_source_reparse'],
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

            $metadata = self::triggerReparseMetadata($records, $trimmed, $table);
            $record = new SQLiteSchemaRecord('trigger', $name, $table, 0, self::normalizeCreateSql($trimmed), $nextRowId++);
            $records[] = $record;

            return [
                'kind' => 'create_trigger',
                'name' => $name,
                'table' => $table,
                'rootpage' => 0,
                'rowid' => $record->rowId,
                'body_source_tables' => $metadata['body_source_tables'],
                'body_source_views' => $metadata['body_source_views'],
                'body_indexed_by' => $metadata['body_indexed_by'],
                'generated_column_references' => $metadata['generated_column_references'],
                'generated_column_reference_count' => count($metadata['generated_column_references']),
                'generated_index_references' => $metadata['generated_index_references'],
                'generated_index_reference_count' => count($metadata['generated_index_references']),
                'view_references' => $metadata['view_references'],
                'current_source_reparse' => $metadata['current_source_reparse'],
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

        if (preg_match('/^alter\s+table\s+(?<table>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s+add\s+(?:column\s+)?(?<definition>.+)$/is', $trimmed, $matches)) {
            $tableName = self::unquoteIdentifier($matches['table']);
            $table = self::findRecordIndex($records, 'table', $tableName);
            if ($table === null) {
                throw new InvalidArgumentException("SQLite schema DDL reparse cannot add a column on missing table {$tableName}");
            }

            $addPlan = SQLiteAlterTableColumnCorpus::addColumn(
                $records[$table],
                $trimmed,
                self::currentRowsForTable($currentRowsByTable, $tableName),
            );

            $records[$table] = new SQLiteSchemaRecord(
                $records[$table]->type,
                $records[$table]->name,
                $records[$table]->tableName,
                $records[$table]->rootPage,
                $addPlan['sql'],
                $records[$table]->rowId,
            );

            $dependentReparse = self::dependentReparseForAddColumn($records, $tableName, $addPlan['column']);

            return [
                'kind' => 'alter_table_add_column',
                'table' => $tableName,
                'column' => $addPlan['column'],
                'column_count' => $addPlan['column_count'],
                'checked_rows' => $addPlan['checked_rows'],
                'current_row_count' => $addPlan['current_row_count'],
                'generated' => $addPlan['generated'],
                'dependent_reparse_records' => $dependentReparse['records'],
                'dependent_reparse_count' => count($dependentReparse['records']),
                'star_expansion_records' => $dependentReparse['star_expansion_records'],
                'generated_column_view_records' => $dependentReparse['generated_column_view_records'],
                'index_reparse_records' => $dependentReparse['index_reparse_records'],
                'generated_column_index_records' => $dependentReparse['generated_column_index_records'],
                'expression_index_reparse_records' => $dependentReparse['expression_index_reparse_records'],
                'partial_index_reparse_records' => $dependentReparse['partial_index_reparse_records'],
                'index_generated_column_references' => $dependentReparse['index_generated_column_references'],
                'resolved_trigger_records' => $dependentReparse['resolved_trigger_records'],
                'unresolved_trigger_records' => $dependentReparse['unresolved_trigger_records'],
                'trigger_missing_references' => $dependentReparse['trigger_missing_references'],
                'changed' => true,
            ];
        }

        throw new InvalidArgumentException("Unsupported SQLite schema DDL reparse SQL: {$sql}");
    }

    /**
     * @param array<string,list<array<string,mixed>>> $currentRowsByTable
     * @return list<array<string,mixed>>
     */
    private static function currentRowsForTable(array $currentRowsByTable, string $tableName): array
    {
        foreach ($currentRowsByTable as $name => $rows) {
            if (strcasecmp((string) $name, $tableName) === 0) {
                return $rows;
            }
        }

        return [];
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
     * @return array{
     *     records:list<string>,
     *     star_expansion_records:list<string>,
     *     generated_column_view_records:list<string>,
     *     index_reparse_records:list<string>,
     *     generated_column_index_records:list<string>,
     *     expression_index_reparse_records:list<string>,
     *     partial_index_reparse_records:list<string>,
     *     index_generated_column_references:array<string,list<string>>,
     *     resolved_trigger_records:list<string>,
     *     unresolved_trigger_records:list<string>,
     *     trigger_missing_references:array<string,array{new:list<string>,old:list<string>}>
     * }
     */
    private static function dependentReparseForAddColumn(array $records, string $tableName, string $columnName): array
    {
        $dependent = [];
        $generatedColumnViews = [];
        $indexReparse = [];
        $generatedColumnIndexes = [];
        $expressionIndexes = [];
        $partialIndexes = [];
        $indexGeneratedReferences = [];
        foreach ($records as $record) {
            if (!in_array($record->type, ['index', 'trigger', 'view'], true)) {
                continue;
            }

            if (strcasecmp($record->tableName, $tableName) === 0 || self::recordSqlReferencesName($record, $tableName) || self::recordSqlReferencesName($record, $columnName)) {
                $dependent[] = $record->type . ':' . $record->name;
            }
            if ($record->type === 'view' && self::recordSqlReferencesName($record, $columnName)) {
                $generatedColumnViews[] = 'view:' . $record->name;
            }
            if ($record->type !== 'index' || strcasecmp($record->tableName, $tableName) !== 0 || $record->sql === null) {
                continue;
            }

            $entry = 'index:' . $record->name;
            $indexReparse[] = $entry;
            $terms = self::indexTerms($record->sql);
            $generatedReferences = self::generatedColumnReferences($records, $tableName, $terms);
            if ($generatedReferences !== []) {
                $generatedColumnIndexes[] = $entry;
                $indexGeneratedReferences[$record->name] = $generatedReferences;
            }
            if (array_filter($terms, static fn (string $term): bool => self::isExpressionIndexTerm($term)) !== []) {
                $expressionIndexes[] = $entry;
            }
            if (self::hasTopLevelWhere($record->sql)) {
                $partialIndexes[] = $entry;
            }
        }

        $resolvedTriggers = [];
        $unresolvedTriggers = [];
        $missingReferences = [];
        foreach ($records as $record) {
            if ($record->type !== 'trigger') {
                continue;
            }
            if (!in_array('trigger:' . $record->name, $dependent, true)) {
                continue;
            }

            $resolution = SQLiteViewTriggerNameResolution::resolveTrigger($records, $record->name);
            if ($resolution['status'] === 'resolved') {
                $resolvedTriggers[] = 'trigger:' . $record->name;
                continue;
            }

            $unresolvedTriggers[] = 'trigger:' . $record->name;
            $missingReferences[$record->name] = [
                'new' => $resolution['missingNew'],
                'old' => $resolution['missingOld'],
            ];
        }

        return [
            'records' => $dependent,
            'star_expansion_records' => self::starExpansionRecordsForTable($records, $tableName),
            'generated_column_view_records' => $generatedColumnViews,
            'index_reparse_records' => $indexReparse,
            'generated_column_index_records' => $generatedColumnIndexes,
            'expression_index_reparse_records' => $expressionIndexes,
            'partial_index_reparse_records' => $partialIndexes,
            'index_generated_column_references' => $indexGeneratedReferences,
            'resolved_trigger_records' => $resolvedTriggers,
            'unresolved_trigger_records' => $unresolvedTriggers,
            'trigger_missing_references' => $missingReferences,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<string>
     */
    private static function starExpansionRecordsForTable(array $records, string $tableName): array
    {
        $dependent = [];
        foreach ($records as $record) {
            if (!in_array($record->type, ['trigger', 'view'], true) || $record->sql === null) {
                continue;
            }

            if (!self::recordSqlReferencesName($record, $tableName)) {
                continue;
            }

            if (preg_match('/(?<![A-Za-z0-9_$])select\s+(?:distinct\s+)?\*/i', $record->sql) === 1) {
                $dependent[] = $record->type . ':' . $record->name;
            }
        }

        return $dependent;
    }

    private static function recordSqlReferencesName(SQLiteSchemaRecord $record, string $name): bool
    {
        if ($record->sql === null) {
            return false;
        }

        return preg_match('/(?<![A-Za-z0-9_$])' . preg_quote($name, '/') . '(?![A-Za-z0-9_$])/i', $record->sql) === 1;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array{source_tables:list<string>, source_views:list<string>, view_references:list<string>, indexed_by:list<string>, generated_column_references:list<string>, generated_index_references:list<string>, star_expansion_records:list<string>, current_source_reparse:bool}
     */
    private static function viewReparseMetadata(array $records, string $createViewSql, string $viewName): array
    {
        $sources = self::viewSources($createViewSql);
        $sourceTables = [];
        $sourceViews = [];
        $viewReferences = [];
        $indexedBy = self::viewIndexedByNames($createViewSql);
        $generatedColumnReferences = [];
        $generatedIndexReferences = [];
        $starExpansionRecords = self::viewUsesStarProjection($createViewSql) ? ['view:' . $viewName] : [];

        foreach ($sources as $source) {
            if (self::findRecordIndex($records, 'table', $source) !== null) {
                $sourceTables[] = $source;
                foreach (self::generatedColumnNames($records, $source) as $column) {
                    if (self::sqlReferencesIdentifier($createViewSql, $column) && !in_array($column, $generatedColumnReferences, true)) {
                        $generatedColumnReferences[] = $column;
                    }
                }
                continue;
            }

            $view = self::findRecord($records, 'view', $source);
            if ($view !== null) {
                $sourceViews[] = $source;
                $viewReferences[] = 'view:' . $source;
                if ($view->sql !== null) {
                    $viewMetadata = self::viewReparseMetadata($records, $view->sql, $view->name);
                    foreach ($viewMetadata['generated_column_references'] as $column) {
                        if (!in_array($column, $generatedColumnReferences, true)) {
                            $generatedColumnReferences[] = $column;
                        }
                    }
                    foreach ($viewMetadata['generated_index_references'] as $indexName) {
                        if (!in_array($indexName, $generatedIndexReferences, true)) {
                            $generatedIndexReferences[] = $indexName;
                        }
                    }
                }
                continue;
            }

            throw new InvalidArgumentException("SQLite schema DDL reparse cannot create view {$viewName} from missing table or view {$source}");
        }

        foreach ($indexedBy as $indexName) {
            $index = self::findRecord($records, 'index', $indexName);
            if ($index === null) {
                throw new InvalidArgumentException("SQLite schema DDL reparse cannot create view {$viewName} with missing indexed-by index {$indexName}");
            }
            if ($index->sql === null) {
                continue;
            }

            $terms = self::indexTerms($index->sql);
            if (self::generatedColumnReferences($records, $index->tableName, $terms) !== [] && !in_array($indexName, $generatedIndexReferences, true)) {
                $generatedIndexReferences[] = $indexName;
            }
        }

        return [
            'source_tables' => $sourceTables,
            'source_views' => $sourceViews,
            'view_references' => $viewReferences,
            'indexed_by' => $indexedBy,
            'generated_column_references' => $generatedColumnReferences,
            'generated_index_references' => $generatedIndexReferences,
            'star_expansion_records' => $starExpansionRecords,
            'current_source_reparse' => $generatedColumnReferences !== [] || $generatedIndexReferences !== [] || $viewReferences !== [] || $starExpansionRecords !== [],
        ];
    }

    /**
     * @return list<string>
     */
    private static function viewSources(string $sql): array
    {
        $sources = [];
        if (preg_match_all('/\b(?:from|join)\s+(?<table>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)(?!\s*\()/i', $sql, $matches) !== false) {
            foreach ($matches['table'] as $table) {
                $name = self::unquoteIdentifier($table);
                if (!in_array($name, $sources, true)) {
                    $sources[] = $name;
                }
            }
        }

        return $sources;
    }

    /**
     * @return list<string>
     */
    private static function viewIndexedByNames(string $sql): array
    {
        $indexes = [];
        if (preg_match_all('/\bindexed\s+by\s+(?<name>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)/i', $sql, $matches) !== false) {
            foreach ($matches['name'] as $index) {
                $name = self::unquoteIdentifier($index);
                if (!in_array($name, $indexes, true)) {
                    $indexes[] = $name;
                }
            }
        }

        return $indexes;
    }

    private static function viewUsesStarProjection(string $sql): bool
    {
        return preg_match('/\bas\s+select\s+(?:distinct\s+)?(?:\*|(?:[^;]*?,\s*)?[A-Za-z_][A-Za-z0-9_]*\.\*)(?=\s|,|$)/i', $sql) === 1;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array{body_source_tables:list<string>, body_source_views:list<string>, body_indexed_by:list<string>, generated_column_references:list<string>, generated_index_references:list<string>, view_references:list<string>, current_source_reparse:bool}
     */
    private static function triggerReparseMetadata(array $records, string $createTriggerSql, string $targetTable): array
    {
        $sources = self::triggerBodySources($createTriggerSql);
        $tables = [];
        $views = [];
        $viewReferences = [];
        $generatedColumnReferences = [];
        $generatedIndexReferences = [];

        foreach ($sources as $source) {
            $table = self::findRecord($records, 'table', $source);
            if ($table !== null) {
                $tables[] = $source;
                foreach (self::generatedColumnNames($records, $source) as $column) {
                    if (self::sqlReferencesIdentifier($createTriggerSql, $column) && !in_array($column, $generatedColumnReferences, true)) {
                        $generatedColumnReferences[] = $column;
                    }
                }
                continue;
            }

            $view = self::findRecord($records, 'view', $source);
            if ($view !== null) {
                $views[] = $source;
                $viewReferences[] = 'view:' . $source;
                if ($view->sql !== null) {
                    $viewMetadata = self::viewReparseMetadata($records, $view->sql, $view->name);
                    foreach ($viewMetadata['generated_column_references'] as $column) {
                        if (!in_array($column, $generatedColumnReferences, true)) {
                            $generatedColumnReferences[] = $column;
                        }
                    }
                    foreach ($viewMetadata['generated_index_references'] as $indexName) {
                        if (!in_array($indexName, $generatedIndexReferences, true)) {
                            $generatedIndexReferences[] = $indexName;
                        }
                    }
                }
            }
        }

        foreach (self::generatedColumnNames($records, $targetTable) as $column) {
            if (self::sqlReferencesIdentifier($createTriggerSql, $column) && !in_array($column, $generatedColumnReferences, true)) {
                $generatedColumnReferences[] = $column;
            }
        }

        $indexedBy = self::viewIndexedByNames($createTriggerSql);
        foreach ($indexedBy as $indexName) {
            $index = self::findRecord($records, 'index', $indexName);
            if ($index === null || $index->sql === null) {
                continue;
            }

            $terms = self::indexTerms($index->sql);
            if (self::generatedColumnReferences($records, $index->tableName, $terms) !== [] && !in_array($indexName, $generatedIndexReferences, true)) {
                $generatedIndexReferences[] = $indexName;
            }
        }

        return [
            'body_source_tables' => $tables,
            'body_source_views' => $views,
            'body_indexed_by' => $indexedBy,
            'generated_column_references' => $generatedColumnReferences,
            'generated_index_references' => $generatedIndexReferences,
            'view_references' => $viewReferences,
            'current_source_reparse' => $generatedColumnReferences !== [] || $generatedIndexReferences !== [] || $viewReferences !== [],
        ];
    }

    /**
     * @return list<string>
     */
    private static function triggerBodySources(string $sql): array
    {
        $begin = stripos($sql, ' begin ');
        $body = $begin === false ? $sql : substr($sql, $begin);
        $sources = [];
        if (preg_match_all('/\b(?:from|join|into|update)\s+(?<table>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)(?!\s*\(\s*select\b)/i', $body, $matches) !== false) {
            foreach ($matches['table'] as $table) {
                $name = self::unquoteIdentifier($table);
                if (!in_array($name, $sources, true)) {
                    $sources[] = $name;
                }
            }
        }

        return $sources;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<string>
     */
    private static function generatedColumnNames(array $records, string $tableName): array
    {
        $generated = [];
        $catalog = new SQLitePragmaSchemaCatalog($records);
        foreach ($catalog->execute('PRAGMA table_xinfo("' . str_replace('"', '""', $tableName) . '")')['rows'] ?? [] as $row) {
            if (($row['hidden'] ?? 0) !== 0) {
                $generated[] = (string) $row['name'];
            }
        }

        return $generated;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function findRecord(array $records, string $type, string $name): ?SQLiteSchemaRecord
    {
        $index = self::findRecordIndex($records, $type, $name);

        return $index === null ? null : $records[$index];
    }

    /**
     * @return list<string>
     */
    private static function indexTerms(string $createIndexSql): array
    {
        $open = strpos($createIndexSql, '(');
        if ($open === false) {
            return [];
        }
        $close = self::matchingParen($createIndexSql, $open);
        if ($close === null || $close <= $open) {
            return [];
        }

        return array_map(
            static fn (string $term): string => trim((string) preg_replace('/\s+/', ' ', $term)),
            self::splitTopLevel(substr($createIndexSql, $open + 1, $close - $open - 1))
        );
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<string> $terms
     * @return list<string>
     */
    private static function generatedColumnReferences(array $records, string $tableName, array $terms): array
    {
        $generated = [];
        $catalog = new SQLitePragmaSchemaCatalog($records);
        foreach ($catalog->execute('PRAGMA table_xinfo("' . str_replace('"', '""', $tableName) . '")')['rows'] ?? [] as $row) {
            if (($row['hidden'] ?? 0) !== 0) {
                $generated[] = (string) $row['name'];
            }
        }
        if ($generated === []) {
            return [];
        }

        $references = [];
        foreach ($terms as $term) {
            foreach ($generated as $column) {
                if (self::sqlReferencesIdentifier($term, $column) && !in_array($column, $references, true)) {
                    $references[] = $column;
                }
            }
        }

        return $references;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<string> $terms
     */
    private static function assertIndexTermsReferenceKnownColumns(array $records, string $tableName, array $terms, string $indexName): void
    {
        $columns = self::tableColumnNames($records, $tableName);
        foreach ($terms as $term) {
            $identifier = self::simpleIndexTermIdentifier($term);
            if ($identifier === null) {
                continue;
            }
            if (!self::hasColumn($columns, $identifier)) {
                throw new InvalidArgumentException("SQLite schema DDL reparse cannot create index {$indexName} on missing column {$identifier}");
            }
        }
    }

    private static function simpleIndexTermIdentifier(string $term): ?string
    {
        $trimmed = trim((string) preg_replace('/\s+(?:collate\s+(?:"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*))?(?:\s+)?(?:asc|desc)?\s*$/i', '', trim($term)));
        if (preg_match('/^(?:"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)$/', $trimmed) !== 1) {
            return null;
        }

        return self::unquoteIdentifier($trimmed);
    }

    private static function isExpressionIndexTerm(string $term): bool
    {
        return self::simpleIndexTermIdentifier($term) === null;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $sql): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === $quote) {
                    if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                        $buffer .= $sql[++$i];
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === '[') {
                $quote = ']';
                $buffer .= $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }

        $parts[] = $buffer;

        return $parts;
    }

    private static function matchingParen(string $sql, int $open): ?int
    {
        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        for ($i = $open; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if (($sql[$i + 1] ?? null) === $quote) {
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
                continue;
            }
            if ($char === '[') {
                $quote = ']';
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private static function sqlReferencesIdentifier(string $sql, string $identifier): bool
    {
        return preg_match('/(?<![A-Za-z0-9_])' . preg_quote($identifier, '/') . '(?![A-Za-z0-9_])/i', $sql) === 1;
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
            if (($operation['kind'] ?? '') === 'create_table' || ($operation['kind'] ?? '') === 'alter_table_rename' || ($operation['kind'] ?? '') === 'alter_table_rename_column' || ($operation['kind'] ?? '') === 'alter_table_add_column') {
                $table = (string) (($operation['new_name'] ?? null) ?: ($operation['name'] ?? ''));
                if (($operation['kind'] ?? '') === 'alter_table_rename_column') {
                    $table = (string) ($operation['table'] ?? '');
                }
                if (($operation['kind'] ?? '') === 'alter_table_add_column') {
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
