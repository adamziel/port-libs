<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegrityIndexForeignKeyCurrentNextYield
{
    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<array<string,mixed>> $foreignKeys
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<array{kind:string,source:string,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,index:string|null,columns:list<string>,collations:list<string>,status:string,page:int|null,pointer_map_page:int|null,message:string}>
     */
    public static function collect(
        string|SQLiteDatabase $database,
        array $records,
        array $foreignKeys,
        array $tables,
        string $integritySql = 'PRAGMA integrity_check',
    ): array {
        $rows = [];

        foreach (SQLitePragmaForeignKeyIndexIntegrityYield::collect($records, $foreignKeys, $tables) as $row) {
            $rows[] = [
                'kind' => $row['kind'],
                'source' => $row['kind'] === 'foreign_key_check' ? 'foreign_key' : 'index',
                'table' => $row['table'],
                'rowid' => $row['rowid'],
                'parent' => $row['parent'],
                'fkid' => $row['fkid'],
                'index' => $row['index'],
                'columns' => $row['columns'],
                'collations' => $row['collations'],
                'status' => $row['status'],
                'page' => null,
                'pointer_map_page' => null,
                'message' => $row['message'],
            ];
        }

        foreach (SQLitePragmaIntegrityCurrentNextYield::collect($database, [], $integritySql) as $row) {
            $rows[] = [
                'kind' => $row['kind'],
                'source' => $row['source'],
                'table' => $row['table'],
                'rowid' => $row['rowid'],
                'parent' => $row['parent'],
                'fkid' => $row['fkid'],
                'index' => null,
                'columns' => [],
                'collations' => [],
                'status' => 'error',
                'page' => $row['page'],
                'pointer_map_page' => $row['pointer_map_page'],
                'message' => $row['message'],
            ];
        }

        return $rows;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<array<string,mixed>> $foreignKeys
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{index_admissions:int,index_blockers:int,foreign_key_violations:int,integrity_errors:int},next:array{ready:bool,blocking:list<string>},rows:list<array{kind:string,source:string,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,index:string|null,columns:list<string>,collations:list<string>,status:string,page:int|null,pointer_map_page:int|null,message:string}>}
     */
    public static function page(
        string|SQLiteDatabase $database,
        array $records,
        array $foreignKeys,
        array $tables,
        int $offset = 0,
        int $limit = 81,
        string $integritySql = 'PRAGMA integrity_check',
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity/index/foreign-key current-next81 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity/index/foreign-key current-next81 limit must be positive');
        }

        $rows = self::collect($database, $records, $foreignKeys, $tables, $integritySql);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $indexAdmissions = self::countRows($rows, 'index_admission');
        $indexBlockers = count(array_filter(
            $rows,
            static fn (array $row): bool => $row['kind'] === 'index_admission' && $row['status'] === 'blocked'
        ));
        $foreignKeyViolations = self::countRows($rows, 'foreign_key_check');
        $integrityErrors = count(array_filter(
            $rows,
            static fn (array $row): bool => $row['source'] !== 'index' && $row['source'] !== 'foreign_key'
        ));
        $blocking = [];
        if ($indexBlockers > 0) {
            $blocking[] = 'foreign_key_parent_unique_index';
        }
        if ($foreignKeyViolations > 0) {
            $blocking[] = 'foreign_key_check';
        }
        if ($integrityErrors > 0) {
            $blocking[] = 'integrity_check';
        }

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => [
                'index_admissions' => $indexAdmissions,
                'index_blockers' => $indexBlockers,
                'foreign_key_violations' => $foreignKeyViolations,
                'integrity_errors' => $integrityErrors,
            ],
            'next' => [
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<array{kind:string}> $rows
     */
    private static function countRows(array $rows, string $kind): int
    {
        return count(array_filter($rows, static fn (array $row): bool => $row['kind'] === $kind));
    }
}
