<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegrityForeignKeyYield
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return list<array{kind:string,schema:string|null,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,message:string}>
     */
    public static function collect(string|SQLiteDatabase $database, array $schemas, string $integritySql = 'PRAGMA integrity_check'): array
    {
        $diagnostics = [];
        $integrity = SQLitePragmaIntegrityCheck::execute($integritySql, $database);
        foreach ($integrity['errors'] as $message) {
            $diagnostics[] = [
                'kind' => $integrity['pragma'],
                'schema' => null,
                'table' => null,
                'rowid' => null,
                'parent' => null,
                'fkid' => null,
                'message' => $message,
            ];
        }

        $foreignKeys = SQLitePragmaForeignKeyIntegrity::executeAllSchemas($schemas);
        foreach ($foreignKeys['rows'] as $row) {
            $diagnostics[] = [
                'kind' => 'foreign_key_check',
                'schema' => $row['schema'],
                'table' => $row['table'],
                'rowid' => $row['rowid'],
                'parent' => $row['parent'],
                'fkid' => $row['fkid'],
                'message' => self::foreignKeyMessage($row),
            ];
        }

        return $diagnostics;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,rows:list<array{kind:string,schema:string|null,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,message:string}>}
     */
    public static function page(string|SQLiteDatabase $database, array $schemas, int $offset = 0, int $limit = 32, string $integritySql = 'PRAGMA integrity_check'): array
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity/foreign-key yield offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity/foreign-key yield limit must be positive');
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
}
