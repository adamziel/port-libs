<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaForeignKeyIntegrityPaginationCurrentSourceYield
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current_source:array<string,mixed>,current:array<string,int>,next:array<string,mixed>,rows:list<array{kind:string,source:string,schema:string|null,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,page:int|null,pointer_map_page:int|null,message:string}>}
     */
    public static function page(
        string|SQLiteDatabase $database,
        array $schemas,
        SQLiteAttachedSchemaCatalog $catalog,
        string $foreignKeySql,
        int $offset = 0,
        int $limit = 93,
        string $integritySql = 'PRAGMA integrity_check',
        ?int $expectedSchemaGeneration = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key integrity pagination current-source next93 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key integrity pagination current-source next93 limit must be positive');
        }

        $foreignKeys = self::executeForeignKeySql($foreignKeySql, $schemas, $catalog);
        $currentSource = [
            'pragma' => $foreignKeys['pragma'],
            'schema' => $foreignKeys['schema'],
            'target_schema' => $foreignKeys['target_schema'],
            'target' => $foreignKeys['target'],
            'target_source' => $foreignKeys['target_source'],
            'schema_generation' => $catalog->schemaGeneration(),
            'expected_schema_generation' => $expectedSchemaGeneration,
            'schema_current' => $expectedSchemaGeneration === null || $expectedSchemaGeneration === $catalog->schemaGeneration(),
            'search_order' => $catalog->searchOrder(),
        ];

        if ($currentSource['schema_current'] === false) {
            return [
                'status' => 'stale',
                'offset' => $offset,
                'limit' => $limit,
                'count' => 0,
                'total' => 0,
                'next_offset' => null,
                'complete' => true,
                'current_source' => $currentSource,
                'current' => self::sourceCounts([]),
                'next' => [
                    'ready' => false,
                    'blocking' => ['schema_cache_stale'],
                    'resume_offset' => null,
                    'first_row' => null,
                    'last_row' => null,
                ],
                'rows' => [],
            ];
        }

        $rows = SQLitePragmaIntegrityCurrentNextYield::collect($database, [], $integritySql);
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
            'current_source' => $currentSource,
            'current' => self::sourceCounts($rows),
            'next' => [
                'ready' => $complete,
                'blocking' => $complete ? [] : ['foreign_key_integrity_page_pending'],
                'resume_offset' => $complete ? null : $nextOffset,
                'first_row' => self::boundaryRow($pageRows[0] ?? null),
                'last_row' => self::boundaryRow($pageRows[count($pageRows) - 1] ?? null),
            ],
            'rows' => $pageRows,
        ];
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{status:string,pragma:string,schema:string,target_schema:string,target:string|null,target_source:string,rows:list<array{schema:string,table:string,rowid:int|string|null,parent:string,fkid:int}>}
     */
    private static function executeForeignKeySql(string $sql, array $schemas, SQLiteAttachedSchemaCatalog $catalog): array
    {
        try {
            return SQLitePragmaForeignKeyIntegrity::executeTableValued($sql, $schemas, $catalog);
        } catch (InvalidArgumentException $tableValuedError) {
            try {
                return SQLitePragmaForeignKeyIntegrity::execute($sql, $schemas, $catalog);
            } catch (InvalidArgumentException) {
                throw $tableValuedError;
            }
        }
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

    /**
     * @param array<string,mixed>|null $row
     * @return array{kind:string,source:string,schema:string|null,table:string|null,rowid:int|string|null,page:int|null}|null
     */
    private static function boundaryRow(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'kind' => (string) $row['kind'],
            'source' => (string) $row['source'],
            'schema' => $row['schema'],
            'table' => $row['table'],
            'rowid' => $row['rowid'],
            'page' => $row['page'],
        ];
    }
}
