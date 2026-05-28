<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegrityRecoveryVacuumYield
{
    /**
     * @param list<array{kind?:string,source?:string,page?:int|null,pointer_map_page?:int|null,message:string}>|null $integrityRows
     * @return list<array{kind:string,source:string,page:int|null,pointer_map_page:int|null,action:string,recovery:string,vacuum:string,blocks_vacuum:bool,message:string}>
     */
    public static function collect(string|SQLiteDatabase $database, string $integritySql = 'PRAGMA integrity_check', ?array $integrityRows = null): array
    {
        $rows = $integrityRows ?? SQLitePragmaIntegrityCurrentNextYield::collect($database, [], $integritySql);
        $actions = [];

        foreach ($rows as $ordinal => $row) {
            if (!isset($row['message']) || !is_string($row['message'])) {
                throw new InvalidArgumentException("SQLite PRAGMA recovery/vacuum integrity row {$ordinal} needs a message");
            }

            $source = self::source($row);
            $page = self::rowPage($row);
            $pointerMapPage = self::pointerMapPage($row, $database, $page);
            $action = self::actionFor($source, $page, $pointerMapPage);

            $actions[] = [
                'kind' => (string) ($row['kind'] ?? 'integrity_check'),
                'source' => $source,
                'page' => $page,
                'pointer_map_page' => $pointerMapPage,
                'action' => $action['action'],
                'recovery' => $action['recovery'],
                'vacuum' => $action['vacuum'],
                'blocks_vacuum' => $action['blocks_vacuum'],
                'message' => $row['message'],
            ];
        }

        return $actions;
    }

    /**
     * @param list<array{kind?:string,source?:string,page?:int|null,pointer_map_page?:int|null,message:string}>|null $integrityRows
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{integrity:int,header:int,freelist:int,schema_root:int,pointer_map:int,btree:int,foreign_key:int,recovery_blockers:int,vacuum_blockers:int},next:array{ready_for_vacuum:bool,recovery_required:bool,actions:list<string>,blocking:list<string>},rows:list<array{kind:string,source:string,page:int|null,pointer_map_page:int|null,action:string,recovery:string,vacuum:string,blocks_vacuum:bool,message:string}>}
     */
    public static function page(string|SQLiteDatabase $database, int $offset = 0, int $limit = 77, string $integritySql = 'PRAGMA integrity_check', ?array $integrityRows = null): array
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity recovery/vacuum yield offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity recovery/vacuum yield limit must be positive');
        }

        $rows = self::collect($database, $integritySql, $integrityRows);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $current = self::counts($rows);
        $blocking = self::blocking($rows);

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => $current,
            'next' => [
                'ready_for_vacuum' => $blocking === [],
                'recovery_required' => $rows !== [],
                'actions' => array_values(array_unique(array_map(static fn (array $row): string => $row['action'], $rows))),
                'blocking' => $blocking,
            ],
            'rows' => $pageRows,
        ];
    }

    /**
     * @param array{source?:string,message:string} $row
     */
    private static function source(array $row): string
    {
        if (isset($row['source']) && is_string($row['source']) && $row['source'] !== '') {
            return $row['source'];
        }

        $message = strtolower($row['message']);
        if (str_contains($message, 'pointer-map')) {
            return 'pointer_map';
        }
        if (str_contains($message, 'freelist')) {
            return 'freelist';
        }
        if (str_contains($message, 'sqlite_schema') || str_contains($message, 'largest root btree page')) {
            return 'schema_root';
        }
        if (str_contains($message, 'header') || str_contains($message, 'schema write version') || str_contains($message, 'schema read version') || str_contains($message, 'text encoding')) {
            return 'header';
        }
        if (str_contains($message, 'btree') || str_contains($message, 'cell') || str_contains($message, 'freeblock')) {
            return 'btree';
        }
        if (str_contains($message, 'foreign key')) {
            return 'foreign_key';
        }

        return 'integrity';
    }

    /**
     * @param array{page?:int|null,message:string} $row
     */
    private static function rowPage(array $row): ?int
    {
        if (array_key_exists('page', $row) && ($row['page'] === null || is_int($row['page']))) {
            return $row['page'];
        }

        foreach ([
            '/for\s+page\s+(\d+)/i',
            '/for\s+[a-z-]+\s+page\s+(\d+)/i',
            '/page\s+(\d+)\s+pointer-map/i',
            '/marks\s+page\s+(\d+)/i',
            '/at\s+page\s+(\d+)/i',
            '/page\s+(\d+)/i',
        ] as $pattern) {
            if (preg_match($pattern, $row['message'], $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    /**
     * @param array{pointer_map_page?:int|null} $row
     */
    private static function pointerMapPage(array $row, string|SQLiteDatabase $database, ?int $page): ?int
    {
        if (array_key_exists('pointer_map_page', $row) && ($row['pointer_map_page'] === null || is_int($row['pointer_map_page']))) {
            return $row['pointer_map_page'];
        }
        if ($page === null) {
            return null;
        }
        if (is_string($database)) {
            try {
                $database = SQLiteDatabase::fromBytes($database);
            } catch (InvalidArgumentException) {
                return null;
            }
        }
        if (!$database->isAutoVacuum() || $page < 2 || $page > $database->pageCount() || $database->isPointerMapPage($page)) {
            return null;
        }

        return $database->pointerMapPageFor($page);
    }

    /**
     * @return array{action:string,recovery:string,vacuum:string,blocks_vacuum:bool}
     */
    private static function actionFor(string $source, ?int $page, ?int $pointerMapPage): array
    {
        return match ($source) {
            'header' => [
                'action' => 'repair_header_before_vacuum',
                'recovery' => 'reload_header_and_reject_vacuum_until_consistent',
                'vacuum' => 'blocked',
                'blocks_vacuum' => true,
            ],
            'schema_root' => [
                'action' => 'rebuild_schema_root_before_vacuum',
                'recovery' => 'recover_schema_root_page_chain',
                'vacuum' => 'blocked',
                'blocks_vacuum' => true,
            ],
            'freelist' => [
                'action' => 'rebuild_freelist_before_incremental_vacuum',
                'recovery' => $page === null ? 'scan_freelist_trunks' : "repair_freelist_chain_at_page_{$page}",
                'vacuum' => 'recount_freelist_then_resume',
                'blocks_vacuum' => true,
            ],
            'pointer_map' => [
                'action' => 'rewrite_pointer_map_before_auto_vacuum',
                'recovery' => $pointerMapPage === null ? 'rewrite_pointer_map_entry' : "rewrite_pointer_map_page_{$pointerMapPage}",
                'vacuum' => 'resume_auto_vacuum_after_pointer_map_rewrite',
                'blocks_vacuum' => true,
            ],
            'btree' => [
                'action' => 'rebalance_btree_before_vacuum',
                'recovery' => $page === null ? 'scan_btree_pages' : "recover_btree_page_{$page}",
                'vacuum' => 'resume_vacuum_after_btree_rebalance',
                'blocks_vacuum' => true,
            ],
            'foreign_key' => [
                'action' => 'defer_vacuum_until_foreign_key_check_clears',
                'recovery' => 'repair_foreign_key_rows_or_parent_indexes',
                'vacuum' => 'blocked',
                'blocks_vacuum' => true,
            ],
            default => [
                'action' => 'review_integrity_finding_before_vacuum',
                'recovery' => 'manual_integrity_review',
                'vacuum' => 'blocked',
                'blocks_vacuum' => true,
            ],
        };
    }

    /**
     * @param list<array{source:string,blocks_vacuum:bool}> $rows
     * @return array{integrity:int,header:int,freelist:int,schema_root:int,pointer_map:int,btree:int,foreign_key:int,recovery_blockers:int,vacuum_blockers:int}
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'integrity' => 0,
            'header' => 0,
            'freelist' => 0,
            'schema_root' => 0,
            'pointer_map' => 0,
            'btree' => 0,
            'foreign_key' => 0,
            'recovery_blockers' => 0,
            'vacuum_blockers' => 0,
        ];

        foreach ($rows as $row) {
            $source = $row['source'];
            $counts[$source] = ($counts[$source] ?? 0) + 1;
            $counts['recovery_blockers']++;
            if ($row['blocks_vacuum']) {
                $counts['vacuum_blockers']++;
            }
        }

        return $counts;
    }

    /**
     * @param list<array{source:string,blocks_vacuum:bool}> $rows
     * @return list<string>
     */
    private static function blocking(array $rows): array
    {
        $blocking = [];
        foreach ($rows as $row) {
            if ($row['blocks_vacuum']) {
                $blocking[] = $row['source'];
            }
        }

        return array_values(array_unique($blocking));
    }
}
