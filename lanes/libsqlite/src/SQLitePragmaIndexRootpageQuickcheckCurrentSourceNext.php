<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext
{
    /**
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array<string,mixed>,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{index_xinfo:int,quick_check:int,quick_check_errors:int,target_schema:string,target_index:string|null,target_table:string|null},next:array{source_id:string,offset:int}|null,next_row:array<string,mixed>|null,rows:list<array<string,mixed>>}
     */
    public static function page(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        int $offset = 0,
        int $limit = 129,
        string $quickCheckSql = 'PRAGMA quick_check',
        bool $tableValued = false,
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index rootpage quickcheck current-source next129 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA index rootpage quickcheck current-source next129 limit must be positive');
        }
        if (self::quickCheckPragma($quickCheckSql) !== 'quick_check') {
            throw new InvalidArgumentException('SQLite PRAGMA index rootpage quickcheck current-source next129 requires PRAGMA quick_check SQL');
        }

        $base = SQLitePragmaIntegrityIndexRootpageCurrentSourceNext::page(
            $catalog,
            $indexXinfoSql,
            $database,
            0,
            PHP_INT_MAX,
            $quickCheckSql,
            $tableValued,
        );
        $sourceId = self::stableHash([
            'base' => $base['source_id'],
            'mode' => 'pragma-index-rootpage-quickcheck-current-source-next129',
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $rows = self::collectFromBase($base, $quickCheckSql, $database);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $counts = self::counts($rows);

        return [
            'status' => $counts['quick_check_errors'] === 0 ? 'ok' : 'blocked',
            'source_id' => $sourceId,
            'current_source' => [
                ...$base['current_source'],
                'quick_check_sql' => self::normalizeSql($quickCheckSql),
                'source_mode' => 'index_rootpage_quickcheck_current_source_next129',
            ],
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => $counts,
            'next' => $complete ? null : [
                'source_id' => $sourceId,
                'offset' => $nextOffset,
            ],
            'next_row' => $pageRows[1] ?? null,
            'rows' => $pageRows,
        ];
    }

    /**
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array<string,mixed>,next_source:array<string,mixed>,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,mixed>,next_counts:array<string,mixed>,delta:array<string,mixed>,next_state:array{ready:bool,blocking:list<string>},next:array{source_id:string,offset:int}|null,next_row:array<string,mixed>|null,rows:list<array<string,mixed>>}
     */
    public static function currentNextPage(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $currentCatalog,
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $nextCatalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $currentDatabase,
        string|SQLiteDatabase $nextDatabase,
        int $offset = 0,
        int $limit = 146,
        string $quickCheckSql = 'PRAGMA quick_check',
        bool $tableValuedIndexXinfo = false,
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index rootpage quickcheck current-source next146 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA index rootpage quickcheck current-source next146 limit must be positive');
        }

        $current = self::page(
            $currentCatalog,
            $indexXinfoSql,
            $currentDatabase,
            0,
            PHP_INT_MAX,
            $quickCheckSql,
            $tableValuedIndexXinfo,
        );
        $next = self::page(
            $nextCatalog,
            $indexXinfoSql,
            $nextDatabase,
            0,
            PHP_INT_MAX,
            $quickCheckSql,
            $tableValuedIndexXinfo,
        );

        $sourceId = self::stableHash([
            'mode' => 'pragma-index-rootpage-quickcheck-current-source-next146',
            'current' => $current['source_id'],
            'next' => $next['source_id'],
        ]);
        if ($cursor !== null) {
            self::validateCurrentNextCursor($cursor, $sourceId, $offset);
        }

        $currentRows = array_map(
            static fn (array $row): array => self::sideRow('current', $row),
            $current['rows'],
        );
        $nextRows = array_map(
            static fn (array $row): array => self::sideRow('next', $row),
            $next['rows'],
        );
        $rows = [...$currentRows, ...$nextRows];
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $currentCounts = self::currentNextCounts($currentRows);
        $nextCounts = self::currentNextCounts($nextRows);
        $blocking = self::blocking($nextCounts);

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'source_id' => $sourceId,
            'current_source' => self::publicSource($current),
            'next_source' => self::publicSource($next),
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => $currentCounts,
            'next_counts' => $nextCounts,
            'delta' => [
                'quick_check_errors' => $nextCounts['quick_check_errors'] - $currentCounts['quick_check_errors'],
                'target_quick_check_errors' => $nextCounts['target_quick_check_errors'] - $currentCounts['target_quick_check_errors'],
                'non_target_quick_check_errors' => $nextCounts['non_target_quick_check_errors'] - $currentCounts['non_target_quick_check_errors'],
                'total_blockers' => $nextCounts['total_blockers'] - $currentCounts['total_blockers'],
                'cleared' => $currentCounts['total_blockers'] > 0 && $nextCounts['total_blockers'] === 0,
            ],
            'next_state' => [
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
            'next' => $complete ? null : [
                'source_id' => $sourceId,
                'offset' => $nextOffset,
            ],
            'next_row' => $pageRows[1] ?? null,
            'rows' => $pageRows,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function sideRow(string $side, array $row): array
    {
        $phase = ($row['kind'] ?? null) === 'quick_check' ? 'quick_check' : 'index_xinfo';

        return [
            'side' => $side,
            'phase' => $phase,
            ...$row,
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @return list<array<string,mixed>>
     */
    private static function collectFromBase(array $base, string $quickCheckSql, string|SQLiteDatabase $database): array
    {
        $indexRows = array_values(array_filter(
            $base['rows'],
            static fn (array $row): bool => ($row['kind'] ?? null) === 'index_xinfo',
        ));
        $analysis = SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext::analyze($database);
        $rootRows = $analysis['rows'];
        $rootByMessage = [];
        $rootByPage = [];
        foreach ($rootRows as $row) {
            if (isset($row['message']) && is_string($row['message'])) {
                $rootByMessage[$row['message']] = $row;
            }
            if (isset($row['rootpage']) && is_int($row['rootpage'])) {
                $rootByPage[$row['rootpage']] = $row;
            }
        }

        $quick = SQLitePragmaIntegrityCheck::execute($quickCheckSql, $database);
        $quickRows = [];
        foreach ($quick['rows'] as $ordinal => $row) {
            $message = (string) $row['quick_check'];
            if ($message === 'ok') {
                $quickRows[] = [
                    'kind' => 'quick_check',
                    'source' => 'quick_check',
                    'schema' => $base['current']['target_schema'],
                    'target' => $base['current']['target_index'],
                    'table' => $base['current']['target_table'],
                    'pragma' => 'quick_check',
                    'ordinal' => $ordinal,
                    'message' => 'ok',
                    'rootpage' => null,
                    'page_status' => 'ok',
                    'page_type' => null,
                    'pointer_map_type' => null,
                    'pointer_map_parent' => null,
                    'pointer_map_page' => null,
                    'target_match' => false,
                ];
                continue;
            }

            $root = $rootByMessage[$message] ?? null;
            if ($root === null && preg_match('/\bpage (\d+)\b/', $message, $matches)) {
                $root = $rootByPage[(int) $matches[1]] ?? null;
            }
            $targetMatch = $root !== null
                && (($root['name'] ?? null) === $base['current']['target_index']
                    || (($root['type'] ?? null) === 'table' && ($root['name'] ?? null) === $base['current']['target_table']));
            $quickRows[] = [
                'kind' => 'quick_check',
                'source' => 'quick_check',
                'schema' => $base['current']['target_schema'],
                'target' => $base['current']['target_index'],
                'table' => $root['table'] ?? $base['current']['target_table'],
                'pragma' => 'quick_check',
                'ordinal' => $ordinal,
                'message' => $message,
                'rootpage' => $root['rootpage'] ?? null,
                'page_status' => $root['page_status'] ?? 'error',
                'page_type' => $root['page_type'] ?? null,
                'pointer_map_type' => $root['pointer_map_type'] ?? null,
                'pointer_map_parent' => $root['pointer_map_parent'] ?? null,
                'pointer_map_page' => $root['pointer_map_page'] ?? null,
                'target_match' => $targetMatch,
            ];
        }

        return [
            ...array_map(
                static fn (array $row): array => [
                    ...$row,
                    'source' => 'index_xinfo',
                    'pragma' => null,
                    'ordinal' => null,
                    'target_match' => true,
                ],
                $indexRows,
            ),
            ...$quickRows,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{index_xinfo:int,quick_check:int,quick_check_errors:int,target_schema:string,target_index:string|null,target_table:string|null}
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'index_xinfo' => 0,
            'quick_check' => 0,
            'quick_check_errors' => 0,
            'target_schema' => 'main',
            'target_index' => null,
            'target_table' => null,
        ];
        foreach ($rows as $row) {
            if (($row['kind'] ?? null) === 'index_xinfo') {
                $counts['index_xinfo']++;
                $counts['target_schema'] = (string) ($row['schema'] ?? $counts['target_schema']);
                $counts['target_index'] = isset($row['target']) ? (string) $row['target'] : $counts['target_index'];
                continue;
            }
            if (($row['kind'] ?? null) === 'quick_check') {
                $counts['quick_check']++;
                $counts['target_schema'] = (string) ($row['schema'] ?? $counts['target_schema']);
                $counts['target_index'] = isset($row['target']) ? (string) $row['target'] : $counts['target_index'];
                $counts['target_table'] = isset($row['table']) ? (string) $row['table'] : $counts['target_table'];
                if (($row['message'] ?? null) !== 'ok') {
                    $counts['quick_check_errors']++;
                }
            }
        }

        return $counts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{index_xinfo:int,quick_check:int,quick_check_errors:int,target_quick_check_errors:int,non_target_quick_check_errors:int,total_blockers:int,target_schema:string,target_index:string|null,target_table:string|null,quick_messages:list<string>}
     */
    private static function currentNextCounts(array $rows): array
    {
        $counts = [
            'index_xinfo' => 0,
            'quick_check' => 0,
            'quick_check_errors' => 0,
            'target_quick_check_errors' => 0,
            'non_target_quick_check_errors' => 0,
            'total_blockers' => 0,
            'target_schema' => 'main',
            'target_index' => null,
            'target_table' => null,
            'quick_messages' => [],
        ];

        foreach ($rows as $row) {
            $counts['target_schema'] = (string) ($row['schema'] ?? $counts['target_schema']);
            if (isset($row['target']) && is_string($row['target'])) {
                $counts['target_index'] = $row['target'];
            }
            if (isset($row['table']) && is_string($row['table'])) {
                $counts['target_table'] = $row['table'];
            }

            if (($row['kind'] ?? null) === 'index_xinfo') {
                $counts['index_xinfo']++;
                continue;
            }
            if (($row['kind'] ?? null) !== 'quick_check') {
                continue;
            }

            $counts['quick_check']++;
            $message = (string) ($row['message'] ?? '');
            $counts['quick_messages'][] = $message;
            if ($message === 'ok') {
                continue;
            }

            $counts['quick_check_errors']++;
            $counts['total_blockers']++;
            if (($row['target_match'] ?? false) === true) {
                $counts['target_quick_check_errors']++;
            } else {
                $counts['non_target_quick_check_errors']++;
            }
        }

        return $counts;
    }

    /**
     * @param array{quick_check_errors:int,target_quick_check_errors:int,non_target_quick_check_errors:int} $counts
     * @return list<string>
     */
    private static function blocking(array $counts): array
    {
        $blocking = [];
        if ($counts['target_quick_check_errors'] > 0) {
            $blocking[] = 'target_index_rootpage_quick_check';
        }
        if ($counts['non_target_quick_check_errors'] > 0) {
            $blocking[] = 'database_rootpage_quick_check';
        }
        if ($counts['quick_check_errors'] > 0 && $blocking === []) {
            $blocking[] = 'quick_check';
        }

        return $blocking;
    }

    /**
     * @param array<string,mixed> $page
     * @return array<string,mixed>
     */
    private static function publicSource(array $page): array
    {
        return [
            ...$page['current_source'],
            'source_id' => $page['source_id'],
            'status' => $page['status'],
            'target_schema' => $page['current']['target_schema'],
            'target_index' => $page['current']['target_index'],
            'target_table' => $page['current']['target_table'],
        ];
    }

    private static function quickCheckPragma(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (!preg_match('/^PRAGMA\s+(?:(?:main|temp|[A-Za-z_][A-Za-z0-9_]*)\s*\.\s*)?(integrity_check|quick_check)(?:\s*(?:\(\s*\d+\s*\)|=\s*\d+))?$/i', $trimmed, $matches)) {
            throw new InvalidArgumentException('Unsupported SQLite quick_check PRAGMA SQL');
        }

        return strtolower($matches[1]);
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index rootpage quickcheck current-source next129 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index rootpage quickcheck current-source next129 cursor offset does not match the requested page offset');
        }
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCurrentNextCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index rootpage quickcheck current-source next146 cursor does not match the current/next source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index rootpage quickcheck current-source next146 cursor offset does not match the requested page offset');
        }
    }

    private static function normalizeSql(string $sql): string
    {
        return strtolower(preg_replace('/\s+/', ' ', rtrim(trim($sql), ';')) ?? trim($sql));
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
