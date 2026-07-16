<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexListIntegrityRootpageCurrentSourceNext
{
    /**
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array<string,mixed>,next_source:array<string,mixed>,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,mixed>,next_counts:array<string,mixed>,delta:array<string,mixed>,next_state:array{ready:bool,blocking:list<string>},next:array{source_id:string,offset:int}|null,next_row:array<string,mixed>|null,rows:list<array<string,mixed>>}
     */
    public static function currentNextPage(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $currentCatalog,
        string $currentDatabase,
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $nextCatalog,
        string $nextDatabase,
        string $indexListSql,
        int $offset = 0,
        int $limit = 143,
        string $integritySql = 'PRAGMA integrity_check',
        bool $tableValued = false,
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_list integrity rootpage current-source next143 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA index_list integrity rootpage current-source next143 limit must be positive');
        }

        $currentSource = self::source($currentCatalog, $currentDatabase, $indexListSql, $integritySql, $tableValued);
        $nextSource = self::source($nextCatalog, $nextDatabase, $indexListSql, $integritySql, $tableValued);
        $sourceId = self::stableHash([
            'operation' => 'pragma-index-list-integrity-rootpage-current-source-next143',
            'current' => $currentSource,
            'next' => $nextSource,
        ]);

        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $currentRows = self::sideRows('current', SQLitePragmaIndexListRootpageIntegrityCurrentSourceNext::collect(
            $currentCatalog,
            $indexListSql,
            $currentDatabase,
            $integritySql,
            $tableValued,
        ));
        $nextRows = self::sideRows('next', SQLitePragmaIndexListRootpageIntegrityCurrentSourceNext::collect(
            $nextCatalog,
            $indexListSql,
            $nextDatabase,
            $integritySql,
            $tableValued,
        ));
        $rows = [...$currentRows, ...$nextRows];
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $currentCounts = self::counts($currentRows);
        $nextCounts = self::counts($nextRows);
        $blocking = self::blocking($nextCounts);

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'source_id' => $sourceId,
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => $currentCounts,
            'next_counts' => $nextCounts,
            'delta' => [
                'index_list' => $nextCounts['index_list'] - $currentCounts['index_list'],
                'rootpage' => $nextCounts['rootpage'] - $currentCounts['rootpage'],
                'rootpage_errors' => $nextCounts['rootpage_errors'] - $currentCounts['rootpage_errors'],
                'unique_indexes' => $nextCounts['unique_indexes'] - $currentCounts['unique_indexes'],
                'partial_indexes' => $nextCounts['partial_indexes'] - $currentCounts['partial_indexes'],
                'cleared' => $currentCounts['rootpage_errors'] > 0 && $nextCounts['rootpage_errors'] === 0,
            ],
            'next_state' => [
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
            'next' => $complete ? null : [
                'source_id' => $sourceId,
                'offset' => $nextOffset,
            ],
            'next_row' => $pageRows[1] ?? null,
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function sideRows(string $side, array $rows): array
    {
        return array_map(static fn (array $row): array => ['side' => $side, ...$row], $rows);
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
     * @param array<string,mixed> $counts
     * @return list<string>
     */
    private static function blocking(array $counts): array
    {
        $blocking = [];
        if (($counts['rootpage_errors'] ?? 0) > 0) {
            $blocking[] = 'integrity_rootpage';
        }
        if (($counts['index_list'] ?? 0) === 0) {
            $blocking[] = 'index_list';
        }

        return $blocking;
    }

    /**
     * @return array{database:string,catalog:string,index_list_sql:string,integrity_sql:string,table_valued:bool}
     */
    private static function source(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $database,
        string $indexListSql,
        string $integritySql,
        bool $tableValued,
    ): array {
        return [
            'database' => hash('sha256', $database),
            'catalog' => self::catalogHash($catalog),
            'index_list_sql' => self::normalizeSql($indexListSql),
            'integrity_sql' => self::normalizeSql($integritySql),
            'table_valued' => $tableValued,
        ];
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
            throw new InvalidArgumentException('SQLite PRAGMA index_list integrity rootpage current-source next143 cursor does not match the current/next source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_list integrity rootpage current-source next143 cursor offset does not match the requested page offset');
        }
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
