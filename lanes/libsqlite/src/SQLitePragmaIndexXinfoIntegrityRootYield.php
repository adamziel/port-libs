<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoIntegrityRootYield
{
    /**
     * @return list<array<string, int|string|null>>
     */
    public static function collect(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        string $integritySql = 'PRAGMA integrity_check',
        bool $tableValued = false,
    ): array {
        $cursor = $tableValued
            ? $catalog->executeTableValuedPragmaCursor($indexXinfoSql)
            : ($catalog instanceof SQLiteAttachedSchemaCatalog
                ? $catalog->executeSchemaPragmaCursor($indexXinfoSql)
                : $catalog->executeCursor($indexXinfoSql));
        $metadata = $cursor->metadata();
        if (($metadata['pragma'] ?? null) !== 'index_xinfo') {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo integrity yield requires index_xinfo rows');
        }

        $rows = [];
        foreach ($cursor->rows() as $row) {
            $rows[] = [
                'kind' => 'index_xinfo',
                'schema' => $metadata['schema'],
                'target' => $metadata['target'],
                'seqno' => $row['seqno'] ?? null,
                'cid' => $row['cid'] ?? null,
                'name' => $row['name'] ?? null,
                'desc' => $row['desc'] ?? null,
                'coll' => $row['coll'] ?? null,
                'key' => $row['key'] ?? null,
                'message' => self::indexMessage($metadata['schema'], $metadata['target'], $row),
            ];
        }

        $integrity = SQLitePragmaIntegrityCheck::execute($integritySql, $database);
        $seenIntegrity = [];
        foreach ($integrity['errors'] as $message) {
            if (!self::isRootIntegrityMessage($message)) {
                continue;
            }
            if (isset($seenIntegrity[$message])) {
                continue;
            }
            $seenIntegrity[$message] = true;

            $rows[] = [
                'kind' => $integrity['pragma'],
                'schema' => null,
                'target' => null,
                'seqno' => null,
                'cid' => null,
                'name' => null,
                'desc' => null,
                'coll' => null,
                'key' => null,
                'message' => $message,
            ];
        }

        return $rows;
    }

    /**
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,int|string|null>|null,next:array<string,int|string|null>|null,rows:list<array<string,int|string|null>>}
     */
    public static function page(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        int $offset = 0,
        int $limit = 54,
        string $integritySql = 'PRAGMA integrity_check',
        bool $tableValued = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo integrity root yield offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo integrity root yield limit must be positive');
        }

        $rows = self::collect($catalog, $indexXinfoSql, $database, $integritySql, $tableValued);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);

        return [
            'status' => 'ok',
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => $pageRows[0] ?? null,
            'next' => $pageRows[1] ?? null,
            'rows' => $pageRows,
        ];
    }

    /**
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array{database:string,catalog:string,index_xinfo_sql:string,integrity_sql:string,table_valued:bool},offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,int|string|null>|null,next:array{source_id:string,offset:int}|null,next_row:array<string,int|string|null>|null,rows:list<array<string,int|string|null>>}
     */
    public static function pageWithSourceCursor(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        int $offset = 0,
        int $limit = 103,
        string $integritySql = 'PRAGMA integrity_check',
        bool $tableValued = false,
        ?array $cursor = null,
    ): array {
        $source = self::source($catalog, $indexXinfoSql, $database, $integritySql, $tableValued);
        if ($cursor !== null) {
            self::validateCursor($cursor, $source['source_id'], $offset);
        }

        $page = self::page($catalog, $indexXinfoSql, $database, $offset, $limit, $integritySql, $tableValued);

        return [
            'status' => $page['status'],
            'source_id' => $source['source_id'],
            'current_source' => [
                'database' => $source['database'],
                'catalog' => $source['catalog'],
                'index_xinfo_sql' => $source['index_xinfo_sql'],
                'integrity_sql' => $source['integrity_sql'],
                'table_valued' => $source['table_valued'],
            ],
            'offset' => $page['offset'],
            'limit' => $page['limit'],
            'count' => $page['count'],
            'total' => $page['total'],
            'next_offset' => $page['next_offset'],
            'complete' => $page['complete'],
            'current' => $page['current'],
            'next' => $page['next_offset'] === null ? null : [
                'source_id' => $source['source_id'],
                'offset' => $page['next_offset'],
            ],
            'next_row' => $page['next'],
            'rows' => $page['rows'],
        ];
    }

    /**
     * @param array<string, int|string|null> $metadata
     * @param array<string, int|string|null> $row
     */
    private static function indexMessage(string $schema, string $target, array $row): string
    {
        $name = $row['name'] ?? null;
        $label = $name === null || $name === '' ? 'expression/rowid' : (string) $name;

        return sprintf(
            'index_xinfo %s.%s seqno %d cid %d %s coll %s key %d',
            $schema,
            $target,
            (int) ($row['seqno'] ?? 0),
            (int) ($row['cid'] ?? 0),
            $label,
            (string) ($row['coll'] ?? 'BINARY'),
            (int) ($row['key'] ?? 0),
        );
    }

    private static function isRootIntegrityMessage(string $message): bool
    {
        $lower = strtolower($message);

        return str_contains($lower, 'sqlite_schema')
            || str_contains($lower, 'largest root btree page')
            || (str_contains($lower, 'pointer-map type') && str_contains($lower, 'root-page'));
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
        $snapshot = [];
        if ($catalog instanceof SQLiteAttachedSchemaCatalog) {
            $snapshot['database_list'] = $catalog->databaseList();
            $snapshot['schema_generation'] = $catalog->schemaGeneration();
            $snapshot['search_order'] = $catalog->searchOrder();
            $snapshot['schemas'] = [];
            foreach ($catalog->databaseList() as $database) {
                $schema = (string) $database['name'];
                $snapshot['schemas'][$schema] = self::schemaRecordsSnapshot($catalog->schemaRecords($schema));
            }

            return self::stableHash($snapshot);
        }

        $snapshot['schema'] = 'main';
        $snapshot['records'] = self::schemaRecordsSnapshot($catalog->records());

        return self::stableHash($snapshot);
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
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo integrity source cursor does not match the current database/catalog source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo integrity source cursor offset does not match the requested page offset');
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
