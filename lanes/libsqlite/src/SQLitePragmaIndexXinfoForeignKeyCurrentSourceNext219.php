<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext219
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next219 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next219 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext217::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::permutedParentUniqueRows($currentRecords, 'current');
        $nextRows = self::permutedParentUniqueRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next219',
            'base' => $base['source_id'],
            'current_parent_key_permutation' => self::rowSummary($currentRows),
            'next_parent_key_permutation' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next219 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next219 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::permutationCounts($currentRows);
        $nextCounts = self::permutationCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next219',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_key_permutation_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_unique_column_order',
                'foreign_key_parent_key_permutation' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_parent_key_permutation_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_unique_column_order',
                'foreign_key_parent_key_permutation' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_key_permutation' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_key_permutation' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_key_permutation_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_parent_key_permutation_blockers' => $nextCounts['permuted_parent_unique_index'] - $currentCounts['permuted_parent_unique_index'],
                'foreign_key_parent_key_permutation_repaired' => $currentCounts['permuted_parent_unique_index'] > 0 && $nextCounts['permuted_parent_unique_index'] === 0,
                'foreign_key_parent_key_permutation_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-parent-unique-column-order',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function permutedParentUniqueRows(array $records, string $phase = 'current'): array
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

            $candidate = self::permutedUniqueCandidate($catalog, $parent, $parentColumns);
            if ($candidate === null) {
                continue;
            }

            foreach ($group as $row) {
                $seq = (int) $row['seq'];
                $actualPosition = array_search(strtolower((string) $row['to']), $candidate['columns'], true);
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_parent_key_permutation',
                    'table' => (string) $row['table'],
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => $seq,
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'parent_columns' => $parentColumns,
                    'permuted_unique_index' => $candidate['index'],
                    'permuted_unique_columns' => $candidate['original_columns'],
                    'expected_position' => $seq,
                    'actual_position' => $actualPosition === false ? null : $actualPosition,
                    'status' => 'permuted_parent_unique_index',
                    'message' => "foreign key {$row['table']}->{$parent} parent columns match UNIQUE index {$candidate['index']} only as a permutation, not in FK order",
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
     * @return array{index:string,columns:list<string>,original_columns:list<string>}|null
     */
    private static function permutedUniqueCandidate(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns): ?array
    {
        $wanted = array_map('strtolower', $parentColumns);
        sort($wanted);

        foreach ($catalog->indexList($parent) as $index) {
            if ((int) ($index['unique'] ?? 0) !== 1 || (int) ($index['partial'] ?? 0) !== 0) {
                continue;
            }

            $xinfo = $catalog->indexXInfo((string) $index['name']);
            $keyRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1));
            $columns = array_map(static fn (array $row): string => strtolower((string) ($row['name'] ?? '')), $keyRows);
            if (count($columns) !== count($parentColumns) || $columns === array_map('strtolower', $parentColumns)) {
                continue;
            }

            $sorted = $columns;
            sort($sorted);
            if ($sorted === $wanted) {
                return [
                    'index' => (string) $index['name'],
                    'columns' => $columns,
                    'original_columns' => array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $keyRows),
                ];
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,permuted_parent_unique_index:int,foreign_keys:int,reordered_columns:int}
     */
    private static function permutationCounts(array $rows): array
    {
        $foreignKeys = [];
        $reordered = 0;
        foreach ($rows as $row) {
            $foreignKeys[(string) $row['table'] . '#' . (int) $row['foreign_key_id']] = true;
            if (($row['actual_position'] ?? null) !== ($row['expected_position'] ?? null)) {
                $reordered++;
            }
        }

        return [
            'rows' => count($rows),
            'permuted_parent_unique_index' => count($rows),
            'foreign_keys' => count($foreignKeys),
            'reordered_columns' => $reordered,
        ];
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
                (string) $row['permuted_unique_index'],
                'columns=' . implode(',', (array) $row['permuted_unique_columns']),
                'expected=' . (int) $row['expected_position'],
                'actual=' . (($row['actual_position'] ?? null) === null ? 'null' : (string) $row['actual_position']),
                (string) $row['status'],
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next219 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
