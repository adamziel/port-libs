<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext185
{
    /**
     * @param list<SQLiteSchemaRecord> $currentRecords
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param list<SQLiteSchemaRecord> $nextRecords
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array<string,mixed>
     */
    public static function currentNextPageFromCatalog(
        array $currentRecords,
        array $currentTables,
        array $nextRecords,
        array $nextTables,
        string $indexXinfoSql,
        int $offset = 0,
        int $limit = 185,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next185 offset must be non-negative');
        }
        if ($limit <= 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next185 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext182::currentNextPageFromCatalog(
            $currentRecords,
            $currentTables,
            $nextRecords,
            $nextTables,
            $indexXinfoSql,
            0,
            PHP_INT_MAX,
            null,
            $tableValuedIndexXinfo,
        );

        $currentRows = self::nullChildKeyRows($currentRecords, $currentTables, 'current');
        $nextRows = self::nullChildKeyRows($nextRecords, $nextTables, 'next');
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next185',
            'base' => $base['source_id'],
            'current_null_child_keys' => self::rowSummary($currentRows),
            'next_null_child_keys' => self::rowSummary($nextRows),
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $total = count($allRows);
        $rows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($rows);
        $complete = $nextOffset >= $total;
        $currentCounts = self::nullChildKeyCounts($currentRows);
        $nextCounts = self::nullChildKeyCounts($nextRows);

        return [
            ...$base,
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($rows),
            'total' => $total,
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_null_child_source' => 'pragma_foreign_key_check_null_child_key_exemption',
                'foreign_key_null_child_keys' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...$base['next_source'],
                'foreign_key_null_child_source' => 'pragma_foreign_key_check_null_child_key_exemption',
                'foreign_key_null_child_keys' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_null_child_keys' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_null_child_keys' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_null_child_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_null_child_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
                'foreign_key_null_child_current_only' => count(array_diff(self::rowSummary($currentRows, false), self::rowSummary($nextRows, false))),
                'foreign_key_null_child_next_only' => count(array_diff(self::rowSummary($nextRows, false), self::rowSummary($currentRows, false))),
            ],
            'next' => $complete ? null : [
                'source_id' => $sourceId,
                'offset' => $nextOffset,
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<array<string,mixed>>
     */
    public static function nullChildKeyRows(array $records, array $tables, string $side = 'current'): array
    {
        self::validateRecords($records);

        $rows = [];
        foreach (SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext167::foreignKeysFromCatalog($records) as $foreignKey) {
            $table = (string) $foreignKey['table'];
            $childRows = self::tableRows($tables, $table);
            $columns = self::columns($foreignKey);
            foreach ($childRows as $ordinal => $row) {
                $nullColumns = [];
                $childValues = [];
                foreach ($columns as $column) {
                    $childColumn = $column['child'];
                    if (!array_key_exists($childColumn, $row)) {
                        throw new InvalidArgumentException("SQLite PRAGMA index_xinfo/FK current-source next185 child row is missing column {$childColumn}");
                    }
                    $childValues[$childColumn] = $row[$childColumn];
                    if ($row[$childColumn] === null) {
                        $nullColumns[] = $childColumn;
                    }
                }
                if ($nullColumns === []) {
                    continue;
                }

                $rows[] = [
                    'side' => $side,
                    'kind' => 'foreign_key_null_child_key',
                    'table' => $table,
                    'rowid' => self::rowid($row, (bool) ($foreignKey['without_rowid'] ?? false), $ordinal),
                    'fkid' => (int) $foreignKey['id'],
                    'parent' => (string) $foreignKey['parent'],
                    'child_columns' => array_column($columns, 'child'),
                    'parent_columns' => array_column($columns, 'parent'),
                    'null_child_columns' => $nullColumns,
                    'child_values' => $childValues,
                    'status' => 'not_checked',
                    'message' => "foreign key {$table}->{$foreignKey['parent']} row is omitted from PRAGMA foreign_key_check because a child key column is NULL",
                ];
            }
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['side'], $left['table'], $left['fkid'], (string) $left['rowid']]
                <=> [$right['side'], $right['table'], $right['fkid'], (string) $right['rowid']],
        );

        return $rows;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next185 records must be SQLiteSchemaRecord instances');
            }
        }
    }

    /**
     * @param array<string,mixed> $foreignKey
     * @return list<array{child:string,parent:string}>
     */
    private static function columns(array $foreignKey): array
    {
        $columns = $foreignKey['columns'] ?? null;
        if (!is_array($columns) || $columns === []) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next185 requires foreign key columns');
        }

        return array_map(
            static fn (array $column): array => [
                'child' => (string) $column['child'],
                'parent' => (string) $column['parent'],
            ],
            $columns,
        );
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<array<string,mixed>>
     */
    private static function tableRows(array $tables, string $table): array
    {
        foreach ($tables as $name => $rows) {
            if (strcasecmp((string) $name, $table) === 0) {
                return $rows;
            }
        }

        return [];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowid(array $row, bool $withoutRowid, int $fallback): int|string|null
    {
        if ($withoutRowid) {
            return null;
        }

        foreach (['rowid', '_rowid_', 'oid'] as $column) {
            if (array_key_exists($column, $row) && (is_int($row[$column]) || is_string($row[$column]))) {
                return $row[$column];
            }
        }

        return $fallback + 1;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,composite:int,partial_null:int,full_null:int,rowid_tables:int,without_rowid_tables:int}
     */
    private static function nullChildKeyCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'composite' => 0,
            'partial_null' => 0,
            'full_null' => 0,
            'rowid_tables' => 0,
            'without_rowid_tables' => 0,
        ];
        foreach ($rows as $row) {
            $childColumns = $row['child_columns'] ?? [];
            $nullColumns = $row['null_child_columns'] ?? [];
            if (count($childColumns) > 1) {
                $counts['composite']++;
            }
            if (count($nullColumns) === count($childColumns)) {
                $counts['full_null']++;
            } else {
                $counts['partial_null']++;
            }
            if (($row['rowid'] ?? null) === null) {
                $counts['without_rowid_tables']++;
            } else {
                $counts['rowid_tables']++;
            }
        }

        return $counts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function rowSummary(array $rows, bool $includeSide = true): array
    {
        $summary = array_map(
            static fn (array $row): string => ($includeSide ? $row['side'] . ':' : '')
                . $row['table'] . '#' . $row['fkid'] . '@' . ($row['rowid'] ?? 'without-rowid')
                . '->' . $row['parent'] . ':null=' . implode('|', $row['null_child_columns']),
            $rows,
        );
        sort($summary);

        return $summary;
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next185 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next185 cursor offset does not match the requested page offset');
        }
    }

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
