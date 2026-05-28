<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext195
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
        int $limit = 195,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next195 offset must be non-negative');
        }
        if ($limit <= 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next195 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext191::currentNextPageFromCatalog(
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

        $currentRows = self::permutedParentKeyRows($currentRecords, 'current');
        $nextRows = self::permutedParentKeyRows($nextRecords, 'next');
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next195',
            'base' => $base['source_id'],
            'current_permuted_parent_keys' => self::rowSummary($currentRows),
            'next_permuted_parent_keys' => self::rowSummary($nextRows),
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $total = count($allRows);
        $rows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($rows);
        $complete = $nextOffset >= $total;
        $currentCounts = self::permutedCounts($currentRows);
        $nextCounts = self::permutedCounts($nextRows);
        $blocking = array_values(array_unique([
            ...($base['next_state']['blocking'] ?? []),
            ...($nextCounts['permuted_unique_only'] > 0 ? ['foreign_key_parent_permuted_unique_index'] : []),
        ]));

        return [
            ...$base,
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($rows),
            'total' => $total,
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_permuted_source' => 'pragma_index_xinfo_permuted_unique_parent_indexes',
                'foreign_key_parent_permuted_keys' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...$base['next_source'],
                'foreign_key_parent_permuted_source' => 'pragma_index_xinfo_permuted_unique_parent_indexes',
                'foreign_key_parent_permuted_keys' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_permuted_rows' => count($currentRows),
                'foreign_key_parent_permuted' => $currentCounts,
                'total_blockers' => (int) ($base['current']['total_blockers'] ?? 0) + $currentCounts['permuted_unique_only'],
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_permuted_rows' => count($nextRows),
                'foreign_key_parent_permuted' => $nextCounts,
                'total_blockers' => (int) ($base['next_counts']['total_blockers'] ?? 0) + $nextCounts['permuted_unique_only'],
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_permuted_rows' => count($nextRows) - count($currentRows),
                'foreign_key_parent_permuted_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
                'foreign_key_parent_permuted_blocker_delta' => $nextCounts['permuted_unique_only'] - $currentCounts['permuted_unique_only'],
                'foreign_key_parent_permuted_repaired' => $currentCounts['permuted_unique_only'] > 0 && $nextCounts['permuted_unique_only'] === 0,
                'total_blockers' => ((int) ($base['delta']['total_blockers'] ?? 0)) + $nextCounts['permuted_unique_only'] - $currentCounts['permuted_unique_only'],
                'cleared' => (($base['delta']['cleared'] ?? false) === true) && $nextCounts['permuted_unique_only'] === 0,
            ],
            'next_state' => [
                ...$base['next_state'],
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
    public static function permutedParentKeyRows(array $records, string $side = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $side)) as $group) {
            $table = (string) $group[0]['table'];
            $parent = (string) $group[0]['parent'];
            $parentColumns = array_map(static fn (array $row): string => (string) $row['to'], $group);
            $candidate = self::parentPermutedIndexCandidate($catalog, $parent, $parentColumns);

            if ($candidate === null) {
                continue;
            }

            foreach ($group as $row) {
                $rows[] = [
                    'side' => $side,
                    'kind' => 'foreign_key_parent_permuted',
                    'table' => $table,
                    'fkid' => (int) $row['id'],
                    'seq' => (int) $row['seq'],
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'index' => $candidate['name'],
                    'index_unique' => 1,
                    'index_partial' => $candidate['partial'],
                    'expected_columns' => $parentColumns,
                    'index_key_columns' => $candidate['key_columns'],
                    'expected_position' => (int) $row['seq'],
                    'actual_position' => self::columnPosition($candidate['key_columns'], (string) $row['to']),
                    'status' => $candidate['partial'] === 1 ? 'partial_permuted_unique' : 'permuted_unique_only',
                    'message' => "foreign key {$table}->{$parent} parent columns match a UNIQUE index only after reordering; SQLite requires the parent key columns in declared order",
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
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next195 records must be SQLiteSchemaRecord instances');
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
     * @param list<string> $parentColumns
     * @return array{name:string,partial:int,key_columns:list<string>}|null
     */
    private static function parentPermutedIndexCandidate(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns): ?array
    {
        $wanted = array_map('strtolower', $parentColumns);
        $sortedWanted = $wanted;
        sort($sortedWanted);
        $partial = null;
        $full = null;
        $hasExactFullKey = false;

        foreach ($catalog->execute('PRAGMA index_list(' . self::pragmaArgumentLiteral($parent) . ')')['rows'] as $index) {
            if ((int) $index['unique'] !== 1) {
                continue;
            }

            $indexName = (string) $index['name'];
            $xinfo = $catalog->execute('PRAGMA index_xinfo(' . self::pragmaArgumentLiteral($indexName) . ')')['rows'];
            $keyRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1));
            $indexColumns = array_map(static fn (array $row): string => strtolower((string) ($row['name'] ?? '')), $keyRows);
            if ($indexColumns === $wanted && (int) $index['partial'] === 0) {
                $hasExactFullKey = true;
                continue;
            }
            if (count($indexColumns) !== count($wanted) || $indexColumns === $wanted) {
                continue;
            }

            $sortedIndex = $indexColumns;
            sort($sortedIndex);
            if ($sortedIndex !== $sortedWanted) {
                continue;
            }

            $candidate = [
                'name' => $indexName,
                'partial' => (int) $index['partial'],
                'key_columns' => array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $keyRows),
            ];
            if ((int) $index['partial'] === 1) {
                $partial ??= $candidate;
                continue;
            }

            $full ??= $candidate;
        }

        return $hasExactFullKey ? $partial : ($full ?? $partial);
    }

    /**
     * @param list<string> $columns
     */
    private static function columnPosition(array $columns, string $needle): ?int
    {
        foreach ($columns as $index => $column) {
            if (strcasecmp($column, $needle) === 0) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,permuted_unique_only:int,partial_permuted_unique:int,reordered_terms:int}
     */
    private static function permutedCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'permuted_unique_only' => 0,
            'partial_permuted_unique' => 0,
            'reordered_terms' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
            if (($row['actual_position'] ?? null) !== ($row['expected_position'] ?? null)) {
                $counts['reordered_terms']++;
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
            static fn (array $row): string => ($includeSide ? $row['side'] . ':' : '') . $row['table'] . '#' . $row['fkid'] . '.' . $row['seq'] . ':' . $row['from'] . '->' . $row['parent'] . '.' . $row['to'] . ':' . ($row['index'] ?? '') . ':order=' . implode('|', $row['index_key_columns'] ?? []),
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
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next195 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next195 cursor offset does not match the requested page offset');
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
