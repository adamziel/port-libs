<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext251
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next251 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next251 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext248::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::expressionChildActionRows($currentRecords, 'current');
        $nextRows = self::expressionChildActionRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next251',
            'base' => $base['source_id'],
            'current_expression_child_actions' => self::rowSummary($currentRows),
            'next_expression_child_actions' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next251 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next251 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::expressionCounts($currentRows);
        $nextCounts = self::expressionCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next251',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_child_action_expression_index_source' => 'pragma_foreign_key_list_actions_plus_pragma_index_xinfo_expression_child_prefix',
                'foreign_key_child_action_expression_index' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_child_action_expression_index_source' => 'pragma_foreign_key_list_actions_plus_pragma_index_xinfo_expression_child_prefix',
                'foreign_key_child_action_expression_index' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_child_action_expression_index' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_child_action_expression_index' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_child_action_expression_index_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_child_action_expression_index_blockers' => $nextCounts['blocked'] - $currentCounts['blocked'],
                'foreign_key_child_action_expression_index_repaired' => $currentCounts['blocked'] > 0 && $nextCounts['blocked'] === 0,
                'foreign_key_child_action_expression_index_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-child-action-expression-index',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function expressionChildActionRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase)) as $group) {
            $first = $group[0];
            $table = (string) $first['table'];
            $onDelete = strtoupper((string) ($first['on_delete'] ?? 'NO ACTION'));
            $onUpdate = strtoupper((string) ($first['on_update'] ?? 'NO ACTION'));
            if ($onDelete === 'NO ACTION' && $onUpdate === 'NO ACTION') {
                continue;
            }

            $childColumns = array_map(static fn (array $row): string => (string) $row['from'], $group);
            $candidate = self::matchingExpressionChildIndex($catalog, $table, $childColumns);
            if ($candidate['index'] === null) {
                continue;
            }

            foreach ($group as $row) {
                $seq = (int) $row['seq'];
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_child_action_expression_index',
                    'table' => $table,
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => $seq,
                    'parent' => (string) $row['parent'],
                    'from' => (string) $row['from'],
                    'to' => (string) ($row['to'] ?? ''),
                    'on_delete' => $onDelete,
                    'on_update' => $onUpdate,
                    'child_columns' => $childColumns,
                    'child_index' => $candidate['index'],
                    'child_index_unique' => $candidate['unique'],
                    'child_index_partial' => $candidate['partial'],
                    'child_index_columns' => $candidate['columns'],
                    'expression_key_positions' => $candidate['expression_positions'],
                    'expression_key_count' => count($candidate['expression_positions']),
                    'matched_prefix_columns' => $candidate['matched_prefix_columns'],
                    'status' => 'expression_child_action_index',
                    'blocked' => true,
                    'message' => "foreign key {$table} action lookup cannot use child index {$candidate['index']} because PRAGMA index_xinfo reports expression key terms",
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
     * @param list<string> $childColumns
     * @return array{index:string|null,unique:int|null,partial:int|null,columns:list<string|null>,expression_positions:list<int>,matched_prefix_columns:int}
     */
    private static function matchingExpressionChildIndex(SQLitePragmaSchemaCatalog $catalog, string $table, array $childColumns): array
    {
        $wanted = array_map('strtolower', $childColumns);

        foreach ($catalog->indexList($table) as $index) {
            if ((int) ($index['partial'] ?? 0) !== 0) {
                continue;
            }

            $keyRows = array_values(array_filter(
                $catalog->indexXInfo((string) $index['name']),
                static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1,
            ));
            $columns = array_map(
                static fn (array $row): ?string => ($row['name'] ?? null) === null ? null : (string) $row['name'],
                $keyRows,
            );
            $expressionPositions = [];
            foreach ($keyRows as $position => $row) {
                if ((int) ($row['cid'] ?? 0) === -2 || ($row['name'] ?? null) === null) {
                    $expressionPositions[] = $position;
                }
            }
            if ($expressionPositions === []) {
                continue;
            }

            $nonExpressionPrefix = [];
            foreach ($columns as $column) {
                if ($column === null) {
                    break;
                }
                $nonExpressionPrefix[] = strtolower($column);
            }
            $matched = self::commonPrefixLength($nonExpressionPrefix, $wanted);
            $expressionInterruptsChildKey = count(array_filter(
                $expressionPositions,
                static fn (int $position): bool => $position < count($wanted),
            )) > 0;
            if ($matched === 0 || $matched >= count($wanted) || !$expressionInterruptsChildKey) {
                continue;
            }

            return [
                'index' => (string) $index['name'],
                'unique' => (int) ($index['unique'] ?? 0),
                'partial' => (int) ($index['partial'] ?? 0),
                'columns' => $columns,
                'expression_positions' => $expressionPositions,
                'matched_prefix_columns' => $matched,
            ];
        }

        return [
            'index' => null,
            'unique' => null,
            'partial' => null,
            'columns' => [],
            'expression_positions' => [],
            'matched_prefix_columns' => 0,
        ];
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private static function commonPrefixLength(array $left, array $right): int
    {
        $count = 0;
        $limit = min(count($left), count($right));
        for ($i = 0; $i < $limit; $i++) {
            if ($left[$i] !== $right[$i]) {
                break;
            }
            $count++;
        }

        return $count;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,blocked:int,expression_child_action_index:int,cascade:int,set_null:int,set_default:int,restrict:int,composite_columns:int,expression_key_terms:int,matched_prefix_columns:int}
     */
    private static function expressionCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'blocked' => 0,
            'expression_child_action_index' => 0,
            'cascade' => 0,
            'set_null' => 0,
            'set_default' => 0,
            'restrict' => 0,
            'composite_columns' => 0,
            'expression_key_terms' => 0,
            'matched_prefix_columns' => 0,
        ];

        foreach ($rows as $row) {
            $counts['blocked']++;
            $counts['expression_child_action_index']++;
            foreach ([(string) $row['on_delete'], (string) $row['on_update']] as $action) {
                if ($action === 'CASCADE') {
                    $counts['cascade']++;
                } elseif ($action === 'SET NULL') {
                    $counts['set_null']++;
                } elseif ($action === 'SET DEFAULT') {
                    $counts['set_default']++;
                } elseif ($action === 'RESTRICT') {
                    $counts['restrict']++;
                }
            }
            if (count((array) ($row['child_columns'] ?? [])) > 1) {
                $counts['composite_columns']++;
            }
            $counts['expression_key_terms'] += (int) ($row['expression_key_count'] ?? 0);
            $counts['matched_prefix_columns'] += (int) ($row['matched_prefix_columns'] ?? 0);
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
                (string) $row['on_update'] . '/' . (string) $row['on_delete'],
                'index=' . (string) ($row['child_index'] ?? 'none'),
                'expr=' . implode(',', array_map('strval', (array) ($row['expression_key_positions'] ?? []))),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next251 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
