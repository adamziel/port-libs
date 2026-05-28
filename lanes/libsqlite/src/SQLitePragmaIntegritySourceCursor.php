<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegritySourceCursor
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @param array{source_id?:string,next_offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array{database:string,integrity_sql:string,foreign_key_sql:string,schema_hash:string},offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,int>|null,next:array{source_id:string,offset:int}|null,rows:list<array{kind:string,source:string,schema:string|null,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,page:int|null,pointer_map_page:int|null,message:string}>}
     */
    public static function pageForForeignKeyPragma(
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql,
        int $offset = 0,
        int $limit = 64,
        string $integritySql = 'PRAGMA integrity_check',
        ?array $cursor = null,
        ?SQLiteAttachedSchemaCatalog $catalog = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity source cursor offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity source cursor limit must be positive');
        }

        $source = self::source($database, $schemas, $foreignKeySql, $integritySql);
        if ($cursor !== null) {
            self::validateCursor($cursor, $source['source_id'], $offset);
        }

        $page = SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma(
            $database,
            $schemas,
            $foreignKeySql,
            $offset,
            $limit,
            $integritySql,
            $catalog,
        );

        return [
            ...$page,
            'source_id' => $source['source_id'],
            'current_source' => [
                'database' => $source['database'],
                'integrity_sql' => $source['integrity_sql'],
                'foreign_key_sql' => $source['foreign_key_sql'],
                'schema_hash' => $source['schema_hash'],
            ],
            'next' => $page['next_offset'] === null ? null : [
                'source_id' => $source['source_id'],
                'offset' => $page['next_offset'],
            ],
        ];
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @param array{source_id?:string,next_offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array{database:string,integrity_sql:string,foreign_key_sql:string,schema_hash:string},offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,int>|null,next:array{source_id:string,offset:int}|null,rows:list<array{kind:string,source:string,schema:string|null,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,page:int|null,pointer_map_page:int|null,message:string}>}
     */
    public static function pageForForeignKeyTableValuedPragma(
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql,
        int $offset = 0,
        int $limit = 64,
        string $integritySql = 'PRAGMA integrity_check',
        ?array $cursor = null,
        ?SQLiteAttachedSchemaCatalog $catalog = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity source cursor offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity source cursor limit must be positive');
        }

        $source = self::source($database, $schemas, $foreignKeySql, $integritySql);
        if ($cursor !== null) {
            self::validateCursor($cursor, $source['source_id'], $offset);
        }

        $page = SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyTableValuedPragma(
            $database,
            $schemas,
            $foreignKeySql,
            $offset,
            $limit,
            $integritySql,
            $catalog,
        );

        return [
            ...$page,
            'source_id' => $source['source_id'],
            'current_source' => [
                'database' => $source['database'],
                'integrity_sql' => $source['integrity_sql'],
                'foreign_key_sql' => $source['foreign_key_sql'],
                'schema_hash' => $source['schema_hash'],
            ],
            'next' => $page['next_offset'] === null ? null : [
                'source_id' => $source['source_id'],
                'offset' => $page['next_offset'],
            ],
        ];
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{source_id:string,database:string,integrity_sql:string,foreign_key_sql:string,schema_hash:string}
     */
    private static function source(string|SQLiteDatabase $database, array $schemas, string $foreignKeySql, string $integritySql): array
    {
        $databaseHash = is_string($database) ? hash('sha256', $database) : self::databaseHash($database);
        $normalized = [
            'database' => $databaseHash,
            'integrity_sql' => self::normalizeSql($integritySql),
            'foreign_key_sql' => self::normalizeSql($foreignKeySql),
            'schema_hash' => self::stableHash($schemas),
        ];

        return [
            ...$normalized,
            'source_id' => self::stableHash($normalized),
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
            throw new InvalidArgumentException('SQLite PRAGMA integrity source cursor does not match the current database/schema source');
        }
        if (array_key_exists('next_offset', $cursor) && $cursor['next_offset'] !== null && $cursor['next_offset'] !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity source cursor offset does not match the requested page offset');
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
