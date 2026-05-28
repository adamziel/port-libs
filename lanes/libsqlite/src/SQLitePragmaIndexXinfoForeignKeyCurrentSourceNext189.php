<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext189
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
        int $limit = 189,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next189 offset must be non-negative');
        }
        if ($limit <= 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next189 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext186::currentNextPageFromCatalog(
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

        $currentRows = self::rejectedParentUniqueIndexRows($currentRecords, 'current');
        $nextRows = self::rejectedParentUniqueIndexRows($nextRecords, 'next');
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next189',
            'base' => $base['source_id'],
            'current_rejected_parent_unique' => self::rowSummary($currentRows),
            'next_rejected_parent_unique' => self::rowSummary($nextRows),
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $rejectedRows = array_values(array_merge($currentRows, $nextRows));
        $allRows = array_values(array_merge(
            array_map(static fn (array $row): array => self::decorateParentKeyRow($row, $rejectedRows), $base['rows']),
            $rejectedRows,
        ));
        $total = count($allRows);
        $rows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($rows);
        $complete = $nextOffset >= $total;
        $currentCounts = self::rejectionCounts($currentRows);
        $nextCounts = self::rejectionCounts($nextRows);
        $blocking = self::blocking($base['next_state']['blocking'] ?? [], $nextCounts);

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
                'foreign_key_rejected_parent_unique_source' => 'pragma_index_list_partial_and_pragma_index_xinfo_expression_keys',
                'foreign_key_rejected_parent_unique' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...$base['next_source'],
                'foreign_key_rejected_parent_unique_source' => 'pragma_index_list_partial_and_pragma_index_xinfo_expression_keys',
                'foreign_key_rejected_parent_unique' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_rejected_parent_unique_indexes' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_rejected_parent_unique_indexes' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_rejected_parent_unique_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_rejected_partial_unique_rows' => $nextCounts['partial_unique'] - $currentCounts['partial_unique'],
                'foreign_key_rejected_expression_unique_rows' => $nextCounts['expression_unique'] - $currentCounts['expression_unique'],
                'foreign_key_rejected_parent_unique_cleared' => $currentCounts['rows'] > 0 && $nextCounts['rows'] === 0,
                'foreign_key_rejected_parent_unique_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
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
    public static function rejectedParentUniqueIndexRows(array $records, string $side = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (self::groupForeignKeys(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext167::foreignKeysFromCatalog($records)) as $foreignKey) {
            $parent = (string) $foreignKey['parent'];
            $parentColumns = array_map(static fn (array $column): string => (string) $column['parent'], $foreignKey['columns']);
            foreach ($catalog->execute('PRAGMA index_list(' . self::pragmaArgumentLiteral($parent) . ')')['rows'] as $index) {
                if ((int) ($index['unique'] ?? 0) !== 1) {
                    continue;
                }

                $indexName = (string) $index['name'];
                $xinfo = $catalog->execute('PRAGMA index_xinfo(' . self::pragmaArgumentLiteral($indexName) . ')')['rows'];
                $keyRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1));
                $keyColumns = array_map(static fn (array $row): ?string => isset($row['name']) ? (string) $row['name'] : null, $keyRows);
                $hasExpression = self::hasExpressionKey($keyRows);
                $matchesParentColumns = self::sameColumns($keyColumns, $parentColumns);
                $partial = (int) ($index['partial'] ?? 0) === 1;
                if (!$partial && !$hasExpression) {
                    continue;
                }
                if (!$matchesParentColumns && !$hasExpression) {
                    continue;
                }

                $reason = $partial ? 'partial_unique_index' : 'expression_unique_index';
                $rows[] = [
                    'side' => $side,
                    'kind' => 'foreign_key_rejected_parent_unique',
                    'table' => (string) $foreignKey['table'],
                    'fkid' => (int) $foreignKey['id'],
                    'parent' => $parent,
                    'index' => $indexName,
                    'parent_columns' => $parentColumns,
                    'index_key_columns' => $keyColumns,
                    'index_partial' => $partial ? 1 : 0,
                    'index_expression_keys' => self::expressionKeyCount($keyRows),
                    'index_key_count' => count($keyRows),
                    'reason' => $reason,
                    'status' => 'rejected',
                    'message' => $reason === 'partial_unique_index'
                        ? "foreign key {$foreignKey['table']}->{$parent} cannot use partial UNIQUE index {$indexName} as a parent key"
                        : "foreign key {$foreignKey['table']}->{$parent} cannot use expression UNIQUE index {$indexName} as a parent key",
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
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next189 requires foreign key columns');
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
     * @param list<array<string,mixed>> $keyRows
     */
    private static function hasExpressionKey(array $keyRows): bool
    {
        return self::expressionKeyCount($keyRows) > 0;
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
     * @return array{rows:int,partial_unique:int,expression_unique:int}
     */
    private static function rejectionCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'partial_unique' => 0,
            'expression_unique' => 0,
        ];
        foreach ($rows as $row) {
            if (($row['reason'] ?? null) === 'partial_unique_index') {
                $counts['partial_unique']++;
            }
            if (($row['reason'] ?? null) === 'expression_unique_index') {
                $counts['expression_unique']++;
            }
        }

        return $counts;
    }

    /**
     * @param list<string> $baseBlocking
     * @param array{rows:int,partial_unique:int,expression_unique:int} $nextCounts
     * @return list<string>
     */
    private static function blocking(array $baseBlocking, array $nextCounts): array
    {
        $blocking = $baseBlocking;
        if ($nextCounts['rows'] > 0) {
            $blocking[] = 'foreign_key_rejected_parent_unique_index';
        }

        return array_values(array_unique($blocking));
    }

    /**
     * @param list<array<string,mixed>> $rejectedRows
     */
    private static function decorateParentKeyRow(array $row, array $rejectedRows): array
    {
        if (($row['kind'] ?? null) !== 'foreign_key_parent_key' || ($row['status'] ?? null) !== 'missing_parent_key') {
            return $row;
        }

        foreach ($rejectedRows as $rejected) {
            if (
                ($row['side'] ?? null) === ($rejected['side'] ?? null)
                && ($row['table'] ?? null) === ($rejected['table'] ?? null)
                && (int) ($row['fkid'] ?? -1) === (int) ($rejected['fkid'] ?? -2)
            ) {
                return [
                    ...$row,
                    'rejected_parent_unique_index' => $rejected['index'],
                    'rejected_parent_unique_reason' => $rejected['reason'],
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
                . ':' . $row['index'] . ':' . $row['reason'],
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
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next189 records must be SQLiteSchemaRecord instances');
            }
        }
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next189 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next189 cursor offset does not match the requested page offset');
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
