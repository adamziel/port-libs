<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175
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
        int $limit = 175,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next175 offset must be non-negative');
        }
        if ($limit <= 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next175 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext173::currentNextPageFromCatalog(
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

        $currentRows = self::foreignKeyListRows($currentRecords, 'current');
        $nextRows = self::foreignKeyListRows($nextRecords, 'next');
        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next175',
            'base' => $base['source_id'],
            'current_fk_list_rows' => self::rowSummary($currentRows),
            'next_fk_list_rows' => self::rowSummary($nextRows),
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
                'foreign_key_list_row_source' => 'pragma_foreign_key_list_column_sequences',
                'foreign_key_list_rows' => count($currentRows),
            ],
            'next_source' => [
                ...$base['next_source'],
                'foreign_key_list_row_source' => 'pragma_foreign_key_list_column_sequences',
                'foreign_key_list_rows' => count($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_list_rows' => count($currentRows),
                'foreign_key_list_columns' => self::columnCounts($currentRows),
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_list_rows' => count($nextRows),
                'foreign_key_list_columns' => self::columnCounts($nextRows),
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_list_rows' => count($nextRows) - count($currentRows),
                'foreign_key_list_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
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
    public static function foreignKeyListRows(array $records, string $side = 'current'): array
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next175 records must be SQLiteSchemaRecord instances');
            }
        }

        $rows = [];
        foreach (SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext167::foreignKeysFromCatalog($records) as $foreignKey) {
            foreach (($foreignKey['columns'] ?? []) as $seq => $column) {
                $rows[] = [
                    'side' => $side,
                    'kind' => 'foreign_key_list',
                    'id' => (int) $foreignKey['id'],
                    'seq' => (int) $seq,
                    'table' => (string) $foreignKey['table'],
                    'parent' => (string) $foreignKey['parent'],
                    'from' => (string) $column['child'],
                    'to' => (string) $column['parent'],
                    'affinity' => (string) $column['affinity'],
                    'collation' => (string) $column['collation'],
                    'on_update' => (string) ($foreignKey['on_update'] ?? 'NO ACTION'),
                    'on_delete' => (string) ($foreignKey['on_delete'] ?? 'NO ACTION'),
                    'match' => (string) ($foreignKey['match'] ?? 'NONE'),
                    'without_rowid' => (bool) ($foreignKey['without_rowid'] ?? false),
                ];
            }
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['side'], $left['table'], $left['id'], $left['seq']]
                <=> [$right['side'], $right['table'], $right['id'], $right['seq']],
        );

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,int>
     */
    private static function columnCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'composite_columns' => 0,
            'implicit_parent_columns' => 0,
            'without_rowid_children' => 0,
        ];
        $ids = [];
        foreach ($rows as $row) {
            $key = strtolower((string) $row['table']) . '#' . (int) $row['id'];
            $ids[$key] = ($ids[$key] ?? 0) + 1;
            if ((bool) ($row['without_rowid'] ?? false)) {
                $counts['without_rowid_children']++;
            }
            if (($row['to'] ?? '') !== '') {
                $counts['implicit_parent_columns']++;
            }
        }
        foreach ($ids as $count) {
            if ($count > 1) {
                $counts['composite_columns'] += $count;
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
            static fn (array $row): string => ($includeSide ? $row['side'] . ':' : '') . $row['table'] . '#' . $row['id'] . '.' . $row['seq'] . ':' . $row['from'] . '->' . $row['parent'] . '.' . $row['to'] . ':' . $row['on_update'] . '/' . $row['on_delete'] . '/' . $row['match'],
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
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next175 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next175 cursor offset does not match the requested page offset');
        }
    }

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
