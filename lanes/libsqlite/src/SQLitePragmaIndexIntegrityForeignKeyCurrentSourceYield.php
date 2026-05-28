<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexIntegrityForeignKeyCurrentSourceYield
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return list<array<string,mixed>>
     */
    public static function collect(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql,
        string $integritySql = 'PRAGMA integrity_check',
        bool $indexTableValued = false,
        ?SQLiteAttachedSchemaCatalog $foreignKeyCatalog = null,
    ): array {
        $rows = [];
        foreach (SQLitePragmaIndexXinfoIntegrityRootYield::collect($catalog, $indexXinfoSql, $database, $integritySql, $indexTableValued) as $row) {
            if (($row['kind'] ?? null) !== 'index_xinfo') {
                continue;
            }
            $rows[] = [
                ...$row,
                'source' => 'index_xinfo',
                'table' => null,
                'rowid' => null,
                'parent' => null,
                'fkid' => null,
            ];
        }

        foreach (SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::collect($database, $schemas, $foreignKeySql, $foreignKeyCatalog) as $row) {
            $rows[] = [
                'kind' => $row['kind'],
                'source' => $row['source'],
                'schema' => $row['schema'],
                'target' => null,
                'seqno' => null,
                'cid' => null,
                'name' => $row['name'],
                'desc' => null,
                'coll' => null,
                'key' => null,
                'table' => $row['table'],
                'rowid' => $row['rowid'],
                'parent' => $row['parent'],
                'fkid' => $row['fkid'],
                'rootpage' => $row['rootpage'],
                'page_status' => $row['page_status'],
                'page_type' => $row['page_type'],
                'pointer_map_page' => $row['pointer_map_page'],
                'message' => $row['message'],
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array{database:string,catalog_hash:string,index_xinfo_sql:string,integrity_sql:string,foreign_key_sql:string,index_table_valued:bool,schema_hash:string,foreign_key_catalog_hash:string|null},offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{index_xinfo:int,integrity_root:int,foreign_key:int},next:array{source_id:string,offset:int}|null,rows:list<array<string,mixed>>}
     */
    public static function page(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql,
        int $offset = 0,
        int $limit = 121,
        string $integritySql = 'PRAGMA integrity_check',
        bool $indexTableValued = false,
        ?array $cursor = null,
        ?SQLiteAttachedSchemaCatalog $foreignKeyCatalog = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index integrity foreign-key source cursor offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA index integrity foreign-key source cursor limit must be positive');
        }

        $source = self::source($catalog, $indexXinfoSql, $database, $schemas, $foreignKeySql, $integritySql, $indexTableValued, $foreignKeyCatalog);
        if ($cursor !== null) {
            self::validateCursor($cursor, $source['source_id'], $offset);
        }

        $rows = self::collect($catalog, $indexXinfoSql, $database, $schemas, $foreignKeySql, $integritySql, $indexTableValued, $foreignKeyCatalog);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);

        return [
            'status' => 'ok',
            'source_id' => $source['source_id'],
            'current_source' => [
                'database' => $source['database'],
                'catalog_hash' => $source['catalog_hash'],
                'index_xinfo_sql' => $source['index_xinfo_sql'],
                'integrity_sql' => $source['integrity_sql'],
                'foreign_key_sql' => $source['foreign_key_sql'],
                'index_table_valued' => $source['index_table_valued'],
                'schema_hash' => $source['schema_hash'],
                'foreign_key_catalog_hash' => $source['foreign_key_catalog_hash'],
            ],
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => self::counts($rows),
            'next' => $complete ? null : [
                'source_id' => $source['source_id'],
                'offset' => $nextOffset,
            ],
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{index_xinfo:int,integrity_root:int,foreign_key:int}
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'index_xinfo' => 0,
            'integrity_root' => 0,
            'foreign_key' => 0,
        ];
        foreach ($rows as $row) {
            $kind = $row['kind'] ?? null;
            if ($kind === 'index_xinfo') {
                $counts['index_xinfo']++;
            } elseif ($kind === 'foreign_key_check') {
                $counts['foreign_key']++;
            } else {
                $counts['integrity_root']++;
            }
        }

        return $counts;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{source_id:string,database:string,catalog_hash:string,index_xinfo_sql:string,integrity_sql:string,foreign_key_sql:string,index_table_valued:bool,schema_hash:string,foreign_key_catalog_hash:string|null}
     */
    private static function source(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql,
        string $integritySql,
        bool $indexTableValued,
        ?SQLiteAttachedSchemaCatalog $foreignKeyCatalog,
    ): array {
        $source = [
            'database' => is_string($database) ? hash('sha256', $database) : self::databaseHash($database),
            'catalog_hash' => self::catalogHash($catalog),
            'index_xinfo_sql' => self::normalizeSql($indexXinfoSql),
            'integrity_sql' => self::normalizeSql($integritySql),
            'foreign_key_sql' => self::normalizeSql($foreignKeySql),
            'index_table_valued' => $indexTableValued,
            'schema_hash' => self::stableHash($schemas),
            'foreign_key_catalog_hash' => $foreignKeyCatalog === null ? null : self::catalogHash($foreignKeyCatalog),
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
            throw new InvalidArgumentException('SQLite PRAGMA index integrity foreign-key source cursor does not match the current database/schema/catalog source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index integrity foreign-key source cursor offset does not match the requested page offset');
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
