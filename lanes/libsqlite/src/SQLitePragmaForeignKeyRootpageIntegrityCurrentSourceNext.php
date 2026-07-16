<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaForeignKeyRootpageIntegrityCurrentSourceNext
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $currentSchemas
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $nextSchemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array<string,mixed>,next_source:array<string,mixed>,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,mixed>,next_counts:array<string,mixed>,delta:array<string,mixed>,next_state:array{ready:bool,blocking:list<string>},next:array{source_id:string,offset:int}|null,rows:list<array<string,mixed>>}
     */
    public static function currentNextPage(
        string|SQLiteDatabase $currentDatabase,
        array $currentSchemas,
        SQLiteAttachedSchemaCatalog $currentCatalog,
        string|SQLiteDatabase $nextDatabase,
        array $nextSchemas,
        SQLiteAttachedSchemaCatalog $nextCatalog,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        string $integritySql = 'PRAGMA integrity_check',
        int $offset = 0,
        int $limit = 147,
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key rootpage integrity current-source next147 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key rootpage integrity current-source next147 limit must be positive');
        }

        $currentSource = self::source($currentDatabase, $currentSchemas, $currentCatalog, $foreignKeySql, $integritySql);
        $nextSource = self::source($nextDatabase, $nextSchemas, $nextCatalog, $foreignKeySql, $integritySql);
        $sourceId = self::stableHash([
            'current' => $currentSource['source_id'],
            'next' => $nextSource['source_id'],
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $currentRows = array_map(
            static fn (array $row): array => ['side' => 'current', ...$row],
            self::collect($currentDatabase, $currentSchemas, $currentCatalog, $foreignKeySql, $integritySql),
        );
        $nextRows = array_map(
            static fn (array $row): array => ['side' => 'next', ...$row],
            self::collect($nextDatabase, $nextSchemas, $nextCatalog, $foreignKeySql, $integritySql),
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
            'delta' => self::delta($currentCounts, $nextCounts),
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
        string|SQLiteDatabase $database,
        array $schemas,
        SQLiteAttachedSchemaCatalog $catalog,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        string $integritySql = 'PRAGMA integrity_check',
    ): array {
        $integrityRows = array_values(array_filter(
            SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::collect($database, $schemas, $foreignKeySql, $catalog, $integritySql),
            static fn (array $row): bool => ($row['kind'] ?? null) === 'integrity_root',
        ));
        $foreignKeyRows = SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::collect($database, $schemas, $catalog, $foreignKeySql);

        return [...$integrityRows, ...$foreignKeyRows];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{integrity_root:int,foreign_key_rootpage:int,child_rootpage_errors:int,parent_rootpage_errors:int,missing_catalog_rootpages:int,pointer_map_conflicts:int,total_blockers:int,schemas:list<string>,tables:list<string>}
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'integrity_root' => 0,
            'foreign_key_rootpage' => 0,
            'child_rootpage_errors' => 0,
            'parent_rootpage_errors' => 0,
            'missing_catalog_rootpages' => 0,
            'pointer_map_conflicts' => 0,
            'total_blockers' => 0,
            'schemas' => [],
            'tables' => [],
        ];

        foreach ($rows as $row) {
            if (is_string($row['schema'] ?? null)) {
                $counts['schemas'][] = $row['schema'];
            }
            if (is_string($row['table'] ?? null)) {
                $counts['tables'][] = $row['table'];
            }

            if (($row['kind'] ?? null) === 'integrity_root') {
                $counts['integrity_root']++;
                $counts['total_blockers']++;
                continue;
            }

            if (($row['kind'] ?? null) !== 'foreign_key_rootpage_pointer_map') {
                continue;
            }

            $counts['foreign_key_rootpage']++;
            $counts['total_blockers']++;
            foreach (['child', 'parent'] as $side) {
                $status = $row[$side . '_rootpage_status'] ?? null;
                if ($status === 'missing_catalog_rootpage' || $status === 'missing_schema_rootpage') {
                    $counts['missing_catalog_rootpages']++;
                } elseif ($status !== 'ok') {
                    $counts[$side . '_rootpage_errors']++;
                }
                if ($status === 'pointer_map') {
                    $counts['pointer_map_conflicts']++;
                }
            }
        }

        $counts['schemas'] = array_values(array_unique($counts['schemas']));
        $counts['tables'] = array_values(array_unique($counts['tables']));

        return $counts;
    }

    /**
     * @param array<string,mixed> $counts
     * @return list<string>
     */
    private static function blocking(array $counts): array
    {
        $blocking = [];
        if (($counts['integrity_root'] ?? 0) > 0) {
            $blocking[] = 'integrity_rootpage';
        }
        if (($counts['foreign_key_rootpage'] ?? 0) > 0) {
            $blocking[] = 'foreign_key_check';
        }
        if (($counts['missing_catalog_rootpages'] ?? 0) > 0) {
            $blocking[] = 'foreign_key_rootpage_catalog';
        }
        if (($counts['pointer_map_conflicts'] ?? 0) > 0) {
            $blocking[] = 'rootpage_pointer_map';
        }
        if (($counts['child_rootpage_errors'] ?? 0) > 0 || ($counts['parent_rootpage_errors'] ?? 0) > 0) {
            $blocking[] = 'foreign_key_rootpage_integrity';
        }

        return $blocking;
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return array<string,mixed>
     */
    private static function delta(array $current, array $next): array
    {
        $keys = ['integrity_root', 'foreign_key_rootpage', 'child_rootpage_errors', 'parent_rootpage_errors', 'missing_catalog_rootpages', 'pointer_map_conflicts', 'total_blockers'];
        $delta = [];
        foreach ($keys as $key) {
            $delta[$key] = (int) $next[$key] - (int) $current[$key];
        }
        $delta['cleared'] = (int) $current['total_blockers'] > 0 && (int) $next['total_blockers'] === 0;

        return $delta;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array<string,mixed>
     */
    private static function source(string|SQLiteDatabase $database, array $schemas, SQLiteAttachedSchemaCatalog $catalog, string $foreignKeySql, string $integritySql): array
    {
        $source = [
            'database' => is_string($database) ? hash('sha256', $database) : self::databaseHash($database),
            'schemas' => self::stableHash($schemas),
            'catalog' => self::stableHash(self::catalogSource($catalog)),
            'foreign_key_sql' => self::normalizeSql($foreignKeySql),
            'integrity_sql' => self::normalizeSql($integritySql),
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

    /**
     * @return array{generation:int,search_order:list<string>,database_list:list<array{seq:int,name:string,file:string|null}>,schema_records:array<string,list<array{type:string,name:string,table:string,rootpage:int|null,sql:string|null,rowid:int}>>}
     */
    private static function catalogSource(SQLiteAttachedSchemaCatalog $catalog): array
    {
        $records = [];
        foreach ($catalog->databaseList() as $database) {
            $schema = $database['name'];
            $records[$schema] = array_map(
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

        return [
            'generation' => $catalog->schemaGeneration(),
            'search_order' => $catalog->searchOrder(),
            'database_list' => $catalog->databaseList(),
            'schema_records' => $records,
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
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key rootpage integrity current-source next147 cursor does not match the current/next source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key rootpage integrity current-source next147 cursor offset does not match the requested page offset');
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
