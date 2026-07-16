<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaForeignKeyPointerMapIntegrityYield
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return list<array{kind:string,source:string,schema:string|null,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,page:int|null,pointer_map_page:int|null,message:string}>
     */
    public static function collect(string|SQLiteDatabase $database, array $schemas, string $integritySql = 'PRAGMA integrity_check'): array
    {
        $rows = [];

        foreach (SQLitePragmaIntegrityPointerMapFreelistYield::collect($database, $integritySql) as $row) {
            $rows[] = [
                'kind' => $row['kind'],
                'source' => $row['source'],
                'schema' => null,
                'table' => null,
                'rowid' => null,
                'parent' => null,
                'fkid' => null,
                'page' => $row['page'],
                'pointer_map_page' => $row['pointer_map_page'],
                'message' => $row['message'],
            ];
        }

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

        return $rows;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{integrity_pointer_map:int,foreign_key_violations:int},rows:list<array{kind:string,source:string,schema:string|null,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,page:int|null,pointer_map_page:int|null,message:string}>}
     */
    public static function page(string|SQLiteDatabase $database, array $schemas, int $offset = 0, int $limit = 52, string $integritySql = 'PRAGMA integrity_check'): array
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key/pointer-map integrity yield offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key/pointer-map integrity yield limit must be positive');
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
            'current' => [
                'integrity_pointer_map' => count(array_filter($rows, static fn (array $row): bool => $row['kind'] !== 'foreign_key_check')),
                'foreign_key_violations' => count(array_filter($rows, static fn (array $row): bool => $row['kind'] === 'foreign_key_check')),
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
}
