<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return list<array<string,mixed>>
     */
    public static function collect(
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql,
        ?SQLiteAttachedSchemaCatalog $catalog = null,
    ): array {
        $rootpage = SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext111::analyze($database);
        $rows = [];
        foreach ($rootpage['rows'] as $row) {
            if (($row['status'] ?? null) === 'ok' || ($row['status'] ?? null) === 'ignored') {
                continue;
            }
            $rows[] = [
                'kind' => 'integrity_root',
                'source' => $row['kind'],
                'schema' => null,
                'table' => $row['table'],
                'rowid' => $row['rowid'],
                'parent' => null,
                'fkid' => null,
                'type' => $row['type'],
                'name' => $row['name'],
                'rootpage' => $row['rootpage'],
                'page_status' => $row['page_status'],
                'page_type' => $row['page_type'],
                'pointer_map_page' => $row['pointer_map_page'],
                'message' => $row['message'],
            ];
        }

        $foreignKeys = SQLitePragmaForeignKeyIntegrity::execute($foreignKeySql, $schemas, $catalog);
        foreach ($foreignKeys['rows'] as $row) {
            $rows[] = [
                'kind' => 'foreign_key_check',
                'source' => 'foreign_key',
                'schema' => $row['schema'],
                'table' => $row['table'],
                'rowid' => $row['rowid'],
                'parent' => $row['parent'],
                'fkid' => $row['fkid'],
                'type' => null,
                'name' => null,
                'rootpage' => null,
                'page_status' => null,
                'page_type' => null,
                'pointer_map_page' => null,
                'message' => self::foreignKeyMessage($row),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array{database:string,foreign_key_sql:string,schema_hash:string,catalog_hash:string|null},offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{integrity_root:int,foreign_key:int},next:array{source_id:string,offset:int}|null,rows:list<array<string,mixed>>}
     */
    public static function page(
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql,
        int $offset = 0,
        int $limit = 117,
        ?array $cursor = null,
        ?SQLiteAttachedSchemaCatalog $catalog = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key root integrity source cursor offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key root integrity source cursor limit must be positive');
        }

        $source = self::source($database, $schemas, $foreignKeySql, $catalog);
        if ($cursor !== null) {
            self::validateCursor($cursor, $source['source_id'], $offset);
        }

        $rows = self::collect($database, $schemas, $foreignKeySql, $catalog);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);

        return [
            'status' => 'ok',
            'source_id' => $source['source_id'],
            'current_source' => [
                'database' => $source['database'],
                'foreign_key_sql' => $source['foreign_key_sql'],
                'schema_hash' => $source['schema_hash'],
                'catalog_hash' => $source['catalog_hash'],
            ],
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => self::sourceCounts($rows),
            'next' => $complete ? null : [
                'source_id' => $source['source_id'],
                'offset' => $nextOffset,
            ],
            'rows' => $pageRows,
        ];
    }

    /**
     * @param array{schema:string,table:string,rowid:int|string|null,parent:string,fkid:int} $row
     */
    private static function foreignKeyMessage(array $row): string
    {
        $rowid = $row['rowid'] === null ? 'NULL' : (string) $row['rowid'];

        return "foreign key mismatch in {$row['schema']}.{$row['table']} rowid {$rowid} references {$row['parent']} fkid {$row['fkid']}";
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{integrity_root:int,foreign_key:int}
     */
    private static function sourceCounts(array $rows): array
    {
        $counts = [
            'integrity_root' => 0,
            'foreign_key' => 0,
        ];
        foreach ($rows as $row) {
            if (($row['kind'] ?? null) === 'foreign_key_check') {
                $counts['foreign_key']++;
            } else {
                $counts['integrity_root']++;
            }
        }

        return $counts;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{source_id:string,database:string,foreign_key_sql:string,schema_hash:string,catalog_hash:string|null}
     */
    private static function source(string|SQLiteDatabase $database, array $schemas, string $foreignKeySql, ?SQLiteAttachedSchemaCatalog $catalog): array
    {
        $normalized = [
            'database' => is_string($database) ? hash('sha256', $database) : self::databaseHash($database),
            'foreign_key_sql' => self::normalizeSql($foreignKeySql),
            'schema_hash' => self::stableHash($schemas),
            'catalog_hash' => $catalog === null ? null : self::stableHash(self::catalogSource($catalog)),
        ];

        return [
            ...$normalized,
            'source_id' => self::stableHash($normalized),
        ];
    }

    /**
     * @return array{generation:int,search_order:list<string>,database_list:list<array{seq:int,name:string,file:string|null}>,schema_records:array<string,list<array{type:string,name:string,table:string,rootpage:int|null,sql:string|null,rowid:int}>>}
     */
    private static function catalogSource(SQLiteAttachedSchemaCatalog $catalog): array
    {
        $schemaRecords = [];
        foreach ($catalog->databaseList() as $database) {
            $schema = $database['name'];
            $schemaRecords[$schema] = array_map(
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
            'schema_records' => $schemaRecords,
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
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key root integrity source cursor does not match the current database/schema source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key root integrity source cursor offset does not match the requested page offset');
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
