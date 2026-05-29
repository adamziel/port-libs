<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext190
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
        int $limit = 190,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next190 offset must be non-negative');
        }
        if ($limit <= 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next190 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext187::currentNextPageFromCatalog(
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

        $currentRows = self::expressionParentIndexRows($currentRecords, 'current');
        $nextRows = self::expressionParentIndexRows($nextRecords, 'next');
        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next190',
            'base' => $base['source_id'],
            'current_expression_parent_indexes' => self::rowSummary($currentRows),
            'next_expression_parent_indexes' => self::rowSummary($nextRows),
        ]);

        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $total = count($allRows);
        $rows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($rows);
        $complete = $nextOffset >= $total;
        $currentCounts = self::expressionCounts($currentRows);
        $nextCounts = self::expressionCounts($nextRows);

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
                'foreign_key_expression_parent_index_source' => 'pragma_index_xinfo_expression_parent_candidates',
                'foreign_key_expression_parent_index_rows' => count($currentRows),
            ],
            'next_source' => [
                ...$base['next_source'],
                'foreign_key_expression_parent_index_source' => 'pragma_index_xinfo_expression_parent_candidates',
                'foreign_key_expression_parent_index_rows' => count($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_expression_parent_index_rows' => count($currentRows),
                'foreign_key_expression_parent_indexes' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_expression_parent_index_rows' => count($nextRows),
                'foreign_key_expression_parent_indexes' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_expression_parent_index_rows' => count($nextRows) - count($currentRows),
                'foreign_key_expression_parent_index_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
                'foreign_key_expression_parent_index_blockers' => $nextCounts['expression_parent_key'] - $currentCounts['expression_parent_key'],
                'foreign_key_expression_parent_index_repaired' => $currentCounts['expression_parent_key'] > 0 && $nextCounts['expression_parent_key'] === 0,
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
    public static function expressionParentIndexRows(array $records, string $side = 'current'): array
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next190 records must be SQLiteSchemaRecord instances');
            }
        }

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (self::groupParentKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext178::parentKeyRows($records, $side)) as $group) {
            $first = $group[0];
            $parent = (string) $first['parent'];
            $parentColumns = array_map(static fn (array $row): string => (string) $row['to'], $group);
            $candidate = self::matchingExpressionUniqueIndex($catalog, $parent, $parentColumns);
            if ($candidate === null) {
                continue;
            }

            foreach ($group as $row) {
                $indexRow = $candidate['rows'][(int) $row['seq']] ?? null;
                $mappedByFullParentKey = ($row['status'] ?? null) === 'ok';
                $status = $mappedByFullParentKey ? 'shadowed_by_full_parent_key' : 'expression_parent_key';
                $rows[] = [
                    'side' => $side,
                    'kind' => 'foreign_key_expression_parent_index',
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
                    'index_partial' => 0,
                    'expression_terms' => $candidate['expression_terms'],
                    'ordinary_terms' => $candidate['ordinary_terms'],
                    'full_parent_key' => $row['index'],
                    'status' => $status,
                    'message' => $mappedByFullParentKey
                        ? "foreign key {$row['table']}->{$parent} has full parent key {$row['index']}; expression UNIQUE {$candidate['name']} is diagnostic only"
                        : "foreign key {$row['table']}->{$parent} parent key arity matches expression UNIQUE {$candidate['name']} but expression terms cannot satisfy FK parent keys",
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
     * @return array{name:string,rows:list<array<string,mixed>>,expression_terms:int,ordinary_terms:int}|null
     */
    private static function matchingExpressionUniqueIndex(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns): ?array
    {
        foreach ($catalog->execute('PRAGMA index_list(' . self::pragmaArgumentLiteral($parent) . ')')['rows'] as $index) {
            if ((int) $index['unique'] !== 1 || (int) $index['partial'] !== 0) {
                continue;
            }

            $indexName = (string) $index['name'];
            $xinfo = $catalog->execute('PRAGMA index_xinfo(' . self::pragmaArgumentLiteral($indexName) . ')')['rows'];
            $keyRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1));
            if (count($keyRows) !== count($parentColumns)) {
                continue;
            }

            $expressionTerms = count(array_filter($keyRows, static fn (array $row): bool => (int) ($row['cid'] ?? 0) === -2 || ($row['name'] ?? null) === null));
            if ($expressionTerms === 0) {
                continue;
            }

            $ordinaryTerms = count($keyRows) - $expressionTerms;
            $ordinaryColumns = array_values(array_map(
                static fn (array $row): string => strtolower((string) ($row['name'] ?? '')),
                array_filter($keyRows, static fn (array $row): bool => ($row['name'] ?? null) !== null),
            ));
            $parentColumnNames = array_map('strtolower', $parentColumns);
            if ($ordinaryColumns !== array_slice($parentColumnNames, 0, count($ordinaryColumns))) {
                continue;
            }

            return [
                'name' => $indexName,
                'rows' => $keyRows,
                'expression_terms' => $expressionTerms,
                'ordinary_terms' => $ordinaryTerms,
            ];
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,expression_parent_key:int,shadowed_by_full_parent_key:int,expression_terms:int,ordinary_terms:int}
     */
    private static function expressionCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'expression_parent_key' => 0,
            'shadowed_by_full_parent_key' => 0,
            'expression_terms' => 0,
            'ordinary_terms' => 0,
        ];
        foreach ($rows as $row) {
            if (($row['status'] ?? null) === 'expression_parent_key') {
                $counts['expression_parent_key']++;
            } elseif (($row['status'] ?? null) === 'shadowed_by_full_parent_key') {
                $counts['shadowed_by_full_parent_key']++;
            }
            $counts['expression_terms'] += (int) ($row['expression_terms'] ?? 0);
            $counts['ordinary_terms'] += (int) ($row['ordinary_terms'] ?? 0);
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
            static fn (array $row): string => ($includeSide ? $row['side'] . ':' : '') . $row['table'] . '#' . $row['fkid'] . '.' . $row['seq'] . ':' . $row['from'] . '->' . $row['parent'] . '.' . $row['to'] . ':' . $row['index'] . ':' . $row['status'] . ':' . $row['expression_terms'] . '/' . $row['ordinary_terms'],
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
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next190 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next190 cursor offset does not match the requested page offset');
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
