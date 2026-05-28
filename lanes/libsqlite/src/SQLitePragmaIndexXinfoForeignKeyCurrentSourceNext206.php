<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext206
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next206 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next206 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext203::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::rowidAliasParentRows($currentRecords, 'current');
        $nextRows = self::rowidAliasParentRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next206',
            'base' => $base['source_id'],
            'current_rowid_parent_keys' => self::rowSummary($currentRows),
            'next_rowid_parent_keys' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next206 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next206 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::rowidAliasCounts($currentRows);
        $nextCounts = self::rowidAliasCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next206',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_rowid_alias_source' => 'pragma_foreign_key_list_parent_groups_plus_table_info_integer_primary_key',
                'foreign_key_parent_rowid_alias' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_parent_rowid_alias_source' => 'pragma_foreign_key_list_parent_groups_plus_table_info_integer_primary_key',
                'foreign_key_parent_rowid_alias' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_rowid_alias' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_rowid_alias' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_rowid_alias_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_parent_rowid_alias_covered' => $nextCounts['rowid_alias_parent_key'] - $currentCounts['rowid_alias_parent_key'],
                'foreign_key_parent_rowid_alias_missing' => $nextCounts['missing_parent_key'] - $currentCounts['missing_parent_key'],
                'foreign_key_parent_rowid_alias_repaired' => $currentCounts['missing_parent_key'] > 0 && $nextCounts['missing_parent_key'] === 0,
                'foreign_key_parent_rowid_alias_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-parent-rowid-alias-coverage',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function rowidAliasParentRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (self::groupForeignKeys(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext167::foreignKeysFromCatalog($records)) as $foreignKey) {
            $parent = (string) $foreignKey['parent'];
            $parentColumns = array_map(static fn (array $column): string => (string) $column['parent'], $foreignKey['columns']);
            $rowidAlias = self::rowidAliasColumn($catalog, $parent);
            $matches = count($parentColumns) === 1 && $rowidAlias !== null && strcasecmp($parentColumns[0], $rowidAlias['name']) === 0;

            $rows[] = [
                'phase' => $phase,
                'kind' => 'foreign_key_parent_rowid_alias',
                'table' => (string) $foreignKey['table'],
                'foreign_key_id' => (int) $foreignKey['id'],
                'parent' => $parent,
                'parent_columns' => $parentColumns,
                'child_columns' => array_map(static fn (array $column): string => (string) $column['child'], $foreignKey['columns']),
                'rowid_alias_column' => $rowidAlias['name'] ?? null,
                'rowid_alias_type' => $rowidAlias['type'] ?? null,
                'rowid_alias_pk' => $rowidAlias['pk'] ?? null,
                'status' => $matches ? 'rowid_alias_parent_key' : 'missing_parent_key',
                'message' => $matches
                    ? "foreign key {$foreignKey['table']}->{$parent} parent key is covered by INTEGER PRIMARY KEY rowid alias {$rowidAlias['name']}"
                    : "foreign key {$foreignKey['table']}->{$parent} parent key is not an INTEGER PRIMARY KEY rowid alias",
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
     * @return array{name:string,type:string,pk:int}|null
     */
    private static function rowidAliasColumn(SQLitePragmaSchemaCatalog $catalog, string $table): ?array
    {
        foreach ($catalog->tableInfo($table) as $row) {
            if ((int) $row['pk'] === 1 && strtoupper(trim((string) $row['type'])) === 'INTEGER') {
                return [
                    'name' => (string) $row['name'],
                    'type' => (string) $row['type'],
                    'pk' => (int) $row['pk'],
                ];
            }
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next206 requires foreign key columns');
            }
        }

        return array_values($grouped);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,rowid_alias_parent_key:int,missing_parent_key:int,single_column:int,composite:int}
     */
    private static function rowidAliasCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'rowid_alias_parent_key' => 0,
            'missing_parent_key' => 0,
            'single_column' => 0,
            'composite' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
            if (count((array) ($row['parent_columns'] ?? [])) === 1) {
                $counts['single_column']++;
            } else {
                $counts['composite']++;
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
            static fn (array $row): string => ($includePhase ? $row['phase'] . ':' : '')
                . $row['table'] . '#' . $row['foreign_key_id'] . '->' . $row['parent']
                . ':' . implode(',', (array) $row['parent_columns'])
                . ':' . (($row['rowid_alias_column'] ?? null) === null ? 'no-rowid-alias' : 'rowid-alias=' . $row['rowid_alias_column'])
                . ':' . $row['status'],
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next206 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
