<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext204
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next204 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next204 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext203::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::childIndexRows($currentRecords, 'current');
        $nextRows = self::childIndexRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next204',
            'base' => $base['source_id'],
            'current_child_index' => self::rowSummary($currentRows),
            'next_child_index' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next204 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next204 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::childIndexCounts($currentRows);
        $nextCounts = self::childIndexCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next204',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_child_index_source' => 'pragma_foreign_key_list_child_groups_plus_pragma_index_list_xinfo',
                'foreign_key_child_index' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_child_index_source' => 'pragma_foreign_key_list_child_groups_plus_pragma_index_list_xinfo',
                'foreign_key_child_index' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_child_index' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_child_index' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_child_index_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_child_index_missing' => $nextCounts['missing_child_index'] - $currentCounts['missing_child_index'],
                'foreign_key_child_index_covered' => $nextCounts['covered'] - $currentCounts['covered'],
                'foreign_key_child_index_repaired' => $currentCounts['missing_child_index'] > 0 && $nextCounts['missing_child_index'] === 0,
                'foreign_key_child_index_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-child-index-coverage',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function childIndexRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (self::groupForeignKeys(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext167::foreignKeysFromCatalog($records)) as $foreignKey) {
            $childColumns = array_map(static fn (array $column): string => (string) $column['child'], $foreignKey['columns']);
            $candidate = self::coveringChildIndex($catalog, (string) $foreignKey['table'], $childColumns);
            $status = $candidate === null ? 'missing_child_index' : 'covered';

            $rows[] = [
                'phase' => $phase,
                'kind' => 'foreign_key_child_index',
                'table' => (string) $foreignKey['table'],
                'foreign_key_id' => (int) $foreignKey['id'],
                'parent' => (string) $foreignKey['parent'],
                'parent_columns' => array_map(static fn (array $column): string => (string) $column['parent'], $foreignKey['columns']),
                'child_columns' => $childColumns,
                'child_index' => $candidate['name'] ?? null,
                'child_index_unique' => $candidate['unique'] ?? null,
                'child_index_origin' => $candidate['origin'] ?? null,
                'child_index_key_columns' => $candidate['columns'] ?? [],
                'child_index_prefix_columns' => $candidate['prefix_columns'] ?? [],
                'child_index_collations' => $candidate['collations'] ?? [],
                'child_index_desc' => $candidate['desc'] ?? [],
                'child_index_partial' => $candidate['partial'] ?? null,
                'child_index_expression_terms' => $candidate['expression_terms'] ?? null,
                'status' => $status,
                'message' => $status === 'covered'
                    ? "foreign key {$foreignKey['table']} child key is searchable by {$candidate['name']}"
                    : "foreign key {$foreignKey['table']} child key has no usable child-side index prefix",
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
     * @param list<string> $childColumns
     * @return array{name:string,unique:int,origin:string,columns:list<string>,prefix_columns:list<string>,collations:list<string>,desc:list<int>,partial:int,expression_terms:int}|null
     */
    private static function coveringChildIndex(SQLitePragmaSchemaCatalog $catalog, string $table, array $childColumns): ?array
    {
        foreach ($catalog->indexList($table) as $index) {
            if ((int) $index['partial'] !== 0) {
                continue;
            }

            $xinfo = $catalog->indexXInfo((string) $index['name']);
            $keyRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1));
            if (count($keyRows) < count($childColumns)) {
                continue;
            }

            $prefixRows = array_slice($keyRows, 0, count($childColumns));
            $prefixColumns = array_map(static fn (array $row): ?string => isset($row['name']) ? (string) $row['name'] : null, $prefixRows);
            if (!self::sameColumns($prefixColumns, $childColumns)) {
                continue;
            }

            $expressionTerms = count(array_filter($prefixRows, static fn (array $row): bool => ($row['name'] ?? null) === null || (int) ($row['cid'] ?? 0) === -2));
            if ($expressionTerms > 0) {
                continue;
            }

            return [
                'name' => (string) $index['name'],
                'unique' => (int) $index['unique'],
                'origin' => (string) $index['origin'],
                'columns' => array_map(
                    static fn (array $row): string => (string) ($row['name'] ?? ''),
                    $keyRows,
                ),
                'prefix_columns' => array_map(static fn (?string $column): string => (string) $column, $prefixColumns),
                'collations' => array_map(static fn (array $row): string => strtoupper((string) ($row['coll'] ?? 'BINARY')), $prefixRows),
                'desc' => array_map(static fn (array $row): int => (int) ($row['desc'] ?? 0), $prefixRows),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next204 requires foreign key columns');
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
     * @return array{rows:int,covered:int,missing_child_index:int,non_unique:int,unique:int,partial:int,descending_prefix:int}
     */
    private static function childIndexCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'covered' => 0,
            'missing_child_index' => 0,
            'non_unique' => 0,
            'unique' => 0,
            'partial' => 0,
            'descending_prefix' => 0,
        ];
        foreach ($rows as $row) {
            if (($row['status'] ?? null) === 'covered') {
                $counts['covered']++;
            }
            if (($row['status'] ?? null) === 'missing_child_index') {
                $counts['missing_child_index']++;
            }
            if (($row['child_index_unique'] ?? null) === 1) {
                $counts['unique']++;
            }
            if (($row['child_index_unique'] ?? null) === 0) {
                $counts['non_unique']++;
            }
            if (($row['child_index_partial'] ?? null) === 1) {
                $counts['partial']++;
            }
            if (in_array(1, (array) ($row['child_index_desc'] ?? []), true)) {
                $counts['descending_prefix']++;
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
                implode(',', (array) $row['child_columns']),
                (string) ($row['child_index'] ?? 'missing'),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next204 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
