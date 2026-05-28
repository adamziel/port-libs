<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext234
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next234 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next234 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext232::page(
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
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next234',
            'base' => $base['source_id'],
            'current_expression_parent_key' => self::rowSummary($currentRows),
            'next_expression_parent_key' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next234 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next234 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::expressionCounts($currentRows);
        $nextCounts = self::expressionCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next234',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_expression_parent_key_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_expression_terms',
                'foreign_key_expression_parent_key' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_expression_parent_key_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_expression_terms',
                'foreign_key_expression_parent_key' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_expression_parent_key' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_expression_parent_key' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_expression_parent_key_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_expression_parent_key_blockers' => $nextCounts['blocked'] - $currentCounts['blocked'],
                'foreign_key_expression_parent_key_repaired' => $currentCounts['blocked'] > 0 && $nextCounts['blocked'] === 0,
                'foreign_key_expression_parent_key_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-expression-parent-index-rejection',
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
        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase)) as $group) {
            $parent = (string) $group[0]['parent'];
            $parentColumns = array_map(static fn (array $row): string => (string) $row['to'], $group);
            if (in_array('', $parentColumns, true)) {
                continue;
            }

            $candidate = self::matchingExpressionParentKey($catalog, $parent, $parentColumns);
            foreach ($group as $row) {
                $seq = (int) $row['seq'];
                $indexRow = $candidate['rows'][$seq] ?? null;
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_expression_parent_key',
                    'table' => (string) $row['table'],
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => $seq,
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'parent_columns' => $parentColumns,
                    'parent_unique_index' => $candidate['index'],
                    'expression_unique_index' => $candidate['expression_index'],
                    'expression_key_count' => $candidate['expression_key_count'],
                    'expression_positions' => $candidate['expression_positions'],
                    'index_column' => $indexRow['name'] ?? null,
                    'index_column_cid' => $indexRow['cid'] ?? null,
                    'index_column_is_expression' => ((int) ($indexRow['cid'] ?? 0)) === -2 && ($indexRow['name'] ?? null) === null,
                    'status' => $candidate['status'],
                    'message' => match ($candidate['status']) {
                        'ok' => "foreign key {$row['table']}->{$parent} parent key uses column UNIQUE index {$candidate['index']}",
                        'expression_parent_unique_index' => "foreign key {$row['table']}->{$parent} cannot use expression UNIQUE index {$candidate['expression_index']} as a parent key",
                        default => "foreign key {$row['table']}->{$parent} has no column UNIQUE parent key",
                    },
                ];
            }
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['phase'], $left['table'], $left['foreign_key_id'], $left['seq']]
                <=> [$right['phase'], $right['table'], $right['foreign_key_id'], $right['seq']],
        );

        return $rows;
    }

    /**
     * @param list<string> $parentColumns
     * @return array{status:string,index:string|null,expression_index:string|null,rows:list<array<string,mixed>>,expression_key_count:int,expression_positions:list<int>}
     */
    private static function matchingExpressionParentKey(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns): array
    {
        $wanted = array_map('strtolower', $parentColumns);
        $expression = [
            'index' => null,
            'rows' => [],
            'positions' => [],
        ];

        foreach ($catalog->indexList($parent) as $index) {
            if ((int) ($index['unique'] ?? 0) !== 1 || (int) ($index['partial'] ?? 0) !== 0) {
                continue;
            }

            $indexName = (string) $index['name'];
            $keyRows = array_values(array_filter(
                $catalog->indexXInfo($indexName),
                static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1,
            ));
            if (count($keyRows) !== count($wanted)) {
                continue;
            }

            $hasExpression = false;
            $positions = [];
            foreach ($keyRows as $position => $row) {
                if ((int) ($row['cid'] ?? 0) === -2 && ($row['name'] ?? null) === null) {
                    $hasExpression = true;
                    $positions[] = $position;
                }
            }
            if ($hasExpression) {
                if ($expression['index'] === null) {
                    $expression = [
                        'index' => $indexName,
                        'rows' => $keyRows,
                        'positions' => $positions,
                    ];
                }
                continue;
            }

            $columns = array_map(static fn (array $row): string => strtolower((string) ($row['name'] ?? '')), $keyRows);
            if ($columns === $wanted) {
                return [
                    'status' => 'ok',
                    'index' => $indexName,
                    'expression_index' => $expression['index'],
                    'rows' => $keyRows,
                    'expression_key_count' => 0,
                    'expression_positions' => [],
                ];
            }
        }

        return [
            'status' => $expression['index'] === null ? 'missing_parent_unique_index' : 'expression_parent_unique_index',
            'index' => null,
            'expression_index' => $expression['index'],
            'rows' => $expression['rows'],
            'expression_key_count' => count($expression['positions']),
            'expression_positions' => $expression['positions'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,ok:int,blocked:int,expression_parent_unique_index:int,missing_parent_unique_index:int,expression_terms:int,composite_columns:int}
     */
    private static function expressionCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'ok' => 0,
            'blocked' => 0,
            'expression_parent_unique_index' => 0,
            'missing_parent_unique_index' => 0,
            'expression_terms' => 0,
            'composite_columns' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'ok') {
                $counts['ok']++;
            } else {
                $counts['blocked']++;
                if (isset($counts[$status])) {
                    $counts[$status]++;
                }
            }
            if (($row['index_column_is_expression'] ?? false) === true) {
                $counts['expression_terms']++;
            }
            if ((int) ($row['seq'] ?? 0) > 0) {
                $counts['composite_columns']++;
            }
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
            static fn (array $row): string => implode(':', array_filter([
                $includePhase ? (string) $row['phase'] : null,
                (string) $row['table'] . '#' . (int) $row['foreign_key_id'] . '.' . (int) $row['seq'],
                (string) $row['from'] . '->' . (string) $row['parent'] . '.' . (string) $row['to'],
                'column=' . (string) ($row['parent_unique_index'] ?? ''),
                'expression=' . (string) ($row['expression_unique_index'] ?? ''),
                ($row['index_column_is_expression'] ?? false) ? 'cid=-2' : 'cid=' . (string) ($row['index_column_cid'] ?? ''),
                (string) ($row['status'] ?? ''),
            ], static fn (?string $part): bool => $part !== null)),
            $rows,
        );
        sort($summary);

        return $summary;
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
     * @param list<mixed> $records
     */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next234 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
