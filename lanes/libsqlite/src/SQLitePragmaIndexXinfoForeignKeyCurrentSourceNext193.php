<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext193
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
        int $limit = 193,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next193 offset must be non-negative');
        }
        if ($limit <= 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next193 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext189::currentNextPageFromCatalog(
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

        $currentRows = self::parentUniqueOrderRows($currentRecords, 'current');
        $nextRows = self::parentUniqueOrderRows($nextRecords, 'next');
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next193',
            'base' => $base['source_id'],
            'current_parent_unique_order' => self::rowSummary($currentRows),
            'next_parent_unique_order' => self::rowSummary($nextRows),
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $orderRows = array_values(array_merge($currentRows, $nextRows));
        $allRows = array_values(array_merge(
            array_map(static fn (array $row): array => self::decorateParentKeyRow($row, $orderRows), $base['rows']),
            $orderRows,
        ));
        $total = count($allRows);
        $rows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($rows);
        $complete = $nextOffset >= $total;
        $currentCounts = self::orderCounts($currentRows);
        $nextCounts = self::orderCounts($nextRows);
        $blocking = array_values(array_unique([
            ...($base['next_state']['blocking'] ?? []),
            ...($nextCounts['order_mismatch'] > 0 ? ['foreign_key_parent_unique_column_order'] : []),
        ]));

        return [
            ...$base,
            'source_id' => $sourceId,
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($rows),
            'total' => $total,
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_unique_order_source' => 'pragma_index_xinfo_parent_unique_column_order',
                'foreign_key_parent_unique_order' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...$base['next_source'],
                'foreign_key_parent_unique_order_source' => 'pragma_index_xinfo_parent_unique_column_order',
                'foreign_key_parent_unique_order' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_unique_order_rows' => count($currentRows),
                'foreign_key_parent_unique_order' => $currentCounts,
                'total_blockers' => (int) ($base['current']['total_blockers'] ?? 0) + $currentCounts['order_mismatch'],
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_unique_order_rows' => count($nextRows),
                'foreign_key_parent_unique_order' => $nextCounts,
                'total_blockers' => (int) ($base['next_counts']['total_blockers'] ?? 0) + $nextCounts['order_mismatch'],
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_unique_order_rows' => count($nextRows) - count($currentRows),
                'foreign_key_parent_unique_order_mismatches' => $nextCounts['order_mismatch'] - $currentCounts['order_mismatch'],
                'foreign_key_parent_unique_order_repaired' => $currentCounts['order_mismatch'] > 0 && $nextCounts['order_mismatch'] === 0,
                'foreign_key_parent_unique_order_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
                'total_blockers' => ((int) ($base['delta']['total_blockers'] ?? 0)) + $nextCounts['order_mismatch'] - $currentCounts['order_mismatch'],
                'cleared' => (($base['delta']['cleared'] ?? false) === true) && $nextCounts['order_mismatch'] === 0,
            ],
            'next_state' => [
                'ready' => $blocking === [],
                'blocking' => $blocking,
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
     * @return list<array<string,mixed>>
     */
    public static function parentUniqueOrderRows(array $records, string $side = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (self::groupForeignKeys(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext167::foreignKeysFromCatalog($records)) as $foreignKey) {
            $parent = (string) $foreignKey['parent'];
            $parentColumns = array_map(static fn (array $column): string => (string) $column['parent'], $foreignKey['columns']);
            foreach ($catalog->execute('PRAGMA index_list(' . self::pragmaArgumentLiteral($parent) . ')')['rows'] as $index) {
                if ((int) ($index['unique'] ?? 0) !== 1 || (int) ($index['partial'] ?? 0) === 1) {
                    continue;
                }

                $indexName = (string) $index['name'];
                $xinfo = $catalog->execute('PRAGMA index_xinfo(' . self::pragmaArgumentLiteral($indexName) . ')')['rows'];
                $keyRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1));
                if (self::expressionKeyCount($keyRows) > 0) {
                    continue;
                }

                $keyColumns = array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $keyRows);
                if (self::sameColumns($keyColumns, $parentColumns) || !self::sameColumnSet($keyColumns, $parentColumns)) {
                    continue;
                }

                $rows[] = [
                    'side' => $side,
                    'kind' => 'foreign_key_parent_unique_order',
                    'table' => (string) $foreignKey['table'],
                    'fkid' => (int) $foreignKey['id'],
                    'parent' => $parent,
                    'index' => $indexName,
                    'parent_columns' => $parentColumns,
                    'index_key_columns' => $keyColumns,
                    'index_unique' => 1,
                    'index_partial' => 0,
                    'index_expression_keys' => 0,
                    'index_key_count' => count($keyRows),
                    'status' => 'order_mismatch',
                    'message' => "foreign key {$foreignKey['table']}->{$parent} cannot use UNIQUE index {$indexName} because parent key columns are in a different order",
                ];
            }
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['side'], $left['table'], $left['fkid'], $left['index']]
                <=> [$right['side'], $right['table'], $right['fkid'], $right['index']],
        );

        return $rows;
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
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next193 requires foreign key columns');
            }
        }

        return array_values($grouped);
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private static function sameColumns(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            return false;
        }
        foreach ($left as $index => $column) {
            if (strcasecmp($column, $right[$index]) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private static function sameColumnSet(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            return false;
        }
        $left = array_map('strtolower', $left);
        $right = array_map('strtolower', $right);
        sort($left);
        sort($right);

        return $left === $right;
    }

    /**
     * @param list<array<string,mixed>> $keyRows
     */
    private static function expressionKeyCount(array $keyRows): int
    {
        return count(array_filter(
            $keyRows,
            static fn (array $row): bool => (int) ($row['cid'] ?? 0) === -2 || ($row['name'] ?? null) === null || (string) ($row['name'] ?? '') === ''
        ));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,order_mismatch:int}
     */
    private static function orderCounts(array $rows): array
    {
        return [
            'rows' => count($rows),
            'order_mismatch' => count(array_filter($rows, static fn (array $row): bool => ($row['status'] ?? null) === 'order_mismatch')),
        ];
    }

    /**
     * @param list<array<string,mixed>> $orderRows
     */
    private static function decorateParentKeyRow(array $row, array $orderRows): array
    {
        if (($row['kind'] ?? null) !== 'foreign_key_parent_key' || ($row['status'] ?? null) !== 'missing_parent_key') {
            return $row;
        }

        foreach ($orderRows as $orderRow) {
            if (
                ($row['side'] ?? null) === ($orderRow['side'] ?? null)
                && ($row['table'] ?? null) === ($orderRow['table'] ?? null)
                && (int) ($row['fkid'] ?? -1) === (int) ($orderRow['fkid'] ?? -2)
            ) {
                return [
                    ...$row,
                    'rejected_parent_unique_index' => $orderRow['index'],
                    'rejected_parent_unique_reason' => 'column_order_mismatch',
                ];
            }
        }

        return $row;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function rowSummary(array $rows, bool $includeSide = true): array
    {
        $summary = array_map(
            static fn (array $row): string => ($includeSide ? $row['side'] . ':' : '')
                . $row['table'] . '#' . $row['fkid'] . '->' . $row['parent']
                . ':' . $row['index'] . ':column_order_mismatch',
            $rows,
        );
        sort($summary);

        return $summary;
    }

    /** @param list<mixed> $records */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next193 records must be SQLiteSchemaRecord instances');
            }
        }
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next193 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next193 cursor offset does not match the requested page offset');
        }
    }

    private static function pragmaArgumentLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
