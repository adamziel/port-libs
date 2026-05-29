<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexListRootpageIntegrityCurrentSourceNext
{
    /**
     * @return list<array<string,mixed>>
     */
    public static function collect(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexListSql,
        string|SQLiteDatabase $database,
        string $integritySql = 'PRAGMA integrity_check',
        bool $tableValued = false,
    ): array {
        $indexList = self::indexList($catalog, $indexListSql, $tableValued);
        $schema = (string) ($indexList['schema'] ?? 'main');
        $table = (string) ($indexList['target'] ?? '');
        $indexNames = array_map(static fn (array $row): string => (string) $row['name'], $indexList['rows']);

        return [
            ...array_map(
                static fn (array $row): array => [
                    ...$row,
                    'kind' => 'index_list',
                    'source' => 'index_list',
                    'schema' => $schema,
                    'target' => $table,
                    'rootpage' => null,
                    'page_status' => null,
                    'page_type' => null,
                    'pointer_map_type' => null,
                    'pointer_map_parent' => null,
                    'pointer_map_page' => null,
                    'message' => 'pragma index_list ' . $table . ' index ' . $row['name'],
                ],
                $indexList['rows'],
            ),
            ...self::rootpageRows($database, $schema, $table, $indexNames),
        ];
    }

    /**
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array{database:string,catalog:string,index_list_sql:string,integrity_sql:string,table_valued:bool},offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{index_list:int,rootpage:int,rootpage_errors:int,unique_indexes:int,partial_indexes:int,target_schema:string,target_table:string,target_indexes:list<string>},next:array{source_id:string,offset:int}|null,next_row:array<string,mixed>|null,rows:list<array<string,mixed>>}
     */
    public static function page(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexListSql,
        string|SQLiteDatabase $database,
        int $offset = 0,
        int $limit = 139,
        string $integritySql = 'PRAGMA integrity_check',
        bool $tableValued = false,
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_list rootpage integrity current-source next139 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA index_list rootpage integrity current-source next139 limit must be positive');
        }

        $source = self::source($catalog, $indexListSql, $database, $integritySql, $tableValued);
        if ($cursor !== null) {
            self::validateCursor($cursor, $source['source_id'], $offset);
        }

        $rows = self::collect($catalog, $indexListSql, $database, $integritySql, $tableValued);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $counts = self::counts($rows);

        return [
            'status' => $counts['rootpage_errors'] === 0 ? 'ok' : 'blocked',
            'source_id' => $source['source_id'],
            'current_source' => [
                'database' => $source['database'],
                'catalog' => $source['catalog'],
                'index_list_sql' => $source['index_list_sql'],
                'integrity_sql' => $source['integrity_sql'],
                'table_valued' => $source['table_valued'],
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
            ],
            'next_row' => $pageRows[1] ?? null,
            'rows' => $pageRows,
        ];
    }

    /**
     * @return array{status:string,pragma:string,schema:string,target:string,rows:list<array<string,int|string|null>>}
     */
    private static function indexList(SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog, string $sql, bool $tableValued): array
    {
        return $tableValued
            ? ($catalog instanceof SQLiteAttachedSchemaCatalog ? $catalog->executeTableValuedPragma($sql) : $catalog->executeTableValuedPragma($sql))
            : ($catalog instanceof SQLiteAttachedSchemaCatalog ? $catalog->executeSchemaPragma($sql) : $catalog->execute($sql));
    }

    /**
     * @param list<string> $indexNames
     * @return list<array<string,mixed>>
     */
    private static function rootpageRows(string|SQLiteDatabase $database, string $schema, string $table, array $indexNames): array
    {
        $analysis = SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext::analyze($database);
        $wantedIndexes = array_fill_keys(array_map('strtolower', $indexNames), true);
        $rows = [];

        foreach ($analysis['rows'] as $row) {
            $type = $row['type'] ?? null;
            $name = (string) ($row['name'] ?? '');
            $isTargetTable = $type === 'table' && strcasecmp($name, $table) === 0;
            $isTargetIndex = $type === 'index' && isset($wantedIndexes[strtolower($name)]);
            if (($row['kind'] ?? null) !== 'largest_root_mismatch' && !$isTargetTable && !$isTargetIndex) {
                continue;
            }

            $rows[] = [
                'kind' => 'rootpage',
                'source' => 'rootpage_integrity',
                'schema' => $schema,
                'target' => $table,
                'seq' => null,
                'name' => $row['name'] ?? null,
                'unique' => null,
                'origin' => null,
                'partial' => null,
                'type' => $row['type'] ?? null,
                'table' => $row['table'] ?? null,
                'rootpage' => $row['rootpage'] ?? null,
                'page_status' => $row['page_status'] ?? null,
                'page_type' => $row['page_type'] ?? null,
                'pointer_map_type' => $row['pointer_map_type'] ?? null,
                'pointer_map_parent' => $row['pointer_map_parent'] ?? null,
                'pointer_map_page' => $row['pointer_map_page'] ?? null,
                'message' => $row['message'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{index_list:int,rootpage:int,rootpage_errors:int,unique_indexes:int,partial_indexes:int,target_schema:string,target_table:string,target_indexes:list<string>}
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'index_list' => 0,
            'rootpage' => 0,
            'rootpage_errors' => 0,
            'unique_indexes' => 0,
            'partial_indexes' => 0,
            'target_schema' => 'main',
            'target_table' => '',
            'target_indexes' => [],
        ];

        foreach ($rows as $row) {
            if (($row['kind'] ?? null) === 'index_list') {
                $counts['index_list']++;
                $counts['target_schema'] = (string) ($row['schema'] ?? $counts['target_schema']);
                $counts['target_table'] = (string) ($row['target'] ?? $counts['target_table']);
                $counts['target_indexes'][] = (string) ($row['name'] ?? '');
                $counts['unique_indexes'] += (int) ($row['unique'] ?? 0) === 1 ? 1 : 0;
                $counts['partial_indexes'] += (int) ($row['partial'] ?? 0) === 1 ? 1 : 0;
                continue;
            }

            if (($row['kind'] ?? null) === 'rootpage') {
                $counts['rootpage']++;
                $counts['target_schema'] = (string) ($row['schema'] ?? $counts['target_schema']);
                $counts['target_table'] = (string) ($row['target'] ?? $counts['target_table']);
                if (($row['page_status'] ?? 'ok') !== 'ok') {
                    $counts['rootpage_errors']++;
                }
            }
        }

        return $counts;
    }

    /**
     * @return array{source_id:string,database:string,catalog:string,index_list_sql:string,integrity_sql:string,table_valued:bool}
     */
    private static function source(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexListSql,
        string|SQLiteDatabase $database,
        string $integritySql,
        bool $tableValued,
    ): array {
        $source = [
            'database' => is_string($database) ? hash('sha256', $database) : self::databaseHash($database),
            'catalog' => self::catalogHash($catalog),
            'index_list_sql' => self::normalizeSql($indexListSql),
            'integrity_sql' => self::normalizeSql($integritySql),
            'table_valued' => $tableValued,
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

    private static function normalizeSql(string $sql): string
    {
        return strtolower(preg_replace('/\s+/', ' ', rtrim(trim($sql), ';')) ?? trim($sql));
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index_list rootpage integrity current-source next139 cursor does not match the current database/catalog source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_list rootpage integrity current-source next139 cursor offset does not match the requested page offset');
        }
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
