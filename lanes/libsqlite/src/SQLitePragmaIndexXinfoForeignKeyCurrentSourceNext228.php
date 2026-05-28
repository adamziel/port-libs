<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext228
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next228 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next228 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext224::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::parentKeySortOrderRows($currentRecords, 'current');
        $nextRows = self::parentKeySortOrderRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next228',
            'base' => $base['source_id'],
            'current_parent_key_sort_order' => self::rowSummary($currentRows),
            'next_parent_key_sort_order' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next228 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next228 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::sortOrderCounts($currentRows);
        $nextCounts = self::sortOrderCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next228',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_key_sort_order_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_desc_flags',
                'foreign_key_parent_key_sort_order' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_parent_key_sort_order_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_desc_flags',
                'foreign_key_parent_key_sort_order' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_key_sort_order' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_key_sort_order' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_key_sort_order_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_parent_key_sort_order_desc_columns' => $nextCounts['desc_columns'] - $currentCounts['desc_columns'],
                'foreign_key_parent_key_sort_order_repaired' => $currentCounts['missing_parent_unique_index'] > 0 && $nextCounts['missing_parent_unique_index'] === 0,
                'foreign_key_parent_key_sort_order_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-parent-sort-order-desc-compatible',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function parentKeySortOrderRows(array $records, string $phase = 'current'): array
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

            $candidate = self::matchingUniqueParentIndex($catalog, $parent, $parentColumns);
            foreach ($group as $row) {
                $seq = (int) $row['seq'];
                $indexRow = $candidate['rows'][$seq] ?? null;
                $descending = (int) ($indexRow['desc'] ?? 0) === 1;
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_parent_key_sort_order',
                    'table' => (string) $row['table'],
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => $seq,
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'parent_columns' => $parentColumns,
                    'parent_unique_index' => $candidate['index'],
                    'index_column_collation' => $indexRow['coll'] ?? null,
                    'index_column_desc' => $descending,
                    'sort_order_ignored_for_fk' => $candidate['status'] === 'ok',
                    'desc_columns' => $candidate['desc_columns'],
                    'status' => $candidate['status'],
                    'message' => $candidate['status'] === 'ok'
                        ? "foreign key {$row['table']}->{$parent} can use UNIQUE index {$candidate['index']} even when PRAGMA index_xinfo reports DESC parent-key columns"
                        : "foreign key {$row['table']}->{$parent} has no matching UNIQUE parent index for sort-order validation",
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
     * @return array{status:string,index:string|null,rows:list<array<string,mixed>>,desc_columns:list<string>}
     */
    private static function matchingUniqueParentIndex(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns): array
    {
        $wanted = array_map('strtolower', $parentColumns);
        foreach ($catalog->indexList($parent) as $index) {
            if ((int) ($index['unique'] ?? 0) !== 1 || (int) ($index['partial'] ?? 0) !== 0) {
                continue;
            }

            $indexName = (string) $index['name'];
            $keyRows = array_values(array_filter(
                $catalog->indexXInfo($indexName),
                static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1,
            ));
            $prefix = array_slice($keyRows, 0, count($wanted));
            $prefixColumns = array_map(static fn (array $row): string => strtolower((string) ($row['name'] ?? '')), $prefix);
            if ($prefixColumns !== $wanted) {
                continue;
            }

            return [
                'status' => 'ok',
                'index' => $indexName,
                'rows' => $prefix,
                'desc_columns' => array_values(array_map(
                    static fn (array $row): string => (string) $row['name'],
                    array_filter($prefix, static fn (array $row): bool => (int) ($row['desc'] ?? 0) === 1),
                )),
            ];
        }

        return [
            'status' => 'missing_parent_unique_index',
            'index' => null,
            'rows' => [],
            'desc_columns' => [],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,ok:int,missing_parent_unique_index:int,desc_columns:int,asc_columns:int,composite_columns:int}
     */
    private static function sortOrderCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'ok' => 0,
            'missing_parent_unique_index' => 0,
            'desc_columns' => 0,
            'asc_columns' => 0,
            'composite_columns' => 0,
        ];
        foreach ($rows as $row) {
            if (($row['status'] ?? null) === 'ok') {
                $counts['ok']++;
            } elseif (($row['status'] ?? null) === 'missing_parent_unique_index') {
                $counts['missing_parent_unique_index']++;
            }
            if (($row['index_column_desc'] ?? false) === true) {
                $counts['desc_columns']++;
            } else {
                $counts['asc_columns']++;
            }
            if ((int) ($row['seq'] ?? 0) > 0) {
                $counts['composite_columns']++;
            }
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
                (string) ($row['parent_unique_index'] ?? 'missing'),
                ($row['index_column_desc'] ?? false) ? 'DESC' : 'ASC',
                (string) ($row['status'] ?? ''),
            ], static fn (?string $part): bool => $part !== null)),
            $rows,
        );
        sort($summary);

        return $summary;
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
     * @param list<mixed> $records
     */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next228 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
