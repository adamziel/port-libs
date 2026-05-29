<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexForeignKeyIntegrityCurrentSourceNext
{
    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<array<string,mixed>> $foreignKeys
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array<string,mixed>,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,mixed>,next:array<string,mixed>|null,current_row:array<string,mixed>|null,next_row:array<string,mixed>|null,rows:list<array<string,mixed>>}
     */
    public static function page(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexListSql,
        string|SQLiteDatabase $database,
        array $records,
        array $foreignKeys,
        array $tables,
        string $currentSource,
        string $nextSource,
        int $offset = 0,
        int $limit = 137,
        string $integritySql = 'PRAGMA integrity_check',
        bool $tableValuedIndexList = false,
        ?array $cursor = null,
    ): array {
        if ($currentSource === '' || $nextSource === '') {
            throw new InvalidArgumentException('SQLite PRAGMA index/foreign-key integrity current-source next137 requires current and next source identifiers');
        }
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index/foreign-key integrity current-source next137 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA index/foreign-key integrity current-source next137 limit must be positive');
        }

        $source = self::source($catalog, $indexListSql, $database, $records, $foreignKeys, $tables, $currentSource, $nextSource, $integritySql, $tableValuedIndexList);
        if ($cursor !== null) {
            self::validateCursor($cursor, $source['source_id'], $offset);
        }

        $rows = self::collect($catalog, $indexListSql, $database, $records, $foreignKeys, $tables, $integritySql, $tableValuedIndexList);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $counts = self::counts($rows);
        $blocking = self::blocking($counts);

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'source_id' => $source['source_id'],
            'current_source' => [
                'current' => $source['current'],
                'next' => $source['next'],
                'catalog' => $source['catalog'],
                'database' => $source['database'],
                'records_hash' => $source['records_hash'],
                'foreign_key_hash' => $source['foreign_key_hash'],
                'table_hash' => $source['table_hash'],
                'index_list_sql' => $source['index_list_sql'],
                'integrity_sql' => $source['integrity_sql'],
                'table_valued_index_list' => $source['table_valued_index_list'],
            ],
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => $counts,
            'next' => $complete ? null : [
                'source_id' => $source['source_id'],
                'offset' => $nextOffset,
                'ready' => $blocking === [],
                'blocking' => $blocking,
                'first_row' => self::boundaryRow($pageRows[0] ?? null),
                'last_row' => self::boundaryRow($pageRows[count($pageRows) - 1] ?? null),
            ],
            'current_row' => $pageRows[0] ?? null,
            'next_row' => $pageRows[1] ?? null,
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<array<string,mixed>> $foreignKeys
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<array<string,mixed>>
     */
    public static function collect(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexListSql,
        string|SQLiteDatabase $database,
        array $records,
        array $foreignKeys,
        array $tables,
        string $integritySql = 'PRAGMA integrity_check',
        bool $tableValuedIndexList = false,
    ): array {
        $rows = [];

        foreach (SQLitePragmaIndexIntegrityCursorCurrentSourceNext::collect($catalog, $indexListSql, $database, $integritySql, $tableValuedIndexList) as $row) {
            $rows[] = [
                ...$row,
                'group' => 'pragma_index_integrity',
                'source' => 'index_' . (string) ($row['source'] ?? $row['kind'] ?? 'row'),
            ];
        }

        foreach (SQLitePragmaForeignKeyIndexIntegrityYield::collect($records, $foreignKeys, $tables) as $row) {
            $source = ($row['kind'] ?? null) === 'foreign_key_check' ? 'foreign_key_check' : 'foreign_key_parent_index';
            $rows[] = [
                ...$row,
                'group' => 'pragma_foreign_key_integrity',
                'source' => $source,
                'schema' => $row['schema'] ?? null,
                'page_status' => null,
                'rootpage' => null,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{index_list:int,index_xinfo:int,rootpage:int,rootpage_errors:int,index_admissions:int,index_blockers:int,foreign_key_violations:int,target_schema:string,target_table:string,indexes:list<string>}
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'index_list' => 0,
            'index_xinfo' => 0,
            'rootpage' => 0,
            'rootpage_errors' => 0,
            'index_admissions' => 0,
            'index_blockers' => 0,
            'foreign_key_violations' => 0,
            'target_schema' => 'main',
            'target_table' => '',
            'indexes' => [],
        ];

        foreach ($rows as $row) {
            $counts['target_schema'] = (string) ($row['schema'] ?? $counts['target_schema']);
            $counts['target_table'] = (string) ($row['table'] ?? $counts['target_table']);
            $kind = (string) ($row['kind'] ?? '');
            if ($kind === 'index_list') {
                $counts['index_list']++;
                $index = (string) ($row['index'] ?? $row['name'] ?? '');
                if ($index !== '' && !in_array($index, $counts['indexes'], true)) {
                    $counts['indexes'][] = $index;
                }
            } elseif ($kind === 'index_xinfo') {
                $counts['index_xinfo']++;
            } elseif ($kind === 'rootpage') {
                $counts['rootpage']++;
                if (($row['page_status'] ?? 'ok') !== 'ok') {
                    $counts['rootpage_errors']++;
                }
            } elseif ($kind === 'index_admission') {
                $counts['index_admissions']++;
                if (($row['status'] ?? 'ok') !== 'ok') {
                    $counts['index_blockers']++;
                }
            } elseif ($kind === 'foreign_key_check') {
                $counts['foreign_key_violations']++;
            }
        }

        return $counts;
    }

    /**
     * @param array{rootpage_errors:int,index_blockers:int,foreign_key_violations:int} $counts
     * @return list<string>
     */
    private static function blocking(array $counts): array
    {
        $blocking = [];
        if ($counts['rootpage_errors'] > 0) {
            $blocking[] = 'index_rootpage_integrity';
        }
        if ($counts['index_blockers'] > 0) {
            $blocking[] = 'foreign_key_parent_unique_index';
        }
        if ($counts['foreign_key_violations'] > 0) {
            $blocking[] = 'foreign_key_check';
        }

        return $blocking;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<array<string,mixed>> $foreignKeys
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,mixed>
     */
    private static function source(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexListSql,
        string|SQLiteDatabase $database,
        array $records,
        array $foreignKeys,
        array $tables,
        string $currentSource,
        string $nextSource,
        string $integritySql,
        bool $tableValuedIndexList,
    ): array {
        $source = [
            'current' => $currentSource,
            'next' => $nextSource,
            'catalog' => self::catalogHash($catalog),
            'database' => is_string($database) ? hash('sha256', $database) : self::databaseHash($database),
            'records_hash' => self::stableHash(array_map(
                static fn (SQLiteSchemaRecord $record): array => [
                    'type' => $record->type,
                    'name' => $record->name,
                    'table' => $record->tableName,
                    'rootpage' => $record->rootPage,
                    'sql' => $record->sql,
                    'rowid' => $record->rowId,
                ],
                $records,
            )),
            'foreign_key_hash' => self::stableHash($foreignKeys),
            'table_hash' => self::stableHash($tables),
            'index_list_sql' => self::normalizeSql($indexListSql),
            'integrity_sql' => self::normalizeSql($integritySql),
            'table_valued_index_list' => $tableValuedIndexList,
        ];

        return [
            ...$source,
            'source_id' => self::stableHash($source),
        ];
    }

    private static function databaseHash(SQLiteDatabase $database): string
    {
        $context = hash_init('sha256');
        hash_update($context, (string) $database->header->pageSize);
        hash_update($context, ':');
        hash_update($context, (string) $database->pageCount());
        for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
            hash_update($context, $database->page($pageNumber));
        }

        return hash_final($context);
    }

    private static function catalogHash(SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog): string
    {
        if ($catalog instanceof SQLiteAttachedSchemaCatalog) {
            $snapshot = [
                'database_list' => $catalog->databaseList(),
                'schema_generation' => $catalog->schemaGeneration(),
                'search_order' => $catalog->searchOrder(),
                'schemas' => [],
            ];
            foreach ($catalog->databaseList() as $database) {
                $schema = (string) $database['name'];
                $snapshot['schemas'][$schema] = self::schemaRecordsSnapshot($catalog->schemaRecords($schema));
            }

            return self::stableHash($snapshot);
        }

        return self::stableHash([
            'schema' => 'main',
            'records' => self::schemaRecordsSnapshot($catalog->records()),
        ]);
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array{type:string,name:string,table:string,rootpage:int|null,sql:string|null,rowid:int}>
     */
    private static function schemaRecordsSnapshot(array $records): array
    {
        return array_map(
            static fn (SQLiteSchemaRecord $record): array => [
                'type' => $record->type,
                'name' => $record->name,
                'table' => $record->tableName,
                'rootpage' => $record->rootPage,
                'sql' => $record->sql,
                'rowid' => $record->rowId,
            ],
            $records,
        );
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index/foreign-key integrity current-source next137 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index/foreign-key integrity current-source next137 cursor offset does not match the requested page offset');
        }
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array{kind:string,source:string,group:string,table:string|null,rowid:int|string|null,index:string|null,parent:string|null}|null
     */
    private static function boundaryRow(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'kind' => (string) ($row['kind'] ?? ''),
            'source' => (string) ($row['source'] ?? ''),
            'group' => (string) ($row['group'] ?? ''),
            'table' => $row['table'] ?? null,
            'rowid' => $row['rowid'] ?? null,
            'index' => $row['index'] ?? null,
            'parent' => $row['parent'] ?? null,
        ];
    }

    private static function normalizeSql(string $sql): string
    {
        return strtolower(preg_replace('/\s+/', ' ', rtrim(trim($sql), ';')) ?? trim($sql));
    }

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', self::stableEncode($value));
    }

    private static function stableEncode(mixed $value): string
    {
        if (is_array($value)) {
            if (!array_is_list($value)) {
                ksort($value);
            }

            return '[' . implode(',', array_map(static fn (mixed $item, string|int $key): string => self::stableEncode((string) $key) . ':' . self::stableEncode($item), $value, array_keys($value))) . ']';
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
