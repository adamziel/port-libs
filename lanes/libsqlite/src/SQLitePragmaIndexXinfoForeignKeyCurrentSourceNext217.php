<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext217
{
    /**
     * @param list<SQLiteSchemaRecord> $currentRecords
     * @param list<SQLiteSchemaRecord> $nextRecords
     * @param array{source_id:string,offset:int}|null $resume
     * @return array<string,mixed>
     */
    public static function page(
        array $currentRecords,
        array $nextRecords,
        string $indexXinfoSql,
        string $foreignKeySql,
        int $offset = 0,
        int $limit = 50,
        ?array $resume = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next217 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next217 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext212::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::parentKeyPrefixRows($currentRecords, 'current');
        $nextRows = self::parentKeyPrefixRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next217',
            'base' => $base['source_id'],
            'current_parent_key_prefix' => self::rowSummary($currentRows),
            'next_parent_key_prefix' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next217 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next217 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::prefixCounts($currentRows);
        $nextCounts = self::prefixCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next217',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_key_prefix_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_unique_prefix',
                'foreign_key_parent_key_prefix' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_parent_key_prefix_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_unique_prefix',
                'foreign_key_parent_key_prefix' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_key_prefix' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_key_prefix' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_key_prefix_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_parent_key_prefix_blockers' => $nextCounts['blocked'] - $currentCounts['blocked'],
                'foreign_key_parent_key_prefix_repaired' => $currentCounts['blocked'] > 0 && $nextCounts['blocked'] === 0,
                'foreign_key_parent_key_prefix_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-parent-unique-prefix',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function parentKeyPrefixRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase)) as $group) {
            $parent = (string) $group[0]['parent'];
            $parentColumns = array_map(static fn (array $row): string => (string) $row['to'], $group);
            if (in_array('', $parentColumns, true)) {
                continue;
            }

            $candidate = self::matchingParentKey($catalog, $parent, $parentColumns);
            foreach ($group as $row) {
                $seq = (int) $row['seq'];
                $indexRow = $candidate['rows'][$seq] ?? null;
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_parent_key_prefix',
                    'table' => (string) $row['table'],
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => $seq,
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'parent_columns' => $parentColumns,
                    'parent_unique_index' => $candidate['full_index'],
                    'suffix_unique_index' => $candidate['suffix_index'],
                    'partial_unique_index' => $candidate['partial_index'],
                    'matched_index' => $candidate['full_index'] ?? $candidate['suffix_index'] ?? $candidate['partial_index'],
                    'matched_index_offset' => $candidate['offset'],
                    'matched_index_seqno' => $indexRow['seqno'] ?? null,
                    'matched_index_column' => $indexRow['name'] ?? null,
                    'covered_parent_columns' => $candidate['covered_columns'],
                    'status' => $candidate['status'],
                    'message' => $candidate['status'] === 'ok'
                        ? "foreign key {$row['table']}->{$parent} parent key is a leading UNIQUE index prefix"
                        : ($candidate['status'] === 'suffix_parent_unique_index'
                            ? "foreign key {$row['table']}->{$parent} parent key is only a suffix of UNIQUE index {$candidate['suffix_index']}"
                            : ($candidate['status'] === 'partial_parent_unique_index'
                                ? "foreign key {$row['table']}->{$parent} parent key only has partial UNIQUE index {$candidate['partial_index']}"
                                : "foreign key {$row['table']}->{$parent} parent key has no matching UNIQUE index prefix")),
                ];
            }
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['phase'], $left['table'], $left['foreign_key_id'], $left['seq']]
                <=> [$right['phase'], $right['table'], $right['foreign_key_id'], $right['seq']],
        );

        return $rows;
    }

    /**
     * @param list<string> $parentColumns
     * @return array{status:string,full_index:string|null,suffix_index:string|null,partial_index:string|null,offset:int|null,rows:list<array<string,mixed>>,covered_columns:int}
     */
    private static function matchingParentKey(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns): array
    {
        $wanted = array_map('strtolower', $parentColumns);
        $primaryKey = self::primaryKeyColumns($catalog, $parent);
        if (array_map('strtolower', $primaryKey) === $wanted) {
            return [
                'status' => 'ok',
                'full_index' => 'sqlite_primary_key',
                'suffix_index' => null,
                'partial_index' => null,
                'offset' => 0,
                'rows' => array_map(
                    static fn (string $column, int $seqno): array => [
                        'seqno' => $seqno,
                        'cid' => $seqno,
                        'name' => $column,
                        'desc' => 0,
                        'coll' => 'BINARY',
                        'key' => 1,
                    ],
                    $primaryKey,
                    array_keys($primaryKey),
                ),
                'covered_columns' => count($wanted),
            ];
        }

        $suffix = null;
        $suffixOffset = null;
        $suffixRows = [];
        $partial = null;
        $partialRows = [];
        $covered = 0;

        foreach ($catalog->indexList($parent) as $index) {
            if ((int) ($index['unique'] ?? 0) !== 1) {
                continue;
            }

            $xinfo = $catalog->indexXInfo((string) $index['name']);
            $keyRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1));
            $columns = array_map(static fn (array $row): string => strtolower((string) ($row['name'] ?? '')), $keyRows);
            $prefix = array_slice($columns, 0, count($wanted));
            $covered = max($covered, count(array_intersect($wanted, $columns)));

            if ($prefix === $wanted) {
                if ((int) ($index['partial'] ?? 0) === 0) {
                    return [
                        'status' => 'ok',
                        'full_index' => (string) $index['name'],
                        'suffix_index' => $suffix,
                        'partial_index' => $partial,
                        'offset' => 0,
                        'rows' => array_slice($keyRows, 0, count($wanted)),
                        'covered_columns' => count($wanted),
                    ];
                }

                $partial ??= (string) $index['name'];
                $partialRows = $partialRows === [] ? array_slice($keyRows, 0, count($wanted)) : $partialRows;
                continue;
            }

            $offset = self::subsequenceOffset($columns, $wanted);
            if ($offset !== null && (int) ($index['partial'] ?? 0) === 0) {
                $suffix ??= (string) $index['name'];
                $suffixOffset ??= $offset;
                $suffixRows = $suffixRows === [] ? array_slice($keyRows, $offset, count($wanted)) : $suffixRows;
            }
        }

        if ($suffix !== null) {
            return [
                'status' => 'suffix_parent_unique_index',
                'full_index' => null,
                'suffix_index' => $suffix,
                'partial_index' => $partial,
                'offset' => $suffixOffset,
                'rows' => $suffixRows,
                'covered_columns' => count($wanted),
            ];
        }

        if ($partial !== null) {
            return [
                'status' => 'partial_parent_unique_index',
                'full_index' => null,
                'suffix_index' => null,
                'partial_index' => $partial,
                'offset' => 0,
                'rows' => $partialRows,
                'covered_columns' => count($partialRows),
            ];
        }

        return [
            'status' => 'missing_parent_unique_index',
            'full_index' => null,
            'suffix_index' => null,
            'partial_index' => null,
            'offset' => null,
            'rows' => [],
            'covered_columns' => $covered,
        ];
    }

    /**
     * @return list<string>
     */
    private static function primaryKeyColumns(SQLitePragmaSchemaCatalog $catalog, string $table): array
    {
        $columns = [];
        foreach ($catalog->tableInfo($table) as $row) {
            $pk = (int) ($row['pk'] ?? 0);
            if ($pk > 0) {
                $columns[$pk] = (string) $row['name'];
            }
        }
        ksort($columns);

        return array_values($columns);
    }

    /**
     * @param list<string> $haystack
     * @param list<string> $needle
     */
    private static function subsequenceOffset(array $haystack, array $needle): ?int
    {
        if ($needle === [] || count($needle) > count($haystack)) {
            return null;
        }
        $limit = count($haystack) - count($needle);
        for ($offset = 1; $offset <= $limit; $offset++) {
            if (array_slice($haystack, $offset, count($needle)) === $needle) {
                return $offset;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<list<array<string,mixed>>>
     */
    private static function groupForeignKeyRows(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[strtolower((string) $row['table']) . '#' . (int) $row['id']][] = $row;
        }
        foreach ($groups as &$group) {
            usort($group, static fn (array $left, array $right): int => (int) $left['seq'] <=> (int) $right['seq']);
        }

        return array_values($groups);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,ok:int,blocked:int,suffix_parent_unique_index:int,partial_parent_unique_index:int,missing_parent_unique_index:int,matched_suffix_columns:int,covered_parent_columns:int}
     */
    private static function prefixCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'ok' => 0,
            'blocked' => 0,
            'suffix_parent_unique_index' => 0,
            'partial_parent_unique_index' => 0,
            'missing_parent_unique_index' => 0,
            'matched_suffix_columns' => 0,
            'covered_parent_columns' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'ok') {
                $counts['ok']++;
            } else {
                $counts['blocked']++;
                if (isset($counts[$status])) {
                    $counts[$status]++;
                }
            }
            if ($status === 'suffix_parent_unique_index') {
                $counts['matched_suffix_columns']++;
            }
            $counts['covered_parent_columns'] += (int) ($row['covered_parent_columns'] ?? 0);
        }

        return $counts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function rowSummary(array $rows, bool $includePhase = true): array
    {
        $summary = array_map(
            static fn (array $row): string => implode(':', array_filter([
                $includePhase ? (string) $row['phase'] : null,
                (string) $row['table'] . '#' . (int) $row['foreign_key_id'] . '.' . (int) $row['seq'],
                (string) $row['from'] . '->' . (string) $row['parent'] . '.' . (string) $row['to'],
                'parent=' . implode(',', (array) $row['parent_columns']),
                (string) ($row['parent_unique_index'] ?? 'missing-full'),
                (string) ($row['suffix_unique_index'] ?? 'missing-suffix'),
                (string) ($row['partial_unique_index'] ?? 'missing-partial'),
                'offset=' . (($row['matched_index_offset'] ?? null) === null ? 'null' : (string) $row['matched_index_offset']),
                (string) ($row['status'] ?? ''),
            ], static fn (?string $part): bool => $part !== null)),
            $rows,
        );
        sort($summary);

        return $summary;
    }

    /**
     * @param list<mixed> $records
     */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next217 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
