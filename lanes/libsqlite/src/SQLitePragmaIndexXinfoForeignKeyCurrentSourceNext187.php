<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext187
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
        int $limit = 187,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next187 offset must be non-negative');
        }
        if ($limit <= 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next187 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext184::currentNextPageFromCatalog(
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

        $currentRows = self::partialParentIndexRows($currentRecords, 'current');
        $nextRows = self::partialParentIndexRows($nextRecords, 'next');
        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next187',
            'base' => $base['source_id'],
            'current_partial_parent_indexes' => self::rowSummary($currentRows),
            'next_partial_parent_indexes' => self::rowSummary($nextRows),
        ]);

        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

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
                'foreign_key_partial_parent_index_source' => 'pragma_index_list_partial_unique_parent_candidates',
                'foreign_key_partial_parent_index_rows' => count($currentRows),
            ],
            'next_source' => [
                ...$base['next_source'],
                'foreign_key_partial_parent_index_source' => 'pragma_index_list_partial_unique_parent_candidates',
                'foreign_key_partial_parent_index_rows' => count($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_partial_parent_index_rows' => count($currentRows),
                'foreign_key_partial_parent_indexes' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_partial_parent_index_rows' => count($nextRows),
                'foreign_key_partial_parent_indexes' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_partial_parent_index_rows' => count($nextRows) - count($currentRows),
                'foreign_key_partial_parent_index_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
                'foreign_key_partial_parent_index_blockers' => $nextCounts['partial_unique_candidates'] - $currentCounts['partial_unique_candidates'],
                'foreign_key_partial_parent_index_repaired' => $currentCounts['partial_unique_candidates'] > 0 && $nextCounts['partial_unique_candidates'] === 0,
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
    public static function partialParentIndexRows(array $records, string $side = 'current'): array
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next187 records must be SQLiteSchemaRecord instances');
            }
        }

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $parentRows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext178::parentKeyRows($records, $side);
        $rows = [];
        foreach (self::groupParentKeyRows($parentRows) as $group) {
            $first = $group[0];
            $parent = (string) $first['parent'];
            $parentColumns = array_map(static fn (array $row): string => (string) $row['to'], $group);
            $candidate = self::matchingPartialUniqueIndex($catalog, $parent, $parentColumns);
            if ($candidate === null) {
                continue;
            }

            foreach ($group as $row) {
                $indexRow = $candidate['rows'][(int) $row['seq']] ?? null;
                $mappedByFullParentKey = ($row['status'] ?? null) === 'ok';
                $status = $mappedByFullParentKey ? 'shadowed_by_full_parent_key' : 'partial_parent_key';
                $rows[] = [
                    'side' => $side,
                    'kind' => 'foreign_key_partial_parent_index',
                    'table' => (string) $row['table'],
                    'fkid' => (int) $row['fkid'],
                    'seq' => (int) $row['seq'],
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'index' => $candidate['name'],
                    'index_seqno' => $indexRow['seqno'] ?? null,
                    'index_cid' => $indexRow['cid'] ?? null,
                    'index_name' => $indexRow['name'] ?? null,
                    'index_coll' => $indexRow['coll'] ?? null,
                    'index_desc' => $indexRow['desc'] ?? null,
                    'index_partial' => 1,
                    'where' => $candidate['where'],
                    'full_parent_key' => $row['index'],
                    'status' => $status,
                    'message' => $mappedByFullParentKey
                        ? "foreign key {$row['table']}->{$parent} has full parent key {$row['index']}; partial UNIQUE {$candidate['name']} is diagnostic only"
                        : "foreign key {$row['table']}->{$parent} parent columns match partial UNIQUE {$candidate['name']} but partial indexes cannot satisfy FK parent keys",
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
    private static function groupParentKeyRows(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = strtolower((string) $row['table']) . '#' . (int) $row['fkid'];
            $groups[$key][] = $row;
        }

        foreach ($groups as &$group) {
            usort($group, static fn (array $left, array $right): int => (int) $left['seq'] <=> (int) $right['seq']);
        }

        return array_values($groups);
    }

    /**
     * @param list<string> $parentColumns
     * @return array{name:string,rows:list<array<string,mixed>>,where:string|null}|null
     */
    private static function matchingPartialUniqueIndex(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns): ?array
    {
        foreach ($catalog->execute('PRAGMA index_list(' . self::pragmaArgumentLiteral($parent) . ')')['rows'] as $index) {
            if ((int) $index['unique'] !== 1 || (int) $index['partial'] !== 1) {
                continue;
            }

            $indexName = (string) $index['name'];
            $xinfo = $catalog->execute('PRAGMA index_xinfo(' . self::pragmaArgumentLiteral($indexName) . ')')['rows'];
            $keyRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1));
            $indexColumns = array_map(static fn (array $row): string => strtolower((string) ($row['name'] ?? '')), $keyRows);
            if ($indexColumns !== array_map('strtolower', $parentColumns)) {
                continue;
            }

            return [
                'name' => $indexName,
                'rows' => $keyRows,
                'where' => self::whereClauseForIndex($catalog, $indexName),
            ];
        }

        return null;
    }

    private static function whereClauseForIndex(SQLitePragmaSchemaCatalog $catalog, string $indexName): ?string
    {
        foreach ($catalog->records() as $record) {
            if ($record->type !== 'index' || strcasecmp($record->name, $indexName) !== 0 || $record->sql === null) {
                continue;
            }
            if (!preg_match('/\bWHERE\b(?<where>.+)$/is', $record->sql, $matches)) {
                return null;
            }

            return trim($matches['where']);
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,partial_unique_candidates:int,shadowed_by_full_parent_key:int,columns:int}
     */
    private static function partialCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'partial_unique_candidates' => 0,
            'shadowed_by_full_parent_key' => 0,
            'columns' => 0,
        ];
        foreach ($rows as $row) {
            if (($row['status'] ?? null) === 'partial_parent_key') {
                $counts['partial_unique_candidates']++;
            } elseif (($row['status'] ?? null) === 'shadowed_by_full_parent_key') {
                $counts['shadowed_by_full_parent_key']++;
            }
            $counts['columns']++;
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
            static fn (array $row): string => ($includeSide ? $row['side'] . ':' : '') . $row['table'] . '#' . $row['fkid'] . '.' . $row['seq'] . ':' . $row['from'] . '->' . $row['parent'] . '.' . $row['to'] . ':' . $row['index'] . ':' . $row['status'] . ':' . ($row['where'] ?? ''),
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
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next187 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next187 cursor offset does not match the requested page offset');
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
