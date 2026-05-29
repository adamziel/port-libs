<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexQuickCheckForeignKeyCurrentSourceNext
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $currentSchemas
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $nextSchemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array<string,mixed>,next_source:array<string,mixed>,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,mixed>,next_counts:array<string,mixed>,delta:array<string,mixed>,next_state:array{ready:bool,blocking:list<string>},next:array{source_id:string,offset:int}|null,rows:list<array<string,mixed>>}
     */
    public static function currentNextPage(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $currentCatalog,
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $nextCatalog,
        string $indexListSql,
        string|SQLiteDatabase $currentDatabase,
        array $currentSchemas,
        string|SQLiteDatabase $nextDatabase,
        array $nextSchemas,
        string $foreignKeySql,
        string $quickCheckSql,
        int $offset = 0,
        int $limit = 141,
        ?array $cursor = null,
        bool $tableValuedIndexList = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index quickcheck foreign-key current/next cursor offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA index quickcheck foreign-key current/next cursor limit must be positive');
        }

        $currentSource = self::source($currentCatalog, $indexListSql, $currentDatabase, $currentSchemas, $foreignKeySql, $quickCheckSql, $tableValuedIndexList);
        $nextSource = self::source($nextCatalog, $indexListSql, $nextDatabase, $nextSchemas, $foreignKeySql, $quickCheckSql, $tableValuedIndexList);
        $sourceId = self::stableHash([
            'current' => $currentSource['source_id'],
            'next' => $nextSource['source_id'],
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $currentRows = array_map(
            static fn (array $row): array => ['side' => 'current', ...$row],
            self::collect($currentCatalog, $indexListSql, $currentDatabase, $currentSchemas, $foreignKeySql, $quickCheckSql, $tableValuedIndexList),
        );
        $nextRows = array_map(
            static fn (array $row): array => ['side' => 'next', ...$row],
            self::collect($nextCatalog, $indexListSql, $nextDatabase, $nextSchemas, $foreignKeySql, $quickCheckSql, $tableValuedIndexList),
        );
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
            'current_source' => self::publicSource($currentSource),
            'next_source' => self::publicSource($nextSource),
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => $currentCounts,
            'next_counts' => $nextCounts,
            'delta' => [
                'index_root_errors' => $nextCounts['index_root_errors'] - $currentCounts['index_root_errors'],
                'integrity_root' => $nextCounts['integrity_root'] - $currentCounts['integrity_root'],
                'foreign_key' => $nextCounts['foreign_key'] - $currentCounts['foreign_key'],
                'total_blockers' => $nextCounts['total_blockers'] - $currentCounts['total_blockers'],
                'cleared' => $currentCounts['total_blockers'] > 0 && $nextCounts['total_blockers'] === 0,
            ],
            'next_state' => [
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
            'next' => $complete ? null : [
                'source_id' => $sourceId,
                'offset' => $nextOffset,
            ],
            'rows' => $pageRows,
        ];
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return list<array<string,mixed>>
     */
    public static function collect(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexListSql,
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql,
        string $quickCheckSql,
        bool $tableValuedIndexList = false,
    ): array {
        $indexRows = SQLitePragmaIndexIntegrityCursorCurrentSourceNext::collect(
            $catalog,
            $indexListSql,
            $database,
            self::indexQuickCheckSql($quickCheckSql),
            $tableValuedIndexList,
        );
        $pragmaRows = SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::collect(
            $database,
            $schemas,
            $foreignKeySql,
            $catalog instanceof SQLiteAttachedSchemaCatalog ? $catalog : null,
            $quickCheckSql,
        );

        return [...$indexRows, ...$pragmaRows];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{index_list:int,index_xinfo:int,index_root:int,index_root_errors:int,integrity_root:int,foreign_key:int,total_blockers:int,target_schema:string,target_table:string,indexes:list<string>}
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'index_list' => 0,
            'index_xinfo' => 0,
            'index_root' => 0,
            'index_root_errors' => 0,
            'integrity_root' => 0,
            'foreign_key' => 0,
            'total_blockers' => 0,
            'target_schema' => 'main',
            'target_table' => '',
            'indexes' => [],
        ];
        foreach ($rows as $row) {
            $kind = $row['kind'] ?? null;
            $counts['target_schema'] = (string) ($row['schema'] ?? $counts['target_schema']);
            $counts['target_table'] = (string) ($row['table'] ?? $counts['target_table']);
            if ($kind === 'index_list') {
                $counts['index_list']++;
                $index = (string) ($row['index'] ?? $row['name'] ?? '');
                if ($index !== '' && !in_array($index, $counts['indexes'], true)) {
                    $counts['indexes'][] = $index;
                }
            } elseif ($kind === 'index_xinfo') {
                $counts['index_xinfo']++;
            } elseif ($kind === 'rootpage') {
                $counts['index_root']++;
                if (($row['page_status'] ?? 'ok') !== 'ok') {
                    $counts['index_root_errors']++;
                    $counts['total_blockers']++;
                }
            } elseif ($kind === 'integrity_root') {
                $counts['integrity_root']++;
                $counts['total_blockers']++;
            } elseif ($kind === 'foreign_key_check') {
                $counts['foreign_key']++;
                $counts['total_blockers']++;
            }
        }

        return $counts;
    }

    /**
     * @param array{index_root_errors:int,integrity_root:int,foreign_key:int} $counts
     * @return list<string>
     */
    private static function blocking(array $counts): array
    {
        $blocking = [];
        if ($counts['index_root_errors'] > 0) {
            $blocking[] = 'index_rootpage';
        }
        if ($counts['integrity_root'] > 0) {
            $blocking[] = 'quick_check_root';
        }
        if ($counts['foreign_key'] > 0) {
            $blocking[] = 'foreign_key_check';
        }

        return $blocking;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array<string,mixed>
     */
    private static function source(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexListSql,
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql,
        string $quickCheckSql,
        bool $tableValuedIndexList,
    ): array {
        $source = [
            'database' => is_string($database) ? hash('sha256', $database) : self::databaseHash($database),
            'catalog' => self::catalogHash($catalog),
            'schemas' => self::stableHash($schemas),
            'index_list_sql' => self::normalizeSql($indexListSql),
            'foreign_key_sql' => self::normalizeSql($foreignKeySql),
            'quick_check_sql' => self::normalizeSql($quickCheckSql),
            'table_valued_index_list' => $tableValuedIndexList,
        ];

        return [
            ...$source,
            'source_id' => self::stableHash($source),
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function publicSource(array $source): array
    {
        unset($source['source_id']);

        return $source;
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

    private static function indexQuickCheckSql(string $sql): string
    {
        $normalized = self::normalizeSql($sql);
        if (preg_match('/^pragma\s+(?:(?:[a-z_][a-z0-9_]*)\s*\.\s*)?(quick_check|integrity_check)\b/i', $normalized, $matches) !== 1) {
            return $sql;
        }

        return 'PRAGMA ' . strtolower($matches[1]);
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index quickcheck foreign-key current/next cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index quickcheck foreign-key current/next cursor offset does not match the requested page offset');
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
