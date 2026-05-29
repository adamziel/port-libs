<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegrityIndexRootpageCurrentSourceNext
{
    /**
     * @return list<array<string,mixed>>
     */
    public static function collect(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        string $integritySql = 'PRAGMA integrity_check',
        bool $tableValued = false,
    ): array {
        $indexRows = SQLitePragmaIndexXinfoIntegrityRootYield::collect($catalog, $indexXinfoSql, $database, $integritySql, $tableValued);
        $target = self::targetFromRows($indexRows, $catalog, $indexXinfoSql, $tableValued);
        $rootpageRows = self::rootpageRows($database, $target);

        return [
            ...array_map(
                static fn (array $row): array => [
                    ...$row,
                    'source' => 'index_xinfo',
                    'rootpage' => null,
                    'page_status' => null,
                    'page_type' => null,
                    'pointer_map_type' => null,
                    'pointer_map_parent' => null,
                    'pointer_map_page' => null,
                ],
                array_values(array_filter($indexRows, static fn (array $row): bool => ($row['kind'] ?? null) === 'index_xinfo')),
            ),
            ...$rootpageRows,
        ];
    }

    /**
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array{database:string,catalog:string,index_xinfo_sql:string,integrity_sql:string,table_valued:bool},offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{index_xinfo:int,rootpage:int,rootpage_errors:int,target_schema:string,target_index:string|null,target_table:string|null},next:array{source_id:string,offset:int}|null,next_row:array<string,mixed>|null,rows:list<array<string,mixed>>}
     */
    public static function page(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        int $offset = 0,
        int $limit = 124,
        string $integritySql = 'PRAGMA integrity_check',
        bool $tableValued = false,
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity index rootpage current-source next124 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity index rootpage current-source next124 limit must be positive');
        }

        $source = self::source($catalog, $indexXinfoSql, $database, $integritySql, $tableValued);
        if ($cursor !== null) {
            self::validateCursor($cursor, $source['source_id'], $offset);
        }

        $rows = self::collect($catalog, $indexXinfoSql, $database, $integritySql, $tableValued);
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
                'index_xinfo_sql' => $source['index_xinfo_sql'],
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
     * @param list<array<string,mixed>> $indexRows
     * @return array{schema:string,index:string|null,table:string|null}
     */
    private static function targetFromRows(array $indexRows, SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog, string $indexXinfoSql, bool $tableValued): array
    {
        foreach ($indexRows as $row) {
            if (($row['kind'] ?? null) === 'index_xinfo') {
                return [
                    'schema' => (string) $row['schema'],
                    'index' => (string) $row['target'],
                    'table' => self::tableForIndex($catalog, (string) $row['schema'], (string) $row['target']),
                ];
            }
        }

        $cursor = $tableValued
            ? $catalog->executeTableValuedPragmaCursor($indexXinfoSql)
            : ($catalog instanceof SQLiteAttachedSchemaCatalog
                ? $catalog->executeSchemaPragmaCursor($indexXinfoSql)
                : $catalog->executeCursor($indexXinfoSql));
        $metadata = $cursor->metadata();

        return [
            'schema' => (string) ($metadata['schema'] ?? 'main'),
            'index' => isset($metadata['target']) ? (string) $metadata['target'] : null,
            'table' => isset($metadata['target']) ? self::tableForIndex($catalog, (string) ($metadata['schema'] ?? 'main'), (string) $metadata['target']) : null,
        ];
    }

    private static function tableForIndex(SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog, string $schema, string $index): ?string
    {
        $records = $catalog instanceof SQLiteAttachedSchemaCatalog ? $catalog->schemaRecords($schema) : $catalog->records();
        foreach ($records as $record) {
            if ($record->type === 'index' && strcasecmp($record->name, $index) === 0) {
                return $record->tableName;
            }
        }

        return null;
    }

    /**
     * @param array{schema:string,index:string|null,table:string|null} $target
     * @return list<array<string,mixed>>
     */
    private static function rootpageRows(string|SQLiteDatabase $database, array $target): array
    {
        $analysis = SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext::analyze($database);
        $rows = [];
        foreach ($analysis['rows'] as $row) {
            $name = (string) ($row['name'] ?? '');
            $table = (string) ($row['table'] ?? '');
            $kind = (string) ($row['kind'] ?? '');
            $isTargetTableRoot = ($row['type'] ?? null) === 'table' && $target['table'] !== null && $name === $target['table'];
            $isTargetIndexRoot = ($row['type'] ?? null) === 'index' && $name === (string) $target['index'];
            if ($kind !== 'largest_root_mismatch' && !$isTargetTableRoot && !$isTargetIndexRoot) {
                continue;
            }

            $rows[] = [
                'kind' => 'rootpage',
                'source' => 'rootpage_integrity',
                'schema' => $target['schema'],
                'target' => $target['index'],
                'seqno' => null,
                'cid' => null,
                'name' => $row['name'] ?? null,
                'desc' => null,
                'coll' => null,
                'key' => null,
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
     * @return array{index_xinfo:int,rootpage:int,rootpage_errors:int,target_schema:string,target_index:string|null,target_table:string|null}
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'index_xinfo' => 0,
            'rootpage' => 0,
            'rootpage_errors' => 0,
            'target_schema' => 'main',
            'target_index' => null,
            'target_table' => null,
        ];
        foreach ($rows as $row) {
            if (($row['kind'] ?? null) === 'index_xinfo') {
                $counts['index_xinfo']++;
                $counts['target_schema'] = (string) ($row['schema'] ?? 'main');
                $counts['target_index'] = isset($row['target']) ? (string) $row['target'] : null;
                continue;
            }
            if (($row['kind'] ?? null) === 'rootpage') {
                $counts['rootpage']++;
                $counts['target_schema'] = (string) ($row['schema'] ?? $counts['target_schema']);
                $counts['target_index'] = isset($row['target']) ? (string) $row['target'] : $counts['target_index'];
                if (($row['type'] ?? null) === 'table') {
                    $counts['target_table'] = isset($row['name']) ? (string) $row['name'] : $counts['target_table'];
                } elseif (($row['table'] ?? null) !== null) {
                    $counts['target_table'] = (string) $row['table'];
                }
                if (($row['page_status'] ?? 'ok') !== 'ok') {
                    $counts['rootpage_errors']++;
                }
            }
        }

        return $counts;
    }

    /**
     * @return array{source_id:string,database:string,catalog:string,index_xinfo_sql:string,integrity_sql:string,table_valued:bool}
     */
    private static function source(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        string $integritySql,
        bool $tableValued,
    ): array {
        $source = [
            'database' => is_string($database) ? hash('sha256', $database) : self::databaseHash($database),
            'catalog' => self::catalogHash($catalog),
            'index_xinfo_sql' => self::normalizeSql($indexXinfoSql),
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
            throw new InvalidArgumentException('SQLite PRAGMA integrity index rootpage current-source next124 cursor does not match the current database/catalog source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity index rootpage current-source next124 cursor offset does not match the requested page offset');
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
