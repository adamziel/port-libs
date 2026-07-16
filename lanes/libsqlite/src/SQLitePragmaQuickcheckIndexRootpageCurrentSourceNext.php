<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaQuickcheckIndexRootpageCurrentSourceNext
{
    /**
     * @return list<array<string,mixed>>
     */
    public static function collect(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        bool $tableValued = false,
    ): array {
        return array_map(
            static function (array $row): array {
                return [
                    ...$row,
                    'quickcheck_source' => 'pragma quick_check',
                    'quickcheck_index_rootpage' => ($row['kind'] ?? null) === 'rootpage',
                    'quickcheck_requires_integrity_check' => ($row['kind'] ?? null) === 'rootpage'
                        && ($row['page_status'] ?? 'ok') !== 'ok',
                ];
            },
            SQLitePragmaIntegrityIndexRootpageCurrentSourceNext::collect(
                $catalog,
                $indexXinfoSql,
                $database,
                'PRAGMA quick_check',
                $tableValued,
            ),
        );
    }

    /**
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array{database:string,catalog:string,index_xinfo_sql:string,integrity_sql:string,table_valued:bool},offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,mixed>,quickcheck:array{pragma:string,index_xinfo:int,rootpage:int,rootpage_errors:int,target_schema:string,target_index:string|null,target_table:string|null,needs_integrity_check:bool,source_stable:bool},next:array{source_id:string,offset:int}|null,next_row:array<string,mixed>|null,rows:list<array<string,mixed>>}
     */
    public static function page(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        int $offset = 0,
        int $limit = 135,
        bool $tableValued = false,
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA quick_check index rootpage current-source next135 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA quick_check index rootpage current-source next135 limit must be positive');
        }

        $base = SQLitePragmaIntegrityIndexRootpageCurrentSourceNext::page(
            $catalog,
            $indexXinfoSql,
            $database,
            $offset,
            $limit,
            'PRAGMA quick_check',
            $tableValued,
            $cursor,
        );
        $rows = self::collect($catalog, $indexXinfoSql, $database, $tableValued);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $rootpageErrors = (int) ($base['current']['rootpage_errors'] ?? 0);

        return [
            ...$base,
            'status' => $rootpageErrors === 0 ? 'ok' : 'blocked',
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'quickcheck' => [
                'pragma' => 'quick_check',
                'index_xinfo' => (int) ($base['current']['index_xinfo'] ?? 0),
                'rootpage' => (int) ($base['current']['rootpage'] ?? 0),
                'rootpage_errors' => $rootpageErrors,
                'target_schema' => (string) ($base['current']['target_schema'] ?? 'main'),
                'target_index' => isset($base['current']['target_index']) ? (string) $base['current']['target_index'] : null,
                'target_table' => isset($base['current']['target_table']) ? (string) $base['current']['target_table'] : null,
                'needs_integrity_check' => $rootpageErrors > 0,
                'source_stable' => ($base['current_source']['integrity_sql'] ?? null) === 'pragma quick_check',
            ],
            'next' => $complete ? null : [
                'source_id' => $base['source_id'],
                'offset' => $nextOffset,
            ],
            'next_row' => $pageRows[1] ?? null,
            'rows' => $pageRows,
        ];
    }
}
