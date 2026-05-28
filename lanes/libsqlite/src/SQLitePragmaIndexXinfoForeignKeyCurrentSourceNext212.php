<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext212
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next212 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next212 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext209::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::childActionLookupRows($currentRecords, 'current');
        $nextRows = self::childActionLookupRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next212',
            'base' => $base['source_id'],
            'current_child_action_lookup' => self::rowSummary($currentRows),
            'next_child_action_lookup' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next212 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next212 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::actionLookupCounts($currentRows);
        $nextCounts = self::actionLookupCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next212',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_child_action_lookup_source' => 'pragma_foreign_key_list_actions_plus_pragma_index_xinfo_nonpartial_child_prefix',
                'foreign_key_child_action_lookup' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_child_action_lookup_source' => 'pragma_foreign_key_list_actions_plus_pragma_index_xinfo_nonpartial_child_prefix',
                'foreign_key_child_action_lookup' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_child_action_lookup' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_child_action_lookup' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_child_action_lookup_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_child_action_lookup_blockers' => $nextCounts['blocked'] - $currentCounts['blocked'],
                'foreign_key_child_action_lookup_repaired' => $currentCounts['blocked'] > 0 && $nextCounts['blocked'] === 0,
                'foreign_key_child_action_lookup_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-child-action-nonpartial-index',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function childActionLookupRows(array $records, string $phase = 'current'): array
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
            $candidate = self::matchingChildActionIndex($catalog, $records, $table, $childColumns);
            $status = match (true) {
                $candidate['full_index'] !== null => 'ok',
                $candidate['partial_index'] !== null => 'partial_child_action_index',
                default => 'missing_child_action_index',
            };

            foreach ($group as $row) {
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_child_action_lookup',
                    'table' => $table,
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => (int) $row['seq'],
                    'parent' => (string) $row['parent'],
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'on_delete' => $onDelete,
                    'on_update' => $onUpdate,
                    'child_columns' => $childColumns,
                    'full_child_index' => $candidate['full_index'],
                    'partial_child_index' => $candidate['partial_index'],
                    'partial_child_index_where' => $candidate['partial_where'],
                    'covered_prefix_columns' => $candidate['covered_prefix_columns'],
                    'status' => $status,
                    'message' => $status === 'ok'
                        ? "foreign key {$table} action lookup can use non-partial child index {$candidate['full_index']}"
                        : ($status === 'partial_child_action_index'
                            ? "foreign key {$table} action lookup only has partial child index {$candidate['partial_index']}"
                            : "foreign key {$table} action lookup has no PRAGMA index_xinfo child prefix"),
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
     * @param list<SQLiteSchemaRecord> $records
     * @param list<string> $childColumns
     * @return array{full_index:string|null,partial_index:string|null,partial_where:string|null,covered_prefix_columns:int}
     */
    private static function matchingChildActionIndex(SQLitePragmaSchemaCatalog $catalog, array $records, string $table, array $childColumns): array
    {
        $partial = null;
        $partialWhere = null;
        $coveredPrefixColumns = 0;
        foreach ($catalog->indexList($table) as $index) {
            $indexName = (string) $index['name'];
            $xinfo = $catalog->indexXInfo($indexName);
            $keyRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1));
            $prefix = array_slice($keyRows, 0, count($childColumns));
            $prefixColumns = array_map(static fn (array $row): string => strtolower((string) ($row['name'] ?? '')), $prefix);
            if ($prefixColumns !== array_map('strtolower', $childColumns)) {
                continue;
            }

            $coveredPrefixColumns = max($coveredPrefixColumns, count($prefix));
            if ((int) ($index['partial'] ?? 0) === 0) {
                return [
                    'full_index' => $indexName,
                    'partial_index' => $partial,
                    'partial_where' => $partialWhere,
                    'covered_prefix_columns' => count($prefix),
                ];
            }

            $partial ??= $indexName;
            $partialWhere ??= self::partialWhereClause($records, $indexName);
        }

        return [
            'full_index' => null,
            'partial_index' => $partial,
            'partial_where' => $partialWhere,
            'covered_prefix_columns' => $coveredPrefixColumns,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function partialWhereClause(array $records, string $indexName): ?string
    {
        foreach ($records as $record) {
            if ($record->type !== 'index' || strcasecmp($record->name, $indexName) !== 0 || $record->sql === null) {
                continue;
            }
            if (preg_match('/\bWHERE\b\s*(?<where>.+)$/is', $record->sql, $matches) === 1) {
                return trim((string) $matches['where']);
            }
        }

        return null;
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
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,ok:int,blocked:int,partial_child_action_index:int,missing_child_action_index:int,cascade:int,set_null:int,set_default:int,restrict:int}
     */
    private static function actionLookupCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'ok' => 0,
            'blocked' => 0,
            'partial_child_action_index' => 0,
            'missing_child_action_index' => 0,
            'cascade' => 0,
            'set_null' => 0,
            'set_default' => 0,
            'restrict' => 0,
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
                $action = strtoupper((string) ($row[$actionKey] ?? ''));
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
                (string) $row['on_delete'] . '/' . (string) $row['on_update'],
                (string) ($row['full_child_index'] ?? 'missing-full'),
                (string) ($row['partial_child_index'] ?? 'missing-partial'),
                (string) ($row['status'] ?? ''),
            ], static fn (?string $part): bool => $part !== null)),
            $rows,
        );
        sort($summary);

        return $summary;
    }

    /**
     * @param list<mixed> $records
     */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next212 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
