<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexIntegrityCursorCurrentSourceNext
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
        $cursor = $tableValued
            ? $catalog->executeTableValuedPragmaCursor($indexListSql)
            : ($catalog instanceof SQLiteAttachedSchemaCatalog
                ? $catalog->executeSchemaPragmaCursor($indexListSql)
                : $catalog->executeCursor($indexListSql));
        $metadata = $cursor->metadata();
        if (($metadata['pragma'] ?? null) !== 'index_list') {
            throw new InvalidArgumentException('SQLite PRAGMA index integrity cursor requires index_list rows');
        }

        $schema = (string) ($metadata['schema'] ?? 'main');
        $table = (string) ($metadata['target'] ?? '');
        $rows = [];
        foreach ($cursor->rows() as $indexRow) {
            $indexName = (string) ($indexRow['name'] ?? '');
            if ($indexName === '') {
                continue;
            }

            $rows[] = [
                'kind' => 'index_list',
                'source' => 'index_list',
                'schema' => $schema,
                'table' => $table,
                'index' => $indexName,
                'seq' => $indexRow['seq'] ?? null,
                'unique' => $indexRow['unique'] ?? null,
                'origin' => $indexRow['origin'] ?? null,
                'partial' => $indexRow['partial'] ?? null,
                'seqno' => null,
                'cid' => null,
                'name' => $indexName,
                'desc' => null,
                'coll' => null,
                'key' => null,
                'rootpage' => null,
                'page_status' => null,
                'message' => self::indexListMessage($schema, $table, $indexRow),
            ];

            foreach (SQLitePragmaIntegrityIndexRootpageCurrentSourceNext::collect(
                $catalog,
                self::indexXinfoSql($schema, $indexName),
                $database,
                $integritySql,
                false,
            ) as $detailRow) {
                $rows[] = [
                    ...$detailRow,
                    'source' => ($detailRow['kind'] ?? null) === 'index_xinfo' ? 'index_xinfo' : 'rootpage_integrity',
                    'table' => $detailRow['table'] ?? $table,
                    'index' => $indexName,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array{database:string,catalog:string,index_list_sql:string,integrity_sql:string,table_valued:bool},offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{index_list:int,index_xinfo:int,rootpage:int,rootpage_errors:int,target_schema:string,target_table:string,indexes:list<string>},next:array{source_id:string,offset:int}|null,current_row:array<string,mixed>|null,next_row:array<string,mixed>|null,rows:list<array<string,mixed>>}
     */
    public static function page(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexListSql,
        string|SQLiteDatabase $database,
        int $offset = 0,
        int $limit = 133,
        string $integritySql = 'PRAGMA integrity_check',
        bool $tableValued = false,
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index integrity current-source cursor offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA index integrity current-source cursor limit must be positive');
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
            'current_row' => $pageRows[0] ?? null,
            'next_row' => $pageRows[1] ?? null,
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{index_list:int,index_xinfo:int,rootpage:int,rootpage_errors:int,target_schema:string,target_table:string,indexes:list<string>}
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'index_list' => 0,
            'index_xinfo' => 0,
            'rootpage' => 0,
            'rootpage_errors' => 0,
            'target_schema' => 'main',
            'target_table' => '',
            'indexes' => [],
        ];
        foreach ($rows as $row) {
            $counts['target_schema'] = (string) ($row['schema'] ?? $counts['target_schema']);
            $counts['target_table'] = (string) ($row['table'] ?? $counts['target_table']);
            if (($row['kind'] ?? null) === 'index_list') {
                $counts['index_list']++;
                $index = (string) ($row['index'] ?? $row['name'] ?? '');
                if ($index !== '' && !in_array($index, $counts['indexes'], true)) {
                    $counts['indexes'][] = $index;
                }
            } elseif (($row['kind'] ?? null) === 'index_xinfo') {
                $counts['index_xinfo']++;
            } elseif (($row['kind'] ?? null) === 'rootpage') {
                $counts['rootpage']++;
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

    private static function indexXinfoSql(string $schema, string $indexName): string
    {
        return sprintf('PRAGMA %s.index_xinfo(%s)', $schema, self::quoteIdentifier($indexName));
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * @param array<string,int|string|null> $indexRow
     */
    private static function indexListMessage(string $schema, string $table, array $indexRow): string
    {
        return sprintf(
            'index_list %s.%s seq %d index %s unique %d origin %s partial %d',
            $schema,
            $table,
            (int) ($indexRow['seq'] ?? 0),
            (string) ($indexRow['name'] ?? ''),
            (int) ($indexRow['unique'] ?? 0),
            (string) ($indexRow['origin'] ?? ''),
            (int) ($indexRow['partial'] ?? 0),
        );
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
            throw new InvalidArgumentException('SQLite PRAGMA index integrity current-source cursor does not match the current database/catalog source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index integrity current-source cursor offset does not match the requested page offset');
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
