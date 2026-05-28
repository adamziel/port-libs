<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext184
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
        int $limit = 184,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next184 offset must be non-negative');
        }
        if ($limit <= 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next184 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext181::currentNextPageFromCatalog(
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

        $currentRows = self::parentKeySortRows($currentRecords, 'current');
        $nextRows = self::parentKeySortRows($nextRecords, 'next');
        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next184',
            'base' => $base['source_id'],
            'current_parent_sort_rows' => self::rowSummary($currentRows),
            'next_parent_sort_rows' => self::rowSummary($nextRows),
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
                'foreign_key_parent_sort_source' => 'pragma_index_xinfo_parent_key_desc_columns',
                'foreign_key_parent_sort_rows' => count($currentRows),
            ],
            'next_source' => [
                ...$base['next_source'],
                'foreign_key_parent_sort_source' => 'pragma_index_xinfo_parent_key_desc_columns',
                'foreign_key_parent_sort_rows' => count($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_sort_rows' => count($currentRows),
                'foreign_key_parent_sort' => self::sortCounts($currentRows),
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_sort_rows' => count($nextRows),
                'foreign_key_parent_sort' => self::sortCounts($nextRows),
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_sort_rows' => count($nextRows) - count($currentRows),
                'foreign_key_parent_sort_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
                'foreign_key_parent_desc_delta' => self::sortCounts($nextRows)['desc'] - self::sortCounts($currentRows)['desc'],
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
    public static function parentKeySortRows(array $records, string $side = 'current'): array
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next184 records must be SQLiteSchemaRecord instances');
            }
        }

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext178::parentKeyRows($records, $side) as $row) {
            $desc = self::indexDescForParentKey($catalog, $row);
            $rows[] = [
                'side' => $side,
                'kind' => 'foreign_key_parent_sort',
                'table' => (string) $row['table'],
                'fkid' => (int) $row['fkid'],
                'seq' => (int) $row['seq'],
                'parent' => (string) $row['parent'],
                'from' => (string) $row['from'],
                'to' => (string) $row['to'],
                'index' => $row['index'],
                'index_seqno' => $row['index_seqno'],
                'index_name' => $row['index_name'],
                'index_desc' => $desc,
                'sort_order' => $desc === 1 ? 'DESC' : ($desc === 0 ? 'ASC' : 'unmapped'),
                'status' => ($row['status'] ?? null) === 'ok' ? 'ok' : 'missing_parent_key',
                'message' => ($row['status'] ?? null) === 'ok'
                    ? "foreign key {$row['table']}->{$row['parent']} parent key {$row['to']} uses " . ($desc === 1 ? 'DESC' : 'ASC') . ' index order'
                    : "foreign key {$row['table']}->{$row['parent']} parent key {$row['to']} has no mapped index order",
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
     * @param array<string,mixed> $parentKeyRow
     */
    private static function indexDescForParentKey(SQLitePragmaSchemaCatalog $catalog, array $parentKeyRow): ?int
    {
        $index = $parentKeyRow['index'] ?? null;
        if (($parentKeyRow['status'] ?? null) !== 'ok' || !is_string($index)) {
            return null;
        }
        if ($index === 'rowid-primary-key') {
            return 0;
        }

        $seqno = $parentKeyRow['index_seqno'] ?? null;
        foreach ($catalog->execute('PRAGMA index_xinfo(' . self::pragmaArgumentLiteral($index) . ')')['rows'] as $row) {
            if ((int) ($row['key'] ?? 0) !== 1) {
                continue;
            }
            if ((int) ($row['seqno'] ?? -1) === (int) $seqno) {
                return (int) ($row['desc'] ?? 0);
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,asc:int,desc:int,missing_parent_key:int}
     */
    private static function sortCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'asc' => 0,
            'desc' => 0,
            'missing_parent_key' => 0,
        ];
        foreach ($rows as $row) {
            if (($row['status'] ?? null) !== 'ok') {
                $counts['missing_parent_key']++;
            } elseif ((int) ($row['index_desc'] ?? 0) === 1) {
                $counts['desc']++;
            } else {
                $counts['asc']++;
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
            static fn (array $row): string => ($includeSide ? $row['side'] . ':' : '') . $row['table'] . '#' . $row['fkid'] . '.' . $row['seq'] . ':' . $row['from'] . '->' . $row['parent'] . '.' . $row['to'] . ':' . ($row['index'] ?? '') . ':' . ($row['sort_order'] ?? ''),
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
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next184 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next184 cursor offset does not match the requested page offset');
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
