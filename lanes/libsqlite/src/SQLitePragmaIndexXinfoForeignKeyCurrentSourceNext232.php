<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext232
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next232 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next232 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext229::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::childActionPrefixRows($currentRecords, 'current');
        $nextRows = self::childActionPrefixRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next232',
            'base' => $base['source_id'],
            'current_child_action_prefix' => self::rowSummary($currentRows),
            'next_child_action_prefix' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next232 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next232 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::prefixCounts($currentRows);
        $nextCounts = self::prefixCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next232',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_child_action_prefix_source' => 'pragma_foreign_key_list_actions_plus_pragma_index_xinfo_leftmost_child_prefix',
                'foreign_key_child_action_prefix' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_child_action_prefix_source' => 'pragma_foreign_key_list_actions_plus_pragma_index_xinfo_leftmost_child_prefix',
                'foreign_key_child_action_prefix' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_child_action_prefix' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_child_action_prefix' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_child_action_prefix_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_child_action_prefix_blockers' => $nextCounts['blocked'] - $currentCounts['blocked'],
                'foreign_key_child_action_prefix_repaired' => $currentCounts['blocked'] > 0 && $nextCounts['blocked'] === 0,
                'foreign_key_child_action_prefix_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-child-leftmost-prefix-index',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function childActionPrefixRows(array $records, string $phase = 'current'): array
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
            $candidate = self::matchingChildPrefixIndex($catalog, $table, $childColumns);
            foreach ($group as $row) {
                $seq = (int) $row['seq'];
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_child_action_prefix',
                    'table' => $table,
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => $seq,
                    'parent' => (string) $row['parent'],
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'on_delete' => $onDelete,
                    'on_update' => $onUpdate,
                    'child_columns' => $childColumns,
                    'leftmost_child_index' => $candidate['leftmost_index'],
                    'misordered_child_index' => $candidate['misordered_index'],
                    'misordered_index_columns' => $candidate['misordered_columns'],
                    'matched_prefix_columns' => $candidate['matched_prefix_columns'],
                    'status' => $candidate['status'],
                    'message' => match ($candidate['status']) {
                        'ok' => "foreign key {$table} action lookup can use leftmost child index {$candidate['leftmost_index']}",
                        'misordered_child_action_index' => "foreign key {$table} action lookup has child columns in {$candidate['misordered_index']} but not as the leftmost prefix",
                        default => "foreign key {$table} action lookup has no child index containing all FK columns",
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
     * @param list<string> $childColumns
     * @return array{status:string,leftmost_index:string|null,misordered_index:string|null,misordered_columns:list<string>,matched_prefix_columns:int}
     */
    private static function matchingChildPrefixIndex(SQLitePragmaSchemaCatalog $catalog, string $table, array $childColumns): array
    {
        $wanted = array_map('strtolower', $childColumns);
        $misorderedIndex = null;
        $misorderedColumns = [];
        $matchedPrefixColumns = 0;

        foreach ($catalog->indexList($table) as $index) {
            if ((int) ($index['partial'] ?? 0) !== 0) {
                continue;
            }

            $indexName = (string) $index['name'];
            $keyRows = array_values(array_filter(
                $catalog->indexXInfo($indexName),
                static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1,
            ));
            $indexColumns = array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $keyRows);
            $foldedColumns = array_map('strtolower', $indexColumns);
            $prefix = array_slice($foldedColumns, 0, count($wanted));
            $matchedPrefixColumns = max($matchedPrefixColumns, self::commonPrefixLength($prefix, $wanted));
            if ($prefix === $wanted) {
                return [
                    'status' => 'ok',
                    'leftmost_index' => $indexName,
                    'misordered_index' => $misorderedIndex,
                    'misordered_columns' => $misorderedColumns,
                    'matched_prefix_columns' => count($wanted),
                ];
            }

            $present = array_intersect($wanted, $foldedColumns);
            if (count($present) === count($wanted) && $misorderedIndex === null) {
                $misorderedIndex = $indexName;
                $misorderedColumns = $indexColumns;
            }
        }

        return [
            'status' => $misorderedIndex === null ? 'missing_child_action_index' : 'misordered_child_action_index',
            'leftmost_index' => null,
            'misordered_index' => $misorderedIndex,
            'misordered_columns' => $misorderedColumns,
            'matched_prefix_columns' => $matchedPrefixColumns,
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
     * @return array{rows:int,ok:int,blocked:int,misordered_child_action_index:int,missing_child_action_index:int,cascade:int,set_null:int,set_default:int,restrict:int,composite_columns:int,matched_prefix_columns:int}
     */
    private static function prefixCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'ok' => 0,
            'blocked' => 0,
            'misordered_child_action_index' => 0,
            'missing_child_action_index' => 0,
            'cascade' => 0,
            'set_null' => 0,
            'set_default' => 0,
            'restrict' => 0,
            'composite_columns' => 0,
            'matched_prefix_columns' => 0,
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
            foreach (['on_delete', 'on_update'] as $actionKey) {
                $action = strtoupper((string) ($row[$actionKey] ?? 'NO ACTION'));
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
            if ((int) ($row['seq'] ?? 0) > 0) {
                $counts['composite_columns']++;
            }
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
                'child=' . implode(',', (array) ($row['child_columns'] ?? [])),
                'leftmost=' . (string) ($row['leftmost_child_index'] ?? ''),
                'misordered=' . (string) ($row['misordered_child_index'] ?? ''),
                'index=' . implode(',', (array) ($row['misordered_index_columns'] ?? [])),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next232 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
