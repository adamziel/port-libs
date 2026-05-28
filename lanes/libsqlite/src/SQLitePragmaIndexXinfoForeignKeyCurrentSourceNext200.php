<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext200
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
        int $limit = 200,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next200 offset must be non-negative');
        }
        if ($limit <= 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next200 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext194::currentNextPageFromCatalog(
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

        $currentRows = self::wrongOrderChildIndexRows($currentRecords, 'current');
        $nextRows = self::wrongOrderChildIndexRows($nextRecords, 'next');
        $orderRows = array_values(array_merge($currentRows, $nextRows));
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next200',
            'base' => $base['source_id'],
            'current_wrong_order_child_indexes' => self::rowSummary($currentRows),
            'next_wrong_order_child_indexes' => self::rowSummary($nextRows),
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $allRows = array_values(array_merge(
            array_map(static fn (array $row): array => self::decorateChildIndexRow($row, $orderRows), $base['rows']),
            $orderRows,
        ));
        $total = count($allRows);
        $rows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($rows);
        $complete = $nextOffset >= $total;
        $currentCounts = self::orderCounts($currentRows);
        $nextCounts = self::orderCounts($nextRows);

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
                'foreign_key_wrong_order_child_index_source' => 'pragma_index_xinfo_child_key_same_set_wrong_order',
                'foreign_key_wrong_order_child_indexes' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...$base['next_source'],
                'foreign_key_wrong_order_child_index_source' => 'pragma_index_xinfo_child_key_same_set_wrong_order',
                'foreign_key_wrong_order_child_indexes' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_wrong_order_child_index_rows' => count($currentRows),
                'foreign_key_wrong_order_child_indexes' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_wrong_order_child_index_rows' => count($nextRows),
                'foreign_key_wrong_order_child_indexes' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_wrong_order_child_index_rows' => count($nextRows) - count($currentRows),
                'foreign_key_wrong_order_child_index_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
                'foreign_key_wrong_order_child_index_repaired' => $currentCounts['wrong_order_child_index'] > 0 && $nextCounts['wrong_order_child_index'] === 0,
                'foreign_key_wrong_order_child_index_diagnostic_only' => true,
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
    public static function wrongOrderChildIndexRows(array $records, string $side = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $side)) as $group) {
            $table = (string) $group[0]['table'];
            $childColumns = array_map(static fn (array $row): string => (string) $row['from'], $group);
            $candidate = self::wrongOrderChildIndexCandidate($catalog, $table, $childColumns);
            if ($candidate === null) {
                continue;
            }

            foreach ($group as $row) {
                $indexRow = $candidate['matched_rows'][strtolower((string) $row['from'])] ?? null;
                $rows[] = [
                    'side' => $side,
                    'kind' => 'foreign_key_wrong_order_child_index',
                    'table' => $table,
                    'fkid' => (int) $row['id'],
                    'seq' => (int) $row['seq'],
                    'parent' => (string) $row['parent'],
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'index' => $candidate['name'],
                    'index_unique' => $candidate['unique'],
                    'index_partial' => $candidate['partial'],
                    'index_seqno' => $indexRow['seqno'] ?? null,
                    'index_cid' => $indexRow['cid'] ?? null,
                    'index_name' => $indexRow['name'] ?? null,
                    'index_coll' => $indexRow['coll'] ?? null,
                    'expected_child_columns' => $childColumns,
                    'index_key_columns' => $candidate['key_columns'],
                    'matched_column_set' => true,
                    'prefix_order_matches' => false,
                    'extra_key_columns' => $candidate['extra_key_columns'],
                    'auxiliary_columns_ignored' => $candidate['auxiliary_columns'],
                    'status' => 'diagnostic_only',
                    'message' => "foreign key {$table} child columns match index {$candidate['name']} only as an unordered set; PRAGMA index_xinfo prefix order is needed for useful child-key lookup",
                ];
            }
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['side'], $left['table'], $left['fkid'], $left['seq']]
                <=> [$right['side'], $right['table'], $right['fkid'], $right['seq']],
        );

        return $rows;
    }

    /**
     * @param list<mixed> $records
     */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next200 records must be SQLiteSchemaRecord instances');
            }
        }
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
     * @param list<string> $childColumns
     * @return array{name:string,unique:int,partial:int,key_columns:list<string>,matched_rows:array<string,array<string,mixed>>,extra_key_columns:int,auxiliary_columns:int}|null
     */
    private static function wrongOrderChildIndexCandidate(SQLitePragmaSchemaCatalog $catalog, string $table, array $childColumns): ?array
    {
        $wanted = array_map('strtolower', $childColumns);
        foreach ($catalog->execute('PRAGMA index_list(' . self::pragmaArgumentLiteral($table) . ')')['rows'] as $index) {
            $indexName = (string) $index['name'];
            $xinfo = $catalog->execute('PRAGMA index_xinfo(' . self::pragmaArgumentLiteral($indexName) . ')')['rows'];
            $keyRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1));
            $prefix = array_slice($keyRows, 0, count($childColumns));
            $prefixColumns = array_map(static fn (array $row): string => strtolower((string) ($row['name'] ?? '')), $prefix);
            if ($prefixColumns === $wanted || count($prefixColumns) !== count($wanted)) {
                continue;
            }

            $prefixSet = $prefixColumns;
            sort($prefixSet);
            $wantedSet = $wanted;
            sort($wantedSet);
            if ($prefixSet !== $wantedSet) {
                continue;
            }

            $matchedRows = [];
            foreach ($prefix as $row) {
                $matchedRows[strtolower((string) ($row['name'] ?? ''))] = $row;
            }

            return [
                'name' => $indexName,
                'unique' => (int) $index['unique'],
                'partial' => (int) $index['partial'],
                'key_columns' => array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $keyRows),
                'matched_rows' => $matchedRows,
                'extra_key_columns' => max(0, count($keyRows) - count($childColumns)),
                'auxiliary_columns' => count($xinfo) - count($keyRows),
            ];
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,wrong_order_child_index:int,unique:int,nonunique:int,partial:int,extra_key_columns:int,auxiliary_columns_ignored:int}
     */
    private static function orderCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'wrong_order_child_index' => 0,
            'unique' => 0,
            'nonunique' => 0,
            'partial' => 0,
            'extra_key_columns' => 0,
            'auxiliary_columns_ignored' => 0,
        ];
        foreach ($rows as $row) {
            $counts['wrong_order_child_index']++;
            if (($row['index_unique'] ?? null) === 1) {
                $counts['unique']++;
            } elseif (($row['index_unique'] ?? null) === 0) {
                $counts['nonunique']++;
            }
            if (($row['index_partial'] ?? null) === 1) {
                $counts['partial']++;
            }
            $counts['extra_key_columns'] += (int) ($row['extra_key_columns'] ?? 0);
            $counts['auxiliary_columns_ignored'] += (int) ($row['auxiliary_columns_ignored'] ?? 0);
        }

        return $counts;
    }

    /**
     * @param list<array<string,mixed>> $orderRows
     */
    private static function decorateChildIndexRow(array $row, array $orderRows): array
    {
        if (($row['kind'] ?? null) !== 'foreign_key_child_index' || ($row['status'] ?? null) !== 'missing_child_index') {
            return $row;
        }

        foreach ($orderRows as $orderRow) {
            if (
                ($row['side'] ?? null) === ($orderRow['side'] ?? null)
                && ($row['table'] ?? null) === ($orderRow['table'] ?? null)
                && (int) ($row['fkid'] ?? -1) === (int) ($orderRow['fkid'] ?? -2)
                && (int) ($row['seq'] ?? -1) === (int) ($orderRow['seq'] ?? -2)
            ) {
                return [
                    ...$row,
                    'wrong_order_child_index' => $orderRow['index'],
                    'wrong_order_child_index_columns' => $orderRow['index_key_columns'],
                    'wrong_order_child_index_diagnostic_only' => true,
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
                . $row['table'] . '#' . $row['fkid'] . '.' . $row['seq']
                . ':' . $row['from'] . '->' . $row['parent'] . '.' . $row['to']
                . ':' . ($row['index'] ?? '') . ':wrong-child-order',
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
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next200 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next200 cursor offset does not match the requested page offset');
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
