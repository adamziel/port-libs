<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexListForeignKeyRootpageCurrentSourceNext
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $currentSchemas
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $nextSchemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array<string,mixed>,next_source:array<string,mixed>,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,mixed>,next_counts:array<string,mixed>,delta:array<string,mixed>,next_state:array{ready:bool,blocking:list<string>},next:array{source_id:string,offset:int}|null,rows:list<array<string,mixed>>}
     */
    public static function currentNextPage(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $currentCatalog,
        string $currentDatabase,
        array $currentSchemas,
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $nextCatalog,
        string $nextDatabase,
        array $nextSchemas,
        string $indexListSql,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        int $offset = 0,
        int $limit = 148,
        string $integritySql = 'PRAGMA integrity_check',
        bool $tableValuedIndexList = false,
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_list/FK rootpage current-source next148 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA index_list/FK rootpage current-source next148 limit must be positive');
        }
        if (!$currentCatalog instanceof SQLiteAttachedSchemaCatalog || !$nextCatalog instanceof SQLiteAttachedSchemaCatalog) {
            throw new InvalidArgumentException('SQLite PRAGMA index_list/FK rootpage current-source next148 requires attached schema catalogs for FK rootpage checks');
        }

        $currentSource = self::source($currentCatalog, $currentDatabase, $currentSchemas, $indexListSql, $foreignKeySql, $integritySql, $tableValuedIndexList);
        $nextSource = self::source($nextCatalog, $nextDatabase, $nextSchemas, $indexListSql, $foreignKeySql, $integritySql, $tableValuedIndexList);
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-list-foreignkey-rootpage-current-source-next148',
            'current' => $currentSource['source_id'],
            'next' => $nextSource['source_id'],
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $currentRows = self::sideRows('current', self::collect($currentCatalog, $currentDatabase, $currentSchemas, $indexListSql, $foreignKeySql, $integritySql, $tableValuedIndexList));
        $nextRows = self::sideRows('next', self::collect($nextCatalog, $nextDatabase, $nextSchemas, $indexListSql, $foreignKeySql, $integritySql, $tableValuedIndexList));
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
                'index_list' => $nextCounts['index_list'] - $currentCounts['index_list'],
                'index_rootpage_errors' => $nextCounts['index_rootpage_errors'] - $currentCounts['index_rootpage_errors'],
                'foreign_key_violations' => $nextCounts['foreign_key_violations'] - $currentCounts['foreign_key_violations'],
                'foreign_key_rootpage_errors' => $nextCounts['foreign_key_rootpage_errors'] - $currentCounts['foreign_key_rootpage_errors'],
                'pointer_map_conflicts' => $nextCounts['pointer_map_conflicts'] - $currentCounts['pointer_map_conflicts'],
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
        SQLiteAttachedSchemaCatalog $catalog,
        string $database,
        array $schemas,
        string $indexListSql,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        string $integritySql = 'PRAGMA integrity_check',
        bool $tableValuedIndexList = false,
    ): array {
        $indexRows = array_map(
            static fn (array $row): array => [
                ...$row,
                'phase' => match ($row['kind'] ?? null) {
                    'index_list' => 'index_list',
                    'rootpage' => 'index_rootpage',
                    default => 'index_catalog',
                },
            ],
            SQLitePragmaIndexListRootpageIntegrityCurrentSourceNext::collect($catalog, $indexListSql, $database, $integritySql, $tableValuedIndexList),
        );
        $foreignKeyRows = array_map(
            static fn (array $row): array => [
                ...$row,
                'phase' => 'foreign_key_rootpage',
                'kind' => 'foreign_key_rootpage',
            ],
            SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::collect($database, $schemas, $catalog, $foreignKeySql),
        );

        return [...$indexRows, ...$foreignKeyRows];
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
     * @return array<string,mixed>
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'index_list' => 0,
            'index_rootpage' => 0,
            'index_rootpage_errors' => 0,
            'foreign_key_violations' => 0,
            'foreign_key_rootpage_errors' => 0,
            'missing_catalog_rootpages' => 0,
            'pointer_map_conflicts' => 0,
            'total_blockers' => 0,
            'target_schema' => 'main',
            'target_table' => '',
            'indexes' => [],
            'foreign_key_tables' => [],
        ];

        foreach ($rows as $row) {
            $kind = $row['kind'] ?? null;
            if ($kind === 'index_list') {
                $counts['index_list']++;
                $counts['target_schema'] = (string) ($row['schema'] ?? $counts['target_schema']);
                $counts['target_table'] = (string) ($row['target'] ?? $counts['target_table']);
                $index = (string) ($row['name'] ?? '');
                if ($index !== '' && !in_array($index, $counts['indexes'], true)) {
                    $counts['indexes'][] = $index;
                }
                continue;
            }
            if ($kind === 'rootpage') {
                $counts['index_rootpage']++;
                if (($row['page_status'] ?? 'ok') !== 'ok') {
                    $counts['index_rootpage_errors']++;
                    $counts['total_blockers']++;
                }
                continue;
            }
            if ($kind !== 'foreign_key_rootpage') {
                continue;
            }

            $counts['foreign_key_violations']++;
            $table = (string) ($row['table'] ?? '');
            if ($table !== '' && !in_array($table, $counts['foreign_key_tables'], true)) {
                $counts['foreign_key_tables'][] = $table;
            }
            foreach (['child', 'parent'] as $side) {
                $status = $row[$side . '_rootpage_status'] ?? null;
                if ($status === 'missing_catalog_rootpage' || $status === 'missing_schema_rootpage') {
                    $counts['missing_catalog_rootpages']++;
                    $counts['foreign_key_rootpage_errors']++;
                    $counts['total_blockers']++;
                } elseif ($status !== 'ok') {
                    $counts['foreign_key_rootpage_errors']++;
                    $counts['total_blockers']++;
                }
                if ($status === 'pointer_map') {
                    $counts['pointer_map_conflicts']++;
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
        if (($counts['index_list'] ?? 0) === 0) {
            $blocking[] = 'index_list';
        }
        if (($counts['index_rootpage_errors'] ?? 0) > 0) {
            $blocking[] = 'index_rootpage';
        }
        if (($counts['foreign_key_violations'] ?? 0) > 0) {
            $blocking[] = 'foreign_key_check';
        }
        if (($counts['missing_catalog_rootpages'] ?? 0) > 0) {
            $blocking[] = 'foreign_key_rootpage_catalog';
        }
        if (($counts['pointer_map_conflicts'] ?? 0) > 0) {
            $blocking[] = 'rootpage_pointer_map';
        }
        if (($counts['foreign_key_rootpage_errors'] ?? 0) > 0) {
            $blocking[] = 'foreign_key_rootpage_integrity';
        }

        return array_values(array_unique($blocking));
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array<string,mixed>
     */
    private static function source(
        SQLiteAttachedSchemaCatalog $catalog,
        string $database,
        array $schemas,
        string $indexListSql,
        string $foreignKeySql,
        string $integritySql,
        bool $tableValuedIndexList,
    ): array {
        $source = [
            'database' => hash('sha256', $database),
            'catalog' => self::catalogHash($catalog),
            'schemas' => self::stableHash($schemas),
            'index_list_sql' => self::normalizeSql($indexListSql),
            'foreign_key_sql' => self::normalizeSql($foreignKeySql),
            'integrity_sql' => self::normalizeSql($integritySql),
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

    private static function catalogHash(SQLiteAttachedSchemaCatalog $catalog): string
    {
        $snapshot = [
            'database_list' => $catalog->databaseList(),
            'schema_generation' => $catalog->schemaGeneration(),
            'search_order' => $catalog->searchOrder(),
            'schemas' => [],
        ];
        foreach ($catalog->databaseList() as $database) {
            $schema = (string) $database['name'];
            $snapshot['schemas'][$schema] = array_map(
                static fn (SQLiteSchemaRecord $record): array => [
                    'type' => $record->type,
                    'name' => $record->name,
                    'table' => $record->tableName,
                    'rootpage' => $record->rootPage,
                    'sql' => $record->sql,
                    'rowid' => $record->rowId,
                ],
                $catalog->schemaRecords($schema),
            );
        }

        return self::stableHash($snapshot);
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
            throw new InvalidArgumentException('SQLite PRAGMA index_list/FK rootpage current-source next148 cursor does not match the current/next source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_list/FK rootpage current-source next148 cursor offset does not match the requested page offset');
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
