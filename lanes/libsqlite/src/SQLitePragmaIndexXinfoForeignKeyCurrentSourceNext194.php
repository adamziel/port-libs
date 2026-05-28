<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext194
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
        int $limit = 194,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next194 offset must be non-negative');
        }
        if ($limit <= 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next194 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext190::currentNextPageFromCatalog(
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

        $currentRows = self::partialChildIndexRows($currentRecords, 'current');
        $nextRows = self::partialChildIndexRows($nextRecords, 'next');
        $partialRows = array_values(array_merge($currentRows, $nextRows));
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next194',
            'base' => $base['source_id'],
            'current_partial_child_indexes' => self::rowSummary($currentRows),
            'next_partial_child_indexes' => self::rowSummary($nextRows),
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $allRows = array_values(array_merge(
            array_map(static fn (array $row): array => self::decorateChildIndexRow($row, $partialRows), $base['rows']),
            $partialRows,
        ));
        $total = count($allRows);
        $rows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($rows);
        $complete = $nextOffset >= $total;
        $currentCounts = self::partialCounts($currentRows);
        $nextCounts = self::partialCounts($nextRows);

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
                'foreign_key_partial_child_index_source' => 'pragma_index_list_partial_child_indexes_and_pragma_index_xinfo_prefixes',
                'foreign_key_partial_child_indexes' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...$base['next_source'],
                'foreign_key_partial_child_index_source' => 'pragma_index_list_partial_child_indexes_and_pragma_index_xinfo_prefixes',
                'foreign_key_partial_child_indexes' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_partial_child_index_rows' => count($currentRows),
                'foreign_key_partial_child_indexes' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_partial_child_index_rows' => count($nextRows),
                'foreign_key_partial_child_indexes' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_partial_child_index_rows' => count($nextRows) - count($currentRows),
                'foreign_key_partial_child_index_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
                'foreign_key_partial_child_index_cleared' => $currentCounts['partial_child_index'] > 0 && $nextCounts['partial_child_index'] === 0,
                'foreign_key_partial_child_index_diagnostic_only' => true,
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
    public static function partialChildIndexRows(array $records, string $side = 'current'): array
    {
        self::validateRecords($records);

        $rows = [];
        foreach (SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext183::childIndexRows($records, $side) as $row) {
            if (($row['status'] ?? null) !== 'ok' || (int) ($row['index_partial'] ?? 0) !== 1) {
                continue;
            }

            $rows[] = [
                'side' => $side,
                'kind' => 'foreign_key_partial_child_index',
                'table' => (string) $row['table'],
                'fkid' => (int) $row['fkid'],
                'seq' => (int) $row['seq'],
                'parent' => (string) $row['parent'],
                'from' => (string) $row['from'],
                'to' => (string) $row['to'],
                'index' => $row['index'],
                'index_unique' => $row['index_unique'],
                'index_partial' => 1,
                'index_seqno' => $row['index_seqno'],
                'index_cid' => $row['index_cid'],
                'index_name' => $row['index_name'],
                'index_coll' => $row['index_coll'],
                'covered_prefix_columns' => $row['covered_prefix_columns'],
                'extra_key_columns' => $row['extra_key_columns'],
                'auxiliary_columns_ignored' => $row['auxiliary_columns_ignored'],
                'status' => 'diagnostic_only',
                'message' => "foreign key {$row['table']} child column {$row['from']} is backed by partial index {$row['index']}; PRAGMA foreign_key_check does not require a child index",
            ];
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['side'], $left['table'], $left['fkid'], $left['seq']]
                <=> [$right['side'], $right['table'], $right['fkid'], $right['seq']],
        );

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,partial_child_index:int,unique:int,nonunique:int,extra_key_columns:int,auxiliary_columns_ignored:int}
     */
    private static function partialCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'partial_child_index' => 0,
            'unique' => 0,
            'nonunique' => 0,
            'extra_key_columns' => 0,
            'auxiliary_columns_ignored' => 0,
        ];
        foreach ($rows as $row) {
            $counts['partial_child_index']++;
            if (($row['index_unique'] ?? null) === 1) {
                $counts['unique']++;
            } elseif (($row['index_unique'] ?? null) === 0) {
                $counts['nonunique']++;
            }
            $counts['extra_key_columns'] += (int) ($row['extra_key_columns'] ?? 0);
            $counts['auxiliary_columns_ignored'] += (int) ($row['auxiliary_columns_ignored'] ?? 0);
        }

        return $counts;
    }

    /**
     * @param list<array<string,mixed>> $partialRows
     */
    private static function decorateChildIndexRow(array $row, array $partialRows): array
    {
        if (($row['kind'] ?? null) !== 'foreign_key_child_index') {
            return $row;
        }

        foreach ($partialRows as $partial) {
            if (
                ($row['side'] ?? null) === ($partial['side'] ?? null)
                && ($row['table'] ?? null) === ($partial['table'] ?? null)
                && (int) ($row['fkid'] ?? -1) === (int) ($partial['fkid'] ?? -2)
                && (int) ($row['seq'] ?? -1) === (int) ($partial['seq'] ?? -2)
            ) {
                return [
                    ...$row,
                    'partial_child_index_diagnostic_only' => true,
                    'partial_child_index_message' => $partial['message'],
                ];
            }
        }

        return $row;
    }

    /** @param list<mixed> $records */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next194 records must be SQLiteSchemaRecord instances');
            }
        }
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
                . ':' . ($row['index'] ?? '') . ':partial-child',
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
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next194 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next194 cursor offset does not match the requested page offset');
        }
    }

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
