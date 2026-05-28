<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext236
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next236 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next236 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext231::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::parentQuotedCaseRows($currentRecords, 'current');
        $nextRows = self::parentQuotedCaseRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next236',
            'base' => $base['source_id'],
            'current_parent_quoted_case' => self::rowSummary($currentRows),
            'next_parent_quoted_case' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next236 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next236 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::quotedCaseCounts($currentRows);
        $nextCounts = self::quotedCaseCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next236',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_quoted_case_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_casefold',
                'foreign_key_parent_quoted_case' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_parent_quoted_case_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_casefold',
                'foreign_key_parent_quoted_case' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_quoted_case' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_quoted_case' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_quoted_case_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_parent_quoted_case_exact_name_delta' => $nextCounts['exact_name_match'] - $currentCounts['exact_name_match'],
                'foreign_key_parent_quoted_case_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
                'foreign_key_parent_quoted_case_repaired' => $currentCounts['casefold_only'] > 0 && $nextCounts['casefold_only'] === 0,
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-parent-quoted-casefold',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function parentQuotedCaseRows(array $records, string $phase = 'current'): array
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
                $indexColumn = $indexRow['name'] ?? null;
                $to = (string) $row['to'];
                $exact = is_string($indexColumn) && $indexColumn === $to;
                $casefold = is_string($indexColumn) && strcasecmp($indexColumn, $to) === 0;
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_parent_quoted_case',
                    'table' => (string) $row['table'],
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => $seq,
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => $to,
                    'parent_columns' => $parentColumns,
                    'parent_unique_index' => $candidate['index'],
                    'index_key_columns' => $candidate['index_columns'],
                    'index_column' => $indexColumn,
                    'exact_name_match' => $exact,
                    'casefold_name_match' => $casefold,
                    'status' => $candidate['status'] === 'ok'
                        ? ($exact ? 'exact_name_match' : 'casefold_name_match')
                        : $candidate['status'],
                    'message' => $candidate['status'] === 'ok'
                        ? ($exact
                            ? "foreign key {$row['table']}->{$parent} parent key matches quoted index_xinfo names exactly"
                            : "foreign key {$row['table']}->{$parent} parent key matches quoted index_xinfo names by SQLite case-folding")
                        : "foreign key {$row['table']}->{$parent} has no case-folded UNIQUE parent key",
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
     * @return array{status:string,index:string|null,rows:list<array<string,mixed>>,index_columns:list<string>}
     */
    private static function matchingUniqueParentIndex(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns): array
    {
        $wanted = array_map('strtolower', $parentColumns);
        $primaryKey = self::primaryKeyColumns($catalog, $parent);
        if (array_map('strtolower', $primaryKey) === $wanted) {
            return [
                'status' => 'ok',
                'index' => 'sqlite_primary_key',
                'rows' => array_map(
                    static fn (string $column, int $seqno): array => ['seqno' => $seqno, 'name' => $column, 'key' => 1],
                    $primaryKey,
                    array_keys($primaryKey),
                ),
                'index_columns' => $primaryKey,
            ];
        }

        foreach ($catalog->indexList($parent) as $index) {
            if ((int) ($index['unique'] ?? 0) !== 1 || (int) ($index['partial'] ?? 0) !== 0) {
                continue;
            }

            $indexName = (string) $index['name'];
            $keyRows = array_values(array_filter(
                $catalog->indexXInfo($indexName),
                static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1,
            ));
            $columns = array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $keyRows);
            if (array_map('strtolower', $columns) === $wanted && !in_array('', $columns, true)) {
                return [
                    'status' => 'ok',
                    'index' => $indexName,
                    'rows' => $keyRows,
                    'index_columns' => $columns,
                ];
            }
        }

        return [
            'status' => 'missing_parent_unique_index',
            'index' => null,
            'rows' => [],
            'index_columns' => [],
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
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,exact_name_match:int,casefold_only:int,missing_parent_unique_index:int,parent_columns:int}
     */
    private static function quotedCaseCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'exact_name_match' => 0,
            'casefold_only' => 0,
            'missing_parent_unique_index' => 0,
            'parent_columns' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'exact_name_match') {
                $counts['exact_name_match']++;
            } elseif ($status === 'casefold_name_match') {
                $counts['casefold_only']++;
            } elseif ($status === 'missing_parent_unique_index') {
                $counts['missing_parent_unique_index']++;
            }
            $counts['parent_columns'] += count((array) ($row['parent_columns'] ?? []));
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
                'parent=' . implode(',', (array) ($row['parent_columns'] ?? [])),
                'index=' . implode(',', (array) ($row['index_key_columns'] ?? [])),
                'column=' . (string) ($row['index_column'] ?? ''),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next236 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
