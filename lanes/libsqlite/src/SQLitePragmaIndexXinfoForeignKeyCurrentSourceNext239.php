<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext239
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next239 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next239 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext236::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::parentAuxiliaryIndexRows($currentRecords, 'current');
        $nextRows = self::parentAuxiliaryIndexRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next239',
            'base' => $base['source_id'],
            'current_parent_auxiliary_index_rows' => self::rowSummary($currentRows),
            'next_parent_auxiliary_index_rows' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next239 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next239 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::auxiliaryCounts($currentRows);
        $nextCounts = self::auxiliaryCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next239',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_auxiliary_index_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_key0_auxiliary_rows',
                'foreign_key_parent_auxiliary_index' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_parent_auxiliary_index_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_key0_auxiliary_rows',
                'foreign_key_parent_auxiliary_index' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_auxiliary_index' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_auxiliary_index' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_auxiliary_index_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_parent_auxiliary_index_rows_ignored' => $nextCounts['auxiliary_rows_ignored'] - $currentCounts['auxiliary_rows_ignored'],
                'foreign_key_parent_auxiliary_index_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
                'foreign_key_parent_auxiliary_index_repaired' => $currentCounts['auxiliary_rows_misclassified'] > 0 && $nextCounts['auxiliary_rows_misclassified'] === 0,
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-index-xinfo-key0-foreign-key-parent-auxiliary',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function parentAuxiliaryIndexRows(array $records, string $phase = 'current'): array
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

            $candidate = self::matchingParentIndexWithAuxiliaryRows($catalog, $parent, $parentColumns);
            foreach ($group as $row) {
                $seq = (int) $row['seq'];
                $indexRow = $candidate['key_rows'][$seq] ?? null;
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_parent_auxiliary_index',
                    'table' => (string) $row['table'],
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => $seq,
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'parent_columns' => $parentColumns,
                    'parent_unique_index' => $candidate['index'],
                    'index_key_columns' => $candidate['key_columns'],
                    'index_column' => $indexRow['name'] ?? null,
                    'auxiliary_columns' => $candidate['auxiliary_columns'],
                    'auxiliary_cids' => $candidate['auxiliary_cids'],
                    'auxiliary_rows_ignored' => count($candidate['auxiliary_columns']),
                    'status' => $candidate['status'],
                    'message' => $candidate['status'] === 'auxiliary_rows_ignored'
                        ? "foreign key {$row['table']}->{$parent} ignores PRAGMA index_xinfo key=0 auxiliary rows on {$candidate['index']}"
                        : "foreign key {$row['table']}->{$parent} has no UNIQUE parent index with ignorable auxiliary rows",
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
     * @return array{status:string,index:string|null,key_rows:list<array<string,mixed>>,key_columns:list<string>,auxiliary_columns:list<string>,auxiliary_cids:list<int>}
     */
    private static function matchingParentIndexWithAuxiliaryRows(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns): array
    {
        $wanted = array_map('strtolower', $parentColumns);
        foreach ($catalog->indexList($parent) as $index) {
            if ((int) ($index['unique'] ?? 0) !== 1 || (int) ($index['partial'] ?? 0) !== 0) {
                continue;
            }

            $indexName = (string) $index['name'];
            $xinfo = $catalog->indexXInfo($indexName);
            $keyRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1));
            $auxiliaryRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) ($row['key'] ?? 0) === 0));
            $keyColumns = array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $keyRows);
            if ($auxiliaryRows === [] || array_map('strtolower', $keyColumns) !== $wanted) {
                continue;
            }

            return [
                'status' => 'auxiliary_rows_ignored',
                'index' => $indexName,
                'key_rows' => $keyRows,
                'key_columns' => $keyColumns,
                'auxiliary_columns' => array_map(
                    static fn (array $row): string => $row['name'] === null ? 'rowid' : (string) $row['name'],
                    $auxiliaryRows,
                ),
                'auxiliary_cids' => array_map(static fn (array $row): int => (int) ($row['cid'] ?? -999), $auxiliaryRows),
            ];
        }

        return [
            'status' => 'missing_auxiliary_parent_unique_index',
            'index' => null,
            'key_rows' => [],
            'key_columns' => [],
            'auxiliary_columns' => [],
            'auxiliary_cids' => [],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,auxiliary_rows_ignored:int,auxiliary_rows_misclassified:int,rowid_auxiliary:int,without_rowid_primary_key_auxiliary:int,missing_auxiliary_parent_unique_index:int}
     */
    private static function auxiliaryCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'auxiliary_rows_ignored' => 0,
            'auxiliary_rows_misclassified' => 0,
            'rowid_auxiliary' => 0,
            'without_rowid_primary_key_auxiliary' => 0,
            'missing_auxiliary_parent_unique_index' => 0,
        ];
        foreach ($rows as $row) {
            if (($row['status'] ?? null) !== 'auxiliary_rows_ignored') {
                $counts['auxiliary_rows_misclassified']++;
                $counts['missing_auxiliary_parent_unique_index']++;
                continue;
            }

            $auxiliary = (array) ($row['auxiliary_columns'] ?? []);
            $counts['auxiliary_rows_ignored'] += count($auxiliary);
            if (in_array('rowid', $auxiliary, true)) {
                $counts['rowid_auxiliary']++;
            }
            foreach ($auxiliary as $column) {
                if ($column !== 'rowid') {
                    $counts['without_rowid_primary_key_auxiliary']++;
                }
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
                (string) ($row['parent_unique_index'] ?? 'missing-index'),
                'key=' . implode(',', (array) ($row['index_key_columns'] ?? [])),
                'aux=' . implode(',', (array) ($row['auxiliary_columns'] ?? [])),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next239 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
