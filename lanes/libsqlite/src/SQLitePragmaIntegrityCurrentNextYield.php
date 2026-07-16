<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegrityCurrentNextYield
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return list<array{kind:string,source:string,schema:string|null,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,page:int|null,pointer_map_page:int|null,message:string}>
     */
    public static function collect(string|SQLiteDatabase $database, array $schemas = [], string $integritySql = 'PRAGMA integrity_check'): array
    {
        $integrity = SQLitePragmaIntegrityCheck::execute($integritySql, $database);
        $rows = [];

        foreach ($integrity['errors'] as $message) {
            $page = self::messagePageNumber($message);
            $rows[] = [
                'kind' => $integrity['pragma'],
                'source' => self::classify($message),
                'schema' => null,
                'table' => null,
                'rowid' => null,
                'parent' => null,
                'fkid' => null,
                'page' => $page,
                'pointer_map_page' => self::pointerMapPageNumber($database, $page),
                'message' => $message,
            ];
        }

        if ($schemas !== []) {
            $foreignKeys = SQLitePragmaForeignKeyIntegrity::executeAllSchemas($schemas);
            foreach ($foreignKeys['rows'] as $row) {
                $rows[] = [
                    'kind' => 'foreign_key_check',
                    'source' => 'foreign_key',
                    'schema' => $row['schema'],
                    'table' => $row['table'],
                    'rowid' => $row['rowid'],
                    'parent' => $row['parent'],
                    'fkid' => $row['fkid'],
                    'page' => null,
                    'pointer_map_page' => null,
                    'message' => self::foreignKeyMessage($row),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return list<array{kind:string,source:string,schema:string|null,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,page:int|null,pointer_map_page:int|null,message:string}>
     */
    public static function collectForForeignKeyPragma(
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql,
        string $integritySql = 'PRAGMA integrity_check',
        ?SQLiteAttachedSchemaCatalog $catalog = null,
    ): array {
        $rows = self::collect($database, [], $integritySql);
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
                'page' => null,
                'pointer_map_page' => null,
                'message' => self::foreignKeyMessage($row),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return list<array{kind:string,source:string,schema:string|null,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,page:int|null,pointer_map_page:int|null,message:string}>
     */
    public static function collectForForeignKeyTableValuedPragma(
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql,
        string $integritySql = 'PRAGMA integrity_check',
        ?SQLiteAttachedSchemaCatalog $catalog = null,
    ): array {
        $rows = self::collect($database, [], $integritySql);
        $foreignKeys = SQLitePragmaForeignKeyIntegrity::executeTableValued($foreignKeySql, $schemas, $catalog);
        foreach ($foreignKeys['rows'] as $row) {
            $rows[] = [
                'kind' => 'foreign_key_check',
                'source' => 'foreign_key',
                'schema' => $row['schema'],
                'table' => $row['table'],
                'rowid' => $row['rowid'],
                'parent' => $row['parent'],
                'fkid' => $row['fkid'],
                'page' => null,
                'pointer_map_page' => null,
                'message' => self::foreignKeyMessage($row),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,int>|null,rows:list<array{kind:string,source:string,schema:string|null,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,page:int|null,pointer_map_page:int|null,message:string}>}
     */
    public static function page(string|SQLiteDatabase $database, array $schemas = [], int $offset = 0, int $limit = 64, string $integritySql = 'PRAGMA integrity_check'): array
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity current/next yield offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity current/next yield limit must be positive');
        }

        $rows = self::collect($database, $schemas, $integritySql);
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
            'current' => self::sourceCounts($rows),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,int>|null,rows:list<array{kind:string,source:string,schema:string|null,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,page:int|null,pointer_map_page:int|null,message:string}>}
     */
    public static function pageForForeignKeyPragma(
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql,
        int $offset = 0,
        int $limit = 64,
        string $integritySql = 'PRAGMA integrity_check',
        ?SQLiteAttachedSchemaCatalog $catalog = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity current/next yield offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity current/next yield limit must be positive');
        }

        $rows = self::collectForForeignKeyPragma($database, $schemas, $foreignKeySql, $integritySql, $catalog);
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
            'current' => self::sourceCounts($rows),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,int>|null,rows:list<array{kind:string,source:string,schema:string|null,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,page:int|null,pointer_map_page:int|null,message:string}>}
     */
    public static function pageForForeignKeyTableValuedPragma(
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql,
        int $offset = 0,
        int $limit = 64,
        string $integritySql = 'PRAGMA integrity_check',
        ?SQLiteAttachedSchemaCatalog $catalog = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity current/next yield offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity current/next yield limit must be positive');
        }

        $rows = self::collectForForeignKeyTableValuedPragma($database, $schemas, $foreignKeySql, $integritySql, $catalog);
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
            'current' => self::sourceCounts($rows),
            'rows' => $pageRows,
        ];
    }

    private static function classify(string $message): string
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'pointer-map')) {
            return 'pointer_map';
        }
        if (str_contains($lower, 'freelist')) {
            return 'freelist';
        }
        if (str_contains($lower, 'sqlite_schema') || str_contains($lower, 'largest root btree page')) {
            return 'schema_root';
        }
        if (str_contains($lower, 'header') || str_contains($lower, 'schema write version') || str_contains($lower, 'schema read version') || str_contains($lower, 'text encoding')) {
            return 'header';
        }
        if (str_contains($lower, 'btree') || str_contains($lower, 'cell') || str_contains($lower, 'freeblock')) {
            return 'btree';
        }

        return 'integrity';
    }

    private static function messagePageNumber(string $message): ?int
    {
        foreach ([
            '/for\s+page\s+(\d+)/i',
            '/for\s+[a-z-]+\s+page\s+(\d+)/i',
            '/page\s+(\d+)\s+pointer-map/i',
            '/marks\s+page\s+(\d+)/i',
            '/at\s+page\s+(\d+)/i',
            '/page\s+(\d+)/i',
        ] as $pattern) {
            if (preg_match($pattern, $message, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    private static function pointerMapPageNumber(string|SQLiteDatabase $database, ?int $pageNumber): ?int
    {
        if ($pageNumber === null) {
            return null;
        }
        if (is_string($database)) {
            try {
                $database = SQLiteDatabase::fromBytes($database);
            } catch (InvalidArgumentException) {
                return null;
            }
        }
        if (!$database->isAutoVacuum() || $pageNumber < 2 || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapPageFor($pageNumber);
    }

    /**
     * @param list<array{source:string}> $rows
     * @return array<string,int>
     */
    private static function sourceCounts(array $rows): array
    {
        $counts = [
            'integrity' => 0,
            'header' => 0,
            'freelist' => 0,
            'schema_root' => 0,
            'pointer_map' => 0,
            'btree' => 0,
            'foreign_key' => 0,
        ];
        foreach ($rows as $row) {
            $counts[$row['source']] = ($counts[$row['source']] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param array{schema:string,table:string,rowid:int|string|null,parent:string,fkid:int} $row
     */
    private static function foreignKeyMessage(array $row): string
    {
        $rowid = $row['rowid'] === null ? 'NULL' : (string) $row['rowid'];

        return "foreign key mismatch in {$row['schema']}.{$row['table']} rowid {$rowid} references {$row['parent']} fkid {$row['fkid']}";
    }
}
