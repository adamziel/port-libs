<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext170
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
        int $limit = 170,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        $currentForeignKeys = self::foreignKeysFromCatalog($currentRecords);
        $nextForeignKeys = self::foreignKeysFromCatalog($nextRecords);

        $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext156::currentNextPage(
            $currentRecords,
            $currentForeignKeys,
            $currentTables,
            $nextRecords,
            $nextForeignKeys,
            $nextTables,
            $indexXinfoSql,
            $offset,
            $limit,
            $cursor,
            $tableValuedIndexXinfo,
        );

        $currentMap = self::foreignKeyMap($currentForeignKeys);
        $nextMap = self::foreignKeyMap($nextForeignKeys);
        $rows = array_map(
            static function (array $row) use ($currentMap, $nextMap): array {
                if (($row['kind'] ?? null) !== 'foreign_key_check') {
                    return $row;
                }

                $map = ($row['side'] ?? 'current') === 'next' ? $nextMap : $currentMap;
                $foreignKey = $map[self::foreignKeyKey((string) ($row['table'] ?? ''), (int) ($row['fkid'] ?? -1))] ?? null;
                if ($foreignKey === null) {
                    return [
                        ...$row,
                        'constraint_timing' => 'immediate',
                        'deferred_until_commit' => false,
                        'fk_action_summary' => 'NO ACTION/NO ACTION/NONE',
                    ];
                }

                $deferred = ($foreignKey['deferrable'] ?? false) === true && ($foreignKey['initially_deferred'] ?? false) === true;

                return [
                    ...$row,
                    'constraint_timing' => $deferred ? 'deferred' : 'immediate',
                    'deferred_until_commit' => $deferred,
                    'on_update' => (string) ($foreignKey['on_update'] ?? 'NO ACTION'),
                    'on_delete' => (string) ($foreignKey['on_delete'] ?? 'NO ACTION'),
                    'match' => (string) ($foreignKey['match'] ?? 'NONE'),
                    'fk_action_summary' => (string) ($foreignKey['on_update'] ?? 'NO ACTION') . '/' . (string) ($foreignKey['on_delete'] ?? 'NO ACTION') . '/' . (string) ($foreignKey['match'] ?? 'NONE'),
                ];
            },
            $page['rows'],
        );

        $currentTiming = self::timingSummary($currentForeignKeys, $currentTables);
        $nextTiming = self::timingSummary($nextForeignKeys, $nextTables);
        $blocking = self::blocking($page['next_counts'], $nextTiming);

        return [
            ...$page,
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'current_source' => [
                ...$page['current_source'],
                'foreign_key_source' => 'pragma_foreign_key_list_deferrable_violations',
                'derived_foreign_keys' => count($currentForeignKeys),
                'foreign_key_timing' => self::sourceTiming($currentForeignKeys),
            ],
            'next_source' => [
                ...$page['next_source'],
                'foreign_key_source' => 'pragma_foreign_key_list_deferrable_violations',
                'derived_foreign_keys' => count($nextForeignKeys),
                'foreign_key_timing' => self::sourceTiming($nextForeignKeys),
            ],
            'current' => [
                ...$page['current'],
                ...$currentTiming,
            ],
            'next_counts' => [
                ...$page['next_counts'],
                ...$nextTiming,
            ],
            'delta' => [
                ...$page['delta'],
                'immediate_foreign_key_violations' => $nextTiming['immediate_foreign_key_violations'] - $currentTiming['immediate_foreign_key_violations'],
                'deferred_foreign_key_violations' => $nextTiming['deferred_foreign_key_violations'] - $currentTiming['deferred_foreign_key_violations'],
                'deferred_cleared' => $currentTiming['deferred_foreign_key_violations'] > 0 && $nextTiming['deferred_foreign_key_violations'] === 0,
            ],
            'next_state' => [
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function foreignKeysFromCatalog(array $records): array
    {
        return SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext166::foreignKeysFromCatalog($records);
    }

    /**
     * @param list<array<string,mixed>> $foreignKeys
     * @return array<string,array<string,mixed>>
     */
    private static function foreignKeyMap(array $foreignKeys): array
    {
        $map = [];
        foreach ($foreignKeys as $foreignKey) {
            $map[self::foreignKeyKey((string) ($foreignKey['table'] ?? ''), (int) ($foreignKey['id'] ?? -1))] = $foreignKey;
        }

        return $map;
    }

    private static function foreignKeyKey(string $table, int $id): string
    {
        return strtolower($table) . '#' . $id;
    }

    /**
     * @param list<array<string,mixed>> $foreignKeys
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{immediate_foreign_key_violations:int,deferred_foreign_key_violations:int,commit_blocking_foreign_key_violations:int,deferred_foreign_key_constraints:int,immediate_foreign_key_constraints:int}
     */
    private static function timingSummary(array $foreignKeys, array $tables): array
    {
        $summary = [
            'immediate_foreign_key_violations' => 0,
            'deferred_foreign_key_violations' => 0,
            'commit_blocking_foreign_key_violations' => 0,
            'deferred_foreign_key_constraints' => 0,
            'immediate_foreign_key_constraints' => 0,
        ];

        foreach ($foreignKeys as $foreignKey) {
            $deferred = ($foreignKey['deferrable'] ?? false) === true && ($foreignKey['initially_deferred'] ?? false) === true;
            if ($deferred) {
                $summary['deferred_foreign_key_constraints']++;
            } else {
                $summary['immediate_foreign_key_constraints']++;
            }

            $violations = SQLitePragmaForeignKeyCheck::check($tables, [$foreignKey]);
            if ($deferred) {
                $summary['deferred_foreign_key_violations'] += count($violations);
            } else {
                $summary['immediate_foreign_key_violations'] += count($violations);
            }
        }

        $summary['commit_blocking_foreign_key_violations'] = $summary['immediate_foreign_key_violations'] + $summary['deferred_foreign_key_violations'];

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $foreignKeys
     * @return array{deferred:int,immediate:int,initially_deferred:int,deferrable:int}
     */
    private static function sourceTiming(array $foreignKeys): array
    {
        $summary = [
            'deferred' => 0,
            'immediate' => 0,
            'initially_deferred' => 0,
            'deferrable' => 0,
        ];

        foreach ($foreignKeys as $foreignKey) {
            $deferrable = ($foreignKey['deferrable'] ?? false) === true;
            $initiallyDeferred = ($foreignKey['initially_deferred'] ?? false) === true;
            if ($deferrable) {
                $summary['deferrable']++;
            }
            if ($initiallyDeferred) {
                $summary['initially_deferred']++;
            }
            if ($deferrable && $initiallyDeferred) {
                $summary['deferred']++;
            } else {
                $summary['immediate']++;
            }
        }

        return $summary;
    }

    /**
     * @param array<string,mixed> $nextCounts
     * @param array{immediate_foreign_key_violations:int,deferred_foreign_key_violations:int} $timing
     * @return list<string>
     */
    private static function blocking(array $nextCounts, array $timing): array
    {
        $blocking = [];
        if (($nextCounts['index_blockers'] ?? 0) > 0) {
            $blocking[] = 'foreign_key_parent_unique_index';
        }
        if ($timing['immediate_foreign_key_violations'] > 0) {
            $blocking[] = 'immediate_foreign_key_check';
        }
        if ($timing['deferred_foreign_key_violations'] > 0) {
            $blocking[] = 'deferred_foreign_key_check';
        }

        return $blocking;
    }
}
