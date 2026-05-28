<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext244
{
    /**
     * @param list<SQLiteSchemaRecord> $currentRecords
     * @param list<SQLiteSchemaRecord> $nextRecords
     * @param array{source_id:string,offset:int}|null $resume
     * @return array<string,mixed>
     */
    public static function page(
        array $currentRecords,
        array $nextRecords,
        string $indexXinfoSql,
        string $foreignKeySql,
        int $offset = 0,
        int $limit = 50,
        ?array $resume = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next244 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next244 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext241::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::expressionParentKeyRows($currentRecords, 'current');
        $nextRows = self::expressionParentKeyRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next244',
            'base' => $base['source_id'],
            'current_expression_parent_keys' => self::rowSummary($currentRows),
            'next_expression_parent_keys' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next244 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next244 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::expressionCounts($currentRows);
        $nextCounts = self::expressionCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next244',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_expression_index_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_expression_key_rows',
                'foreign_key_parent_expression_indexes' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_parent_expression_index_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_expression_key_rows',
                'foreign_key_parent_expression_indexes' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_expression_indexes' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_expression_indexes' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_expression_index_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_parent_expression_index_blockers' => $nextCounts['blocked'] - $currentCounts['blocked'],
                'foreign_key_parent_expression_index_repaired' => $currentCounts['blocked'] > 0 && $nextCounts['blocked'] === 0,
                'foreign_key_parent_expression_index_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-index-xinfo-expression-parent-key-admission',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function expressionParentKeyRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $groups = self::foreignKeyGroups($records, $phase);
        $rows = [];

        foreach ($groups as $group) {
            $parent = (string) $group[0]['parent'];
            $parentColumns = array_values(array_map(static fn (array $row): string => (string) $row['to'], $group));
            if (in_array('', $parentColumns, true)) {
                continue;
            }

            foreach ($catalog->indexList($parent) as $index) {
                if ((int) $index['unique'] !== 1 || (int) $index['partial'] !== 0) {
                    continue;
                }

                $xinfo = $catalog->indexXInfo((string) $index['name']);
                $keyRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) $row['key'] === 1));
                $expressionRows = array_values(array_filter(
                    $keyRows,
                    static fn (array $row): bool => (int) $row['cid'] === -2 || ($row['name'] ?? null) === null,
                ));
                if ($expressionRows === []) {
                    continue;
                }

                $storedColumns = array_values(array_map(
                    static fn (array $row): string => (string) $row['name'],
                    array_filter($keyRows, static fn (array $row): bool => (int) $row['cid'] >= 0 && ($row['name'] ?? null) !== null),
                ));
                $status = self::looksLikeParentKeyCandidate($storedColumns, $parentColumns, count($keyRows))
                    ? 'expression_parent_key_unusable'
                    : 'expression_unique_index_unrelated';

                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_parent_expression_index',
                    'table' => (string) $group[0]['table'],
                    'foreign_key_id' => (int) $group[0]['id'],
                    'parent' => $parent,
                    'parent_columns' => $parentColumns,
                    'index' => (string) $index['name'],
                    'index_key_columns' => $storedColumns,
                    'index_key_arity' => count($keyRows),
                    'expression_key_columns' => count($expressionRows),
                    'status' => $status,
                    'blocked' => $status === 'expression_parent_key_unusable',
                    'message' => $status === 'expression_parent_key_unusable'
                        ? "foreign key {$group[0]['table']}->{$parent} cannot use UNIQUE index {$index['name']} because PRAGMA index_xinfo reports expression key columns"
                        : "UNIQUE index {$index['name']} on parent {$parent} has expression key columns but does not match foreign key {$group[0]['table']} parent columns",
                ];
            }
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['phase'], $left['table'], $left['foreign_key_id'], $left['index']]
                <=> [$right['phase'], $right['table'], $right['foreign_key_id'], $right['index']],
        );

        return $rows;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,list<array<string,mixed>>>
     */
    private static function foreignKeyGroups(array $records, string $phase): array
    {
        $groups = [];
        foreach (SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase) as $row) {
            $groups[strtolower((string) $row['table']) . '#' . (int) $row['id']][] = $row;
        }

        foreach ($groups as &$group) {
            usort($group, static fn (array $left, array $right): int => (int) $left['seq'] <=> (int) $right['seq']);
        }

        return $groups;
    }

    /**
     * @param list<string> $storedColumns
     * @param list<string> $parentColumns
     */
    private static function looksLikeParentKeyCandidate(array $storedColumns, array $parentColumns, int $indexKeyArity): bool
    {
        if ($indexKeyArity !== count($parentColumns)) {
            return false;
        }

        $remaining = array_map('strtolower', $parentColumns);
        foreach ($storedColumns as $column) {
            $position = array_search(strtolower($column), $remaining, true);
            if ($position === false) {
                return false;
            }
            unset($remaining[$position]);
        }

        return count($storedColumns) < count($parentColumns);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,blocked:int,expression_parent_key_unusable:int,expression_unique_index_unrelated:int,expression_key_columns:int}
     */
    private static function expressionCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'blocked' => 0,
            'expression_parent_key_unusable' => 0,
            'expression_unique_index_unrelated' => 0,
            'expression_key_columns' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'expression_parent_key_unusable') {
                $counts['expression_parent_key_unusable']++;
                $counts['blocked']++;
            } elseif ($status === 'expression_unique_index_unrelated') {
                $counts['expression_unique_index_unrelated']++;
            }
            $counts['expression_key_columns'] += (int) ($row['expression_key_columns'] ?? 0);
        }

        return $counts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function rowSummary(array $rows, bool $includePhase = true): array
    {
        $summary = array_map(
            static fn (array $row): string => ($includePhase ? $row['phase'] . ':' : '')
                . $row['table'] . '#' . $row['foreign_key_id']
                . '->' . $row['parent']
                . '(' . implode(',', $row['parent_columns']) . ')'
                . ':index=' . $row['index']
                . ':key=' . implode(',', $row['index_key_columns'])
                . ':expr=' . $row['expression_key_columns']
                . ':' . $row['status'],
            $rows,
        );
        sort($summary);

        return $summary;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next244 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
