<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaAutoindexForeignKeyPreflight
{
    /**
     * @param array<string,list<SQLiteSchemaRecord>> $recordsBySchema
     * @param array<string,list<array<string,mixed>>> $foreignKeysBySchema
     * @return array{
     *   status:string,
     *   schemas:list<string>,
     *   autoindexes:list<array{schema:string,table:string,name:string,expected:int,actual:int,rootpage:int|null,columns:list<string>,collations:list<string>,origin:string,unique:int,status:string}>,
     *   foreign_keys:list<array{schema:string,table:string,parent:string,columns:list<string>,parent_columns:list<string>,required_autoindex:string|null,status:string}>,
     *   current:array{source:string,autoindex_errors:int,foreign_key_parent_errors:int},
     *   next:array{source:string,ready:bool,blocking:list<string>}
     * }
     */
    public static function planCurrentSource(
        array $recordsBySchema,
        array $foreignKeysBySchema,
        string $currentSource,
        string $nextSource,
    ): array {
        if (trim($currentSource) === '' || trim($nextSource) === '') {
            throw new InvalidArgumentException('SQLite autoindex foreign-key current-source preflight requires current and next source identifiers');
        }

        $schemas = [];
        $autoindexes = [];
        $foreignKeys = [];
        $autoindexErrors = 0;
        $foreignKeyErrors = 0;

        foreach (array_values(array_unique(array_merge(['temp', 'main'], array_keys($recordsBySchema), array_keys($foreignKeysBySchema)))) as $schema) {
            $records = $recordsBySchema[$schema] ?? [];
            $foreignKeysForSchema = $foreignKeysBySchema[$schema] ?? [];
            if (!is_string($schema) || ($records === [] && $foreignKeysForSchema === [])) {
                continue;
            }
            $schemas[] = $schema;
            $plan = self::plan($records, $foreignKeysForSchema);
            $autoindexErrors += $plan['current']['autoindex_errors'];
            $foreignKeyErrors += $plan['current']['foreign_key_parent_errors'];

            foreach ($plan['autoindexes'] as $row) {
                $autoindexes[] = ['schema' => $schema] + $row;
            }
            foreach ($plan['foreign_keys'] as $row) {
                $foreignKeys[] = ['schema' => $schema] + $row;
            }
        }

        $blocking = [];
        if ($autoindexErrors > 0) {
            $blocking[] = 'autoindex_catalog_current_source';
        }
        if ($foreignKeyErrors > 0) {
            $blocking[] = 'foreign_key_parent_autoindex_current_source';
        }

        return [
            'status' => $blocking === [] ? 'ready' : 'blocked',
            'schemas' => $schemas,
            'autoindexes' => $autoindexes,
            'foreign_keys' => $foreignKeys,
            'current' => [
                'source' => $currentSource,
                'autoindex_errors' => $autoindexErrors,
                'foreign_key_parent_errors' => $foreignKeyErrors,
            ],
            'next' => [
                'source' => $nextSource,
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<array<string,mixed>> $foreignKeys
     * @return array{
     *   status:string,
     *   tables:list<string>,
     *   autoindexes:list<array{table:string,name:string,expected:int,actual:int,rootpage:int|null,columns:list<string>,collations:list<string>,origin:string,unique:int,status:string}>,
     *   foreign_keys:list<array{table:string,parent:string,columns:list<string>,parent_columns:list<string>,required_autoindex:string|null,status:string}>,
     *   current:array{autoindex_errors:int,foreign_key_parent_errors:int},
     *   next:array{ready:bool,blocking:list<string>}
     * }
     */
    public static function plan(array $records, array $foreignKeys): array
    {
        $tables = self::tableRecords($records);
        $catalog = new SQLitePragmaSchemaCatalog($records);
        $indexes = self::indexesByTable($records);
        $autoindexes = [];
        $autoindexErrors = 0;

        foreach ($tables as $tableName => $table) {
            if ($table->sql === null) {
                continue;
            }

            $expected = SQLiteCreateTable::automaticIndexColumnMetadata($table->sql);
            foreach ($expected as $offset => $columns) {
                $ordinal = $offset + 1;
                $expectedName = "sqlite_autoindex_{$table->name}_{$ordinal}";
                $actual = self::findIndex($indexes[$tableName] ?? [], $expectedName);
                $indexListRow = self::indexListRow($catalog, $table->name, $expectedName);
                $xinfoRows = $actual === null ? [] : $catalog->execute("PRAGMA index_xinfo({$expectedName})")['rows'];
                $actualColumns = array_values(array_filter(
                    array_map(static fn (array $row): ?string => $row['key'] === 1 ? (string) $row['name'] : null, $xinfoRows),
                    static fn (?string $name): bool => $name !== null
                ));
                $expectedColumns = array_map(static fn (SQLiteIndexColumn $column): string => $column->columnName, $columns);
                $expectedCollations = array_map(static fn (SQLiteIndexColumn $column): string => strtoupper($column->collation), $columns);
                $actualCollations = array_values(array_filter(
                    array_map(static fn (array $row): ?string => $row['key'] === 1 ? strtoupper((string) $row['coll']) : null, $xinfoRows),
                    static fn (?string $name): bool => $name !== null
                ));

                $ok = $actual !== null
                    && $actual->rootPage !== null
                    && self::sameIdentifierList($expectedColumns, $actualColumns)
                    && $expectedCollations === $actualCollations
                    && ($indexListRow['origin'] ?? null) === 'u'
                    && ($indexListRow['unique'] ?? null) === 1;
                if (!$ok) {
                    $autoindexErrors++;
                }

                $autoindexes[] = [
                    'table' => $table->name,
                    'name' => $expectedName,
                    'expected' => count($expectedColumns),
                    'actual' => count($actualColumns),
                    'rootpage' => $actual?->rootPage,
                    'columns' => $actualColumns,
                    'collations' => $actualCollations,
                    'origin' => (string) ($indexListRow['origin'] ?? ''),
                    'unique' => (int) ($indexListRow['unique'] ?? 0),
                    'status' => $ok ? 'ok' : 'blocked',
                ];
            }
        }

        $foreignKeyRows = [];
        $foreignKeyErrors = 0;
        foreach ($foreignKeys as $ordinal => $foreignKey) {
            $normalized = self::normalizeForeignKey($foreignKey, $ordinal);
            $required = self::matchingAutoindex($autoindexes, $normalized['parent'], $normalized['parent_columns']);
            $ok = $required !== null || self::parentIsRowidAlias($tables[strtolower($normalized['parent'])] ?? null, $normalized['parent_columns']);
            if (!$ok) {
                $foreignKeyErrors++;
            }
            $foreignKeyRows[] = [
                'table' => $normalized['table'],
                'parent' => $normalized['parent'],
                'columns' => $normalized['columns'],
                'parent_columns' => $normalized['parent_columns'],
                'required_autoindex' => $required,
                'status' => $ok ? 'ok' : 'blocked',
            ];
        }

        $blocking = [];
        if ($autoindexErrors > 0) {
            $blocking[] = 'autoindex_catalog';
        }
        if ($foreignKeyErrors > 0) {
            $blocking[] = 'foreign_key_parent_autoindex';
        }

        return [
            'status' => $blocking === [] ? 'ready' : 'blocked',
            'tables' => array_map(static fn (SQLiteSchemaRecord $record): string => $record->name, array_values($tables)),
            'autoindexes' => $autoindexes,
            'foreign_keys' => $foreignKeyRows,
            'current' => [
                'autoindex_errors' => $autoindexErrors,
                'foreign_key_parent_errors' => $foreignKeyErrors,
            ],
            'next' => [
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,SQLiteSchemaRecord>
     */
    private static function tableRecords(array $records): array
    {
        $tables = [];
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite autoindex preflight records must be SQLiteSchemaRecord instances');
            }
            if ($record->type === 'table') {
                $tables[strtolower($record->name)] = $record;
            }
        }

        return $tables;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,list<SQLiteSchemaRecord>>
     */
    private static function indexesByTable(array $records): array
    {
        $indexes = [];
        foreach ($records as $record) {
            if ($record->type === 'index') {
                $indexes[strtolower($record->tableName)][] = $record;
            }
        }

        return $indexes;
    }

    /**
     * @param list<SQLiteSchemaRecord> $indexes
     */
    private static function findIndex(array $indexes, string $name): ?SQLiteSchemaRecord
    {
        foreach ($indexes as $index) {
            if (strcasecmp($index->name, $name) === 0) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return array{seq:int,name:string,unique:int,origin:string,partial:int}|null
     */
    private static function indexListRow(SQLitePragmaSchemaCatalog $catalog, string $tableName, string $indexName): ?array
    {
        foreach ($catalog->execute("PRAGMA index_list({$tableName})")['rows'] as $row) {
            if (strcasecmp((string) $row['name'], $indexName) === 0) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array{table:string,parent:string,columns:list<string>,parent_columns:list<string>}
     */
    private static function normalizeForeignKey(array $foreignKey, int $ordinal): array
    {
        $table = self::identifier($foreignKey['table'] ?? null, 'child table');
        $parent = self::identifier($foreignKey['parent'] ?? null, 'parent table');
        $columns = $foreignKey['columns'] ?? null;
        if (!is_array($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite autoindex preflight foreign key {$ordinal} needs columns");
        }

        $childColumns = [];
        $parentColumns = [];
        foreach ($columns as $child => $parentColumn) {
            if (is_int($child) && is_array($parentColumn)) {
                $childColumns[] = self::identifier($parentColumn['child'] ?? null, 'child column');
                $parentColumns[] = self::identifier($parentColumn['parent'] ?? null, 'parent column');
                continue;
            }
            $childColumns[] = self::identifier($child, 'child column');
            $parentColumns[] = self::identifier($parentColumn, 'parent column');
        }

        return [
            'table' => $table,
            'parent' => $parent,
            'columns' => $childColumns,
            'parent_columns' => $parentColumns,
        ];
    }

    /**
     * @param list<array{table:string,name:string,columns:list<string>,status:string}> $autoindexes
     * @param list<string> $parentColumns
     */
    private static function matchingAutoindex(array $autoindexes, string $parent, array $parentColumns): ?string
    {
        foreach ($autoindexes as $autoindex) {
            if ($autoindex['status'] !== 'ok' || strcasecmp($autoindex['table'], $parent) !== 0) {
                continue;
            }
            if (self::sameIdentifierList($autoindex['columns'], $parentColumns)) {
                return $autoindex['name'];
            }
        }

        return null;
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private static function sameIdentifierList(array $left, array $right): bool
    {
        return array_map('strtolower', $left) === array_map('strtolower', $right);
    }

    /**
     * @param list<string> $parentColumns
     */
    private static function parentIsRowidAlias(?SQLiteSchemaRecord $table, array $parentColumns): bool
    {
        if ($table === null || $table->sql === null || count($parentColumns) !== 1) {
            return false;
        }

        return preg_match(
            '/\b' . preg_quote($parentColumns[0], '/') . '\b\s+INTEGER\s+PRIMARY\s+KEY\b/i',
            $table->sql
        ) === 1;
    }

    private static function identifier(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite autoindex preflight {$label} is malformed");
        }

        return $value;
    }
}
