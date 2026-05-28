<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext203
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next203 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next203 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext196::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::parentCoverageRows($currentRecords, 'current');
        $nextRows = self::parentCoverageRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next203',
            'base' => $base['source_id'],
            'current_parent_coverage' => self::rowSummary($currentRows),
            'next_parent_coverage' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next203 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next203 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::coverageCounts($currentRows);
        $nextCounts = self::coverageCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next203',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_coverage_source' => 'pragma_foreign_key_list_parent_groups_plus_pragma_index_list_xinfo',
                'foreign_key_parent_coverage' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                'foreign_key_parent_coverage_source' => 'pragma_foreign_key_list_parent_groups_plus_pragma_index_list_xinfo',
                'foreign_key_parent_coverage' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_coverage' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_coverage' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_coverage_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_parent_coverage_missing' => $nextCounts['missing_parent_unique'] - $currentCounts['missing_parent_unique'],
                'foreign_key_parent_coverage_covered' => $nextCounts['covered'] - $currentCounts['covered'],
                'foreign_key_parent_coverage_repaired' => $currentCounts['missing_parent_unique'] > 0 && $nextCounts['missing_parent_unique'] === 0,
                'foreign_key_parent_coverage_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-parent-index-coverage',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function parentCoverageRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (self::groupForeignKeys(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext167::foreignKeysFromCatalog($records)) as $foreignKey) {
            $parent = (string) $foreignKey['parent'];
            $parentColumns = array_map(static fn (array $column): string => (string) $column['parent'], $foreignKey['columns']);
            $candidate = self::coveringParentIndex($catalog, $parent, $parentColumns);
            $status = $candidate === null ? 'missing_parent_unique' : 'covered';

            $rows[] = [
                'phase' => $phase,
                'kind' => 'foreign_key_parent_coverage',
                'table' => (string) $foreignKey['table'],
                'foreign_key_id' => (int) $foreignKey['id'],
                'parent' => $parent,
                'parent_columns' => $parentColumns,
                'child_columns' => array_map(static fn (array $column): string => (string) $column['child'], $foreignKey['columns']),
                'parent_index' => $candidate['name'] ?? null,
                'parent_index_origin' => $candidate['origin'] ?? null,
                'parent_index_key_columns' => $candidate['columns'] ?? [],
                'parent_index_collations' => $candidate['collations'] ?? [],
                'parent_index_partial' => $candidate['partial'] ?? null,
                'parent_index_expression_terms' => $candidate['expression_terms'] ?? null,
                'status' => $status,
                'message' => $status === 'covered'
                    ? "foreign key {$foreignKey['table']}->{$parent} parent key is covered by {$candidate['name']}"
                    : "foreign key {$foreignKey['table']}->{$parent} parent key has no usable UNIQUE index",
            ];
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['phase'], $left['table'], $left['foreign_key_id']]
                <=> [$right['phase'], $right['table'], $right['foreign_key_id']],
        );

        return $rows;
    }

    /**
     * @param list<string> $parentColumns
     * @return array{name:string,origin:string,columns:list<string>,collations:list<string>,partial:int,expression_terms:int}|null
     */
    private static function coveringParentIndex(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns): ?array
    {
        foreach ($catalog->indexList($parent) as $index) {
            if ((int) $index['unique'] !== 1 || (int) $index['partial'] !== 0) {
                continue;
            }

            $xinfo = $catalog->indexXInfo((string) $index['name']);
            $keyRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1));
            $columns = array_map(static fn (array $row): ?string => isset($row['name']) ? (string) $row['name'] : null, $keyRows);
            if (!self::sameColumns($columns, $parentColumns)) {
                continue;
            }

            $expressionTerms = count(array_filter($keyRows, static fn (array $row): bool => ($row['name'] ?? null) === null || (int) ($row['cid'] ?? 0) === -2));
            if ($expressionTerms > 0) {
                continue;
            }

            return [
                'name' => (string) $index['name'],
                'origin' => (string) $index['origin'],
                'columns' => array_map(static fn (?string $column): string => (string) $column, $columns),
                'collations' => array_map(static fn (array $row): string => strtoupper((string) ($row['coll'] ?? 'BINARY')), $keyRows),
                'partial' => (int) $index['partial'],
                'expression_terms' => $expressionTerms,
            ];
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $foreignKeys
     * @return list<array<string,mixed>>
     */
    private static function groupForeignKeys(array $foreignKeys): array
    {
        $grouped = [];
        foreach ($foreignKeys as $foreignKey) {
            $key = strtolower((string) $foreignKey['table']) . '#' . (int) $foreignKey['id'];
            $grouped[$key] ??= [
                ...$foreignKey,
                'columns' => [],
            ];
            foreach ((array) ($foreignKey['columns'] ?? []) as $column) {
                if (!is_array($column)) {
                    continue;
                }
                $grouped[$key]['columns'][] = [
                    'child' => (string) ($column['child'] ?? ''),
                    'parent' => (string) ($column['parent'] ?? ''),
                ];
            }
        }

        foreach ($grouped as $foreignKey) {
            if ($foreignKey['columns'] === []) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next203 requires foreign key columns');
            }
        }

        return array_values($grouped);
    }

    /**
     * @param list<string|null> $left
     * @param list<string> $right
     */
    private static function sameColumns(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            return false;
        }
        foreach ($left as $index => $column) {
            if ($column === null || strcasecmp($column, $right[$index]) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,covered:int,missing_parent_unique:int,autoindex:int,created_index:int}
     */
    private static function coverageCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'covered' => 0,
            'missing_parent_unique' => 0,
            'autoindex' => 0,
            'created_index' => 0,
        ];
        foreach ($rows as $row) {
            if (($row['status'] ?? null) === 'covered') {
                $counts['covered']++;
            }
            if (($row['status'] ?? null) === 'missing_parent_unique') {
                $counts['missing_parent_unique']++;
            }
            if (($row['parent_index_origin'] ?? null) === 'u') {
                $counts['autoindex']++;
            }
            if (($row['parent_index_origin'] ?? null) === 'c') {
                $counts['created_index']++;
            }
        }

        return $counts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function rowSummary(array $rows, bool $includeStatus = true): array
    {
        $summary = array_map(
            static fn (array $row): string => implode(':', array_filter([
                (string) $row['phase'],
                (string) $row['table'] . '#' . (int) $row['foreign_key_id'] . '->' . (string) $row['parent'],
                implode(',', (array) $row['parent_columns']),
                (string) ($row['parent_index'] ?? 'missing'),
                $includeStatus ? (string) $row['status'] : null,
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next203 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
