<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext238
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next238 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next238 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext234::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::parentDescendingKeyRows($currentRecords, 'current');
        $nextRows = self::parentDescendingKeyRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next238',
            'base' => $base['source_id'],
            'current_parent_descending_key' => self::rowSummary($currentRows),
            'next_parent_descending_key' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next238 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next238 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::descendingCounts($currentRows);
        $nextCounts = self::descendingCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next238',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_descending_key_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_desc_flags',
                'foreign_key_parent_descending_key' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_parent_descending_key_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_desc_flags',
                'foreign_key_parent_descending_key' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_descending_key' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_descending_key' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_descending_key_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_parent_descending_key_blockers' => $nextCounts['blocked'] - $currentCounts['blocked'],
                'foreign_key_parent_descending_key_repaired' => $currentCounts['blocked'] > 0 && $nextCounts['blocked'] === 0,
                'foreign_key_parent_descending_key_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-parent-desc-index-admission',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function parentDescendingKeyRows(array $records, string $phase = 'current'): array
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

            $candidate = self::matchingDescendingParentKey($catalog, $parent, $parentColumns);
            foreach ($group as $row) {
                $seq = (int) $row['seq'];
                $indexRow = $candidate['rows'][$seq] ?? null;
                $descending = (int) ($indexRow['desc'] ?? 0) === 1;
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_parent_descending_key',
                    'table' => (string) $row['table'],
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => $seq,
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'parent_columns' => $parentColumns,
                    'parent_unique_index' => $candidate['index'],
                    'candidate_index' => $candidate['candidate_index'],
                    'candidate_columns' => $candidate['candidate_columns'],
                    'index_column' => $indexRow['name'] ?? null,
                    'index_column_cid' => $indexRow['cid'] ?? null,
                    'index_column_desc' => $indexRow['desc'] ?? null,
                    'index_column_collation' => $indexRow['coll'] ?? null,
                    'descending_parent_terms' => $candidate['descending_terms'],
                    'all_parent_terms_descending' => $candidate['all_descending'],
                    'status' => $candidate['status'],
                    'message' => match ($candidate['status']) {
                        'ok_desc_parent_unique_index' => "foreign key {$row['table']}->{$parent} may use descending UNIQUE parent index {$candidate['index']}",
                        'ok' => "foreign key {$row['table']}->{$parent} may use ascending UNIQUE parent index {$candidate['index']}",
                        'permuted_desc_parent_unique_index' => "foreign key {$row['table']}->{$parent} has descending UNIQUE index {$candidate['candidate_index']} with parent columns in the wrong order",
                        default => "foreign key {$row['table']}->{$parent} has no non-partial UNIQUE parent index for descending-key admission",
                    },
                    'desc_admissible' => $candidate['status'] === 'ok' || $candidate['status'] === 'ok_desc_parent_unique_index',
                    'this_term_descending' => $descending,
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
     * @return array{status:string,index:string|null,candidate_index:string|null,candidate_columns:list<string>,rows:list<array<string,mixed>>,descending_terms:int,all_descending:bool}
     */
    private static function matchingDescendingParentKey(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns): array
    {
        $wanted = array_map('strtolower', $parentColumns);
        $permuted = [
            'index' => null,
            'columns' => [],
            'rows' => [],
            'descending' => 0,
            'all_descending' => false,
        ];

        foreach ($catalog->indexList($parent) as $index) {
            if ((int) ($index['unique'] ?? 0) !== 1 || (int) ($index['partial'] ?? 0) !== 0) {
                continue;
            }

            $indexName = (string) $index['name'];
            $keyRows = array_values(array_filter(
                $catalog->indexXInfo($indexName),
                static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1,
            ));
            if (count($keyRows) !== count($wanted)) {
                continue;
            }

            $columns = array_map(static fn (array $row): string => strtolower((string) ($row['name'] ?? '')), $keyRows);
            $descending = count(array_filter($keyRows, static fn (array $row): bool => (int) ($row['desc'] ?? 0) === 1));
            $allDescending = $descending > 0 && $descending === count($keyRows);
            if ($columns === $wanted) {
                return [
                    'status' => $descending > 0 ? 'ok_desc_parent_unique_index' : 'ok',
                    'index' => $indexName,
                    'candidate_index' => $permuted['index'],
                    'candidate_columns' => $permuted['columns'],
                    'rows' => $keyRows,
                    'descending_terms' => $descending,
                    'all_descending' => $allDescending,
                ];
            }

            $sortedColumns = $columns;
            $sortedWanted = $wanted;
            sort($sortedColumns);
            sort($sortedWanted);
            if ($descending > 0 && $sortedColumns === $sortedWanted && $permuted['index'] === null) {
                $permuted = [
                    'index' => $indexName,
                    'columns' => array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $keyRows),
                    'rows' => $keyRows,
                    'descending' => $descending,
                    'all_descending' => $allDescending,
                ];
            }
        }

        return [
            'status' => $permuted['index'] === null ? 'missing_parent_unique_index' : 'permuted_desc_parent_unique_index',
            'index' => null,
            'candidate_index' => $permuted['index'],
            'candidate_columns' => $permuted['columns'],
            'rows' => $permuted['rows'],
            'descending_terms' => $permuted['descending'],
            'all_descending' => $permuted['all_descending'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,ok:int,ok_desc_parent_unique_index:int,blocked:int,permuted_desc_parent_unique_index:int,missing_parent_unique_index:int,descending_terms:int,all_descending_rows:int,admissible:int}
     */
    private static function descendingCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'ok' => 0,
            'ok_desc_parent_unique_index' => 0,
            'blocked' => 0,
            'permuted_desc_parent_unique_index' => 0,
            'missing_parent_unique_index' => 0,
            'descending_terms' => 0,
            'all_descending_rows' => 0,
            'admissible' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'ok' || $status === 'ok_desc_parent_unique_index') {
                $counts['ok']++;
                $counts['admissible']++;
                if ($status === 'ok_desc_parent_unique_index') {
                    $counts['ok_desc_parent_unique_index']++;
                }
            } else {
                $counts['blocked']++;
                if (isset($counts[$status])) {
                    $counts[$status]++;
                }
            }
            if (($row['this_term_descending'] ?? false) === true) {
                $counts['descending_terms']++;
            }
            if (($row['all_parent_terms_descending'] ?? false) === true) {
                $counts['all_descending_rows']++;
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
                'index=' . (string) ($row['parent_unique_index'] ?? ''),
                'candidate=' . (string) ($row['candidate_index'] ?? ''),
                'desc=' . (string) ($row['index_column_desc'] ?? ''),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next238 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
