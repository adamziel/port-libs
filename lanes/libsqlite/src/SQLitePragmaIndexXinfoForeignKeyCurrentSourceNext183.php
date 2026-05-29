<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext183
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
        int $limit = 183,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next183 offset must be non-negative');
        }
        if ($limit <= 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next183 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext178::currentNextPageFromCatalog(
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

        $currentRows = self::childIndexRows($currentRecords, 'current');
        $nextRows = self::childIndexRows($nextRecords, 'next');
        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next183',
            'base' => $base['source_id'],
            'current_child_index_rows' => self::rowSummary($currentRows),
            'next_child_index_rows' => self::rowSummary($nextRows),
        ]);

        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $total = count($allRows);
        $rows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($rows);
        $complete = $nextOffset >= $total;

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
                'foreign_key_child_index_source' => 'pragma_index_xinfo_child_key_prefixes',
                'foreign_key_child_index_rows' => count($currentRows),
            ],
            'next_source' => [
                ...$base['next_source'],
                'foreign_key_child_index_source' => 'pragma_index_xinfo_child_key_prefixes',
                'foreign_key_child_index_rows' => count($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_child_index_rows' => count($currentRows),
                'foreign_key_child_indexes' => self::indexCounts($currentRows),
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_child_index_rows' => count($nextRows),
                'foreign_key_child_indexes' => self::indexCounts($nextRows),
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_child_index_rows' => count($nextRows) - count($currentRows),
                'foreign_key_child_index_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
                'foreign_key_child_index_repaired' => self::indexCounts($currentRows)['missing_child_index'] > 0
                    && self::indexCounts($nextRows)['missing_child_index'] === 0,
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
    public static function childIndexRows(array $records, string $side = 'current'): array
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next183 records must be SQLiteSchemaRecord instances');
            }
        }

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $side)) as $group) {
            $table = (string) $group[0]['table'];
            $childColumns = array_map(static fn (array $row): string => (string) $row['from'], $group);
            $candidate = self::matchingChildIndex($catalog, $table, $childColumns);

            foreach ($group as $row) {
                $indexRow = $candidate['rows'][(int) $row['seq']] ?? null;
                $rows[] = [
                    'side' => $side,
                    'kind' => 'foreign_key_child_index',
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
                    'index_key' => $indexRow['key'] ?? null,
                    'covered_prefix_columns' => $candidate['covered_prefix_columns'],
                    'extra_key_columns' => $candidate['extra_key_columns'],
                    'auxiliary_columns_ignored' => $candidate['auxiliary_columns'],
                    'status' => $candidate['name'] === null ? 'missing_child_index' : 'ok',
                    'message' => $candidate['name'] === null
                        ? "foreign key {$table} child columns are not backed by a PRAGMA index_xinfo prefix"
                        : "foreign key {$table} child column {$row['from']} maps to {$candidate['name']} key {$row['seq']}",
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
     * @param list<array<string,mixed>> $rows
     * @return list<list<array<string,mixed>>>
     */
    private static function groupForeignKeyRows(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = strtolower((string) $row['table']) . '#' . (int) $row['id'];
            $groups[$key][] = $row;
        }

        foreach ($groups as &$group) {
            usort($group, static fn (array $left, array $right): int => (int) $left['seq'] <=> (int) $right['seq']);
        }

        return array_values($groups);
    }

    /**
     * @param list<string> $childColumns
     * @return array{name:string|null,unique:int|null,partial:int|null,rows:list<array<string,mixed>>,covered_prefix_columns:int,extra_key_columns:int,auxiliary_columns:int}
     */
    private static function matchingChildIndex(SQLitePragmaSchemaCatalog $catalog, string $table, array $childColumns): array
    {
        foreach ($catalog->execute('PRAGMA index_list(' . self::pragmaArgumentLiteral($table) . ')')['rows'] as $index) {
            $indexName = (string) $index['name'];
            $xinfo = $catalog->execute('PRAGMA index_xinfo(' . self::pragmaArgumentLiteral($indexName) . ')')['rows'];
            $keyRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1));
            $prefix = array_slice($keyRows, 0, count($childColumns));
            $prefixColumns = array_map(static fn (array $row): string => strtolower((string) ($row['name'] ?? '')), $prefix);
            if ($prefixColumns !== array_map('strtolower', $childColumns)) {
                continue;
            }

            return [
                'name' => $indexName,
                'unique' => (int) $index['unique'],
                'partial' => (int) $index['partial'],
                'rows' => $prefix,
                'covered_prefix_columns' => count($prefix),
                'extra_key_columns' => max(0, count($keyRows) - count($childColumns)),
                'auxiliary_columns' => count($xinfo) - count($keyRows),
            ];
        }

        return [
            'name' => null,
            'unique' => null,
            'partial' => null,
            'rows' => [],
            'covered_prefix_columns' => 0,
            'extra_key_columns' => 0,
            'auxiliary_columns' => 0,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,int>
     */
    private static function indexCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'mapped' => 0,
            'missing_child_index' => 0,
            'unique_index' => 0,
            'nonunique_index' => 0,
            'partial_index' => 0,
            'extra_key_columns' => 0,
            'auxiliary_columns_ignored' => 0,
        ];
        foreach ($rows as $row) {
            if (($row['status'] ?? null) === 'ok') {
                $counts['mapped']++;
            } else {
                $counts['missing_child_index']++;
            }
            if (($row['index_unique'] ?? null) === 1) {
                $counts['unique_index']++;
            } elseif (($row['index_unique'] ?? null) === 0) {
                $counts['nonunique_index']++;
            }
            if (($row['index_partial'] ?? null) === 1) {
                $counts['partial_index']++;
            }
            $counts['extra_key_columns'] += (int) ($row['extra_key_columns'] ?? 0);
            $counts['auxiliary_columns_ignored'] += (int) ($row['auxiliary_columns_ignored'] ?? 0);
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
            static fn (array $row): string => ($includeSide ? $row['side'] . ':' : '') . $row['table'] . '#' . $row['fkid'] . '.' . $row['seq'] . ':' . $row['from'] . '->' . $row['parent'] . '.' . $row['to'] . ':' . ($row['index'] ?? '') . ':' . ($row['index_name'] ?? '') . ':' . ($row['status'] ?? ''),
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
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next183 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next183 cursor offset does not match the requested page offset');
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
