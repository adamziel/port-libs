<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaForeignKeyIndexRootCurrentSourceNext
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array{database:string,catalog:string,schemas:string,index_xinfo_sql:string,integrity_sql:string,foreign_key_sql:string,index_table_valued:bool},offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{index_xinfo:int,index_root_integrity:int,foreign_key_rootpage:int,pointer_map_conflicts:int,missing_catalog_rootpages:int,schemas:list<string>},next:array{source_id:string,offset:int}|null,next_state:array{ready:bool,blocking:list<string>},rows:list<array<string,mixed>>}
     */
    public static function page(
        SQLiteAttachedSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        int $offset = 0,
        int $limit = 125,
        string $integritySql = 'PRAGMA integrity_check',
        bool $indexTableValued = false,
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key index root current-source next125 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key index root current-source next125 limit must be positive');
        }

        $source = self::source($catalog, $indexXinfoSql, $database, $schemas, $foreignKeySql, $integritySql, $indexTableValued);
        if ($cursor !== null) {
            self::validateCursor($cursor, $source['source_id'], $offset);
        }

        $rows = self::collect($catalog, $indexXinfoSql, $database, $schemas, $foreignKeySql, $integritySql, $indexTableValued);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $current = self::counts($rows);
        $blocking = [];
        if ($current['index_root_integrity'] > 0) {
            $blocking[] = 'index_root_integrity';
        }
        if ($current['foreign_key_rootpage'] > 0) {
            $blocking[] = 'foreign_key_check';
        }
        if ($current['missing_catalog_rootpages'] > 0) {
            $blocking[] = 'foreign_key_rootpage_catalog';
        }
        if ($current['pointer_map_conflicts'] > 0) {
            $blocking[] = 'rootpage_pointer_map';
        }

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'source_id' => $source['source_id'],
            'current_source' => [
                'database' => $source['database'],
                'catalog' => $source['catalog'],
                'schemas' => $source['schemas'],
                'index_xinfo_sql' => $source['index_xinfo_sql'],
                'integrity_sql' => $source['integrity_sql'],
                'foreign_key_sql' => $source['foreign_key_sql'],
                'index_table_valued' => $source['index_table_valued'],
            ],
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => $current,
            'next' => $complete ? null : [
                'source_id' => $source['source_id'],
                'offset' => $nextOffset,
            ],
            'next_state' => [
                'ready' => $blocking === [],
                'blocking' => $blocking,
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
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        string $integritySql = 'PRAGMA integrity_check',
        bool $indexTableValued = false,
    ): array {
        $rows = [];
        foreach (SQLitePragmaIndexXinfoIntegrityRootYield::collect($catalog, $indexXinfoSql, $database, $integritySql, $indexTableValued) as $row) {
            $kind = ($row['kind'] ?? null) === 'index_xinfo' ? 'index_xinfo' : 'index_root_integrity';
            $rows[] = [
                ...$row,
                'kind' => $kind,
                'source' => $kind,
                'table' => $row['table'] ?? null,
                'rowid' => null,
                'parent' => null,
                'fkid' => null,
            ];
        }

        foreach (SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::collect($database, $schemas, $catalog, $foreignKeySql) as $row) {
            $rows[] = [
                ...$row,
                'kind' => 'foreign_key_rootpage',
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{index_xinfo:int,index_root_integrity:int,foreign_key_rootpage:int,pointer_map_conflicts:int,missing_catalog_rootpages:int,schemas:list<string>}
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'index_xinfo' => 0,
            'index_root_integrity' => 0,
            'foreign_key_rootpage' => 0,
            'pointer_map_conflicts' => 0,
            'missing_catalog_rootpages' => 0,
            'schemas' => [],
        ];

        foreach ($rows as $row) {
            $kind = $row['kind'] ?? null;
            if ($kind === 'index_xinfo') {
                $counts['index_xinfo']++;
            } elseif ($kind === 'index_root_integrity') {
                $counts['index_root_integrity']++;
            } elseif ($kind === 'foreign_key_rootpage') {
                $counts['foreign_key_rootpage']++;
                if (($row['child_rootpage_status'] ?? null) === 'pointer_map' || ($row['parent_rootpage_status'] ?? null) === 'pointer_map') {
                    $counts['pointer_map_conflicts']++;
                }
                if (($row['child_rootpage_status'] ?? null) === 'missing_catalog_rootpage' || ($row['parent_rootpage_status'] ?? null) === 'missing_catalog_rootpage') {
                    $counts['missing_catalog_rootpages']++;
                }
            }
            if (is_string($row['schema'] ?? null)) {
                $counts['schemas'][] = $row['schema'];
            }
        }

        $counts['schemas'] = array_values(array_unique($counts['schemas']));

        return $counts;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{source_id:string,database:string,catalog:string,schemas:string,index_xinfo_sql:string,integrity_sql:string,foreign_key_sql:string,index_table_valued:bool}
     */
    private static function source(
        SQLiteAttachedSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql,
        string $integritySql,
        bool $indexTableValued,
    ): array {
        $source = [
            'database' => is_string($database) ? hash('sha256', $database) : self::databaseHash($database),
            'catalog' => self::catalogHash($catalog),
            'schemas' => self::stableHash($schemas),
            'index_xinfo_sql' => self::normalizeSql($indexXinfoSql),
            'integrity_sql' => self::normalizeSql($integritySql),
            'foreign_key_sql' => self::normalizeSql($foreignKeySql),
            'index_table_valued' => $indexTableValued,
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
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key index root current-source next125 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key index root current-source next125 cursor offset does not match the requested page offset');
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
