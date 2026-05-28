<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext207
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next207 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next207 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext206::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::childKeyIndexRows($currentRecords, 'current');
        $nextRows = self::childKeyIndexRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next207',
            'base' => $base['source_id'],
            'current_child_key_indexes' => self::rowSummary($currentRows),
            'next_child_key_indexes' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next207 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next207 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::coverageCounts($currentRows);
        $nextCounts = self::coverageCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next207',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_child_index_source' => 'pragma_foreign_key_list_child_groups_plus_pragma_index_xinfo_prefix',
                'foreign_key_child_indexes' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_child_index_source' => 'pragma_foreign_key_list_child_groups_plus_pragma_index_xinfo_prefix',
                'foreign_key_child_indexes' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_child_indexes' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_child_indexes' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_child_index_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_child_index_covered' => $nextCounts['covered'] - $currentCounts['covered'],
                'foreign_key_child_index_missing' => $nextCounts['missing_child_index'] - $currentCounts['missing_child_index'],
                'foreign_key_child_index_repaired' => $currentCounts['missing_child_index'] > 0 && $nextCounts['missing_child_index'] === 0,
                'foreign_key_child_index_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-child-index-prefix-coverage',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function childKeyIndexRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (self::groupForeignKeys(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext159::foreignKeysFromCatalog($records)) as $foreignKey) {
            $childColumns = array_map(static fn (array $column): string => (string) $column['child'], $foreignKey['columns']);
            $candidate = self::childIndexPrefix($catalog, (string) $foreignKey['table'], $childColumns);
            $status = $candidate === null ? 'missing_child_index' : 'covered';

            $rows[] = [
                'phase' => $phase,
                'kind' => 'foreign_key_child_index',
                'table' => (string) $foreignKey['table'],
                'foreign_key_id' => (int) $foreignKey['id'],
                'parent' => (string) $foreignKey['parent'],
                'child_columns' => $childColumns,
                'parent_columns' => array_map(static fn (array $column): string => (string) $column['parent'], $foreignKey['columns']),
                'child_index' => $candidate['name'] ?? null,
                'child_index_origin' => $candidate['origin'] ?? null,
                'child_index_unique' => $candidate['unique'] ?? null,
                'child_index_partial' => $candidate['partial'] ?? null,
                'child_index_key_columns' => $candidate['columns'] ?? [],
                'child_index_collations' => $candidate['collations'] ?? [],
                'status' => $status,
                'message' => $status === 'covered'
                    ? "foreign key {$foreignKey['table']}->{$foreignKey['parent']} child key is indexed by {$candidate['name']}"
                    : "foreign key {$foreignKey['table']}->{$foreignKey['parent']} child key has no non-partial index prefix",
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
     * @return array{name:string,origin:string,unique:int,partial:int,columns:list<string>,collations:list<string>}|null
     */
    private static function childIndexPrefix(SQLitePragmaSchemaCatalog $catalog, string $table, array $childColumns): ?array
    {
        foreach ($catalog->indexList($table) as $index) {
            if ((int) ($index['partial'] ?? 0) !== 0) {
                continue;
            }

            $keyRows = array_values(array_filter(
                $catalog->indexXInfo((string) $index['name']),
                static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1
            ));
            if (count($keyRows) < count($childColumns)) {
                continue;
            }

            $prefixRows = array_slice($keyRows, 0, count($childColumns));
            $prefixColumns = array_map(static fn (array $row): ?string => isset($row['name']) ? (string) $row['name'] : null, $prefixRows);
            if (!self::sameColumns($prefixColumns, $childColumns)) {
                continue;
            }

            return [
                'name' => (string) $index['name'],
                'origin' => (string) $index['origin'],
                'unique' => (int) $index['unique'],
                'partial' => (int) $index['partial'],
                'columns' => array_map(static fn (?string $column): string => (string) $column, $prefixColumns),
                'collations' => array_map(static fn (array $row): string => strtoupper((string) ($row['coll'] ?? 'BINARY')), $prefixRows),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next207 requires foreign key columns');
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
     * @return array{rows:int,covered:int,missing_child_index:int,single_column:int,composite:int,created_index:int,autoindex:int}
     */
    private static function coverageCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'covered' => 0,
            'missing_child_index' => 0,
            'single_column' => 0,
            'composite' => 0,
            'created_index' => 0,
            'autoindex' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
            if (count((array) ($row['child_columns'] ?? [])) === 1) {
                $counts['single_column']++;
            } else {
                $counts['composite']++;
            }
            if (($row['child_index_origin'] ?? null) === 'c') {
                $counts['created_index']++;
            }
            if (($row['child_index_origin'] ?? null) === 'u') {
                $counts['autoindex']++;
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next207 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
