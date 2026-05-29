<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext164
{
    /**
     * @param list<SQLiteSchemaRecord> $currentRecords
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param list<SQLiteSchemaRecord> $nextRecords
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array<string,mixed>
     */
    public static function currentNextPageFromCatalog(
        array $currentRecords,
        array $currentTables,
        array $nextRecords,
        array $nextTables,
        string $indexXinfoSql,
        int $offset = 0,
        int $limit = 164,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        $currentForeignKeys = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext161::foreignKeysFromCatalog($currentRecords);
        $nextForeignKeys = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext161::foreignKeysFromCatalog($nextRecords);
        $currentCanonicalTables = self::canonicalTables($currentRecords, $currentTables);
        $nextCanonicalTables = self::canonicalTables($nextRecords, $nextTables);

        $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext156::currentNextPage(
            $currentRecords,
            $currentForeignKeys,
            $currentCanonicalTables,
            $nextRecords,
            $nextForeignKeys,
            $nextCanonicalTables,
            $indexXinfoSql,
            $offset,
            $limit,
            $cursor,
            $tableValuedIndexXinfo,
        );

        return [
            ...$page,
            'current_source' => [
                ...$page['current_source'],
                'foreign_key_source' => 'pragma_foreign_key_list',
                'table_key_source' => 'sqlite_schema_casefold',
                'column_key_source' => 'pragma_table_xinfo_casefold',
                'derived_foreign_keys' => count($currentForeignKeys),
            ],
            'next_source' => [
                ...$page['next_source'],
                'foreign_key_source' => 'pragma_foreign_key_list',
                'table_key_source' => 'sqlite_schema_casefold',
                'column_key_source' => 'pragma_table_xinfo_casefold',
                'derived_foreign_keys' => count($nextForeignKeys),
            ],
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    public static function canonicalTables(array $records, array $tables): array
    {
        $catalog = new SQLitePragmaSchemaCatalog($records);
        $canonical = [];
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next164 records must be SQLiteSchemaRecord instances');
            }
            if ($record->type !== 'table') {
                continue;
            }

            $rows = self::tableRows($tables, $record->name);
            $columns = array_map(static fn (array $row): string => (string) $row['name'], $catalog->tableInfo($record->name, true));
            $canonical[$record->name] = array_map(static fn (array $row): array => self::canonicalRow($row, $columns), $rows);
        }

        foreach ($tables as $name => $rows) {
            if (self::hasTable($canonical, $name)) {
                continue;
            }
            $canonical[$name] = $rows;
        }

        return $canonical;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<array<string,mixed>>
     */
    private static function tableRows(array $tables, string $name): array
    {
        if (array_key_exists($name, $tables)) {
            return $tables[$name];
        }
        foreach ($tables as $tableName => $rows) {
            if (strcasecmp($tableName, $name) === 0) {
                return $rows;
            }
        }

        return [];
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private static function canonicalRow(array $row, array $columns): array
    {
        foreach ($columns as $column) {
            if (array_key_exists($column, $row)) {
                continue;
            }
            foreach ($row as $key => $value) {
                if (is_string($key) && strcasecmp($key, $column) === 0) {
                    $row[$column] = $value;
                    break;
                }
            }
        }

        return $row;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     */
    private static function hasTable(array $tables, string $name): bool
    {
        foreach (array_keys($tables) as $tableName) {
            if (strcasecmp((string) $tableName, $name) === 0) {
                return true;
            }
        }

        return false;
    }
}
