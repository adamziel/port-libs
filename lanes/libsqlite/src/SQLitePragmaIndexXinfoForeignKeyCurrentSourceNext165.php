<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext165
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
        int $limit = 165,
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

        $currentActions = self::actionMap($currentForeignKeys);
        $nextActions = self::actionMap($nextForeignKeys);
        $page['rows'] = array_map(
            static function (array $row) use ($currentActions, $nextActions): array {
                if (($row['kind'] ?? null) !== 'index_admission' && ($row['kind'] ?? null) !== 'foreign_key_check') {
                    return $row;
                }

                $map = ($row['side'] ?? 'current') === 'next' ? $nextActions : $currentActions;
                $key = self::actionKey((string) ($row['table'] ?? ''), (int) ($row['fkid'] ?? -1));
                $action = $map[$key] ?? null;
                if ($action === null) {
                    return $row;
                }

                return [
                    ...$row,
                    'on_update' => $action['on_update'],
                    'on_delete' => $action['on_delete'],
                    'match' => $action['match'],
                    'action_summary' => $action['on_update'] . '/' . $action['on_delete'] . '/' . $action['match'],
                ];
            },
            $page['rows'],
        );

        return [
            ...$page,
            'current_source' => [
                ...$page['current_source'],
                'foreign_key_source' => 'pragma_foreign_key_list',
                'action_source' => 'pragma_foreign_key_list_actions',
                'derived_foreign_keys' => count($currentForeignKeys),
                'action_summary' => self::actionSummary($currentForeignKeys),
            ],
            'next_source' => [
                ...$page['next_source'],
                'foreign_key_source' => 'pragma_foreign_key_list',
                'action_source' => 'pragma_foreign_key_list_actions',
                'derived_foreign_keys' => count($nextForeignKeys),
                'action_summary' => self::actionSummary($nextForeignKeys),
            ],
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function foreignKeysFromCatalog(array $records): array
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next165 records must be SQLiteSchemaRecord instances');
            }
        }

        $foreignKeys = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext161::foreignKeysFromCatalog($records);
        $actions = self::actionsFromCatalog($records);

        return array_map(
            static function (array $foreignKey) use ($actions): array {
                $key = self::actionKey((string) ($foreignKey['table'] ?? ''), (int) ($foreignKey['id'] ?? -1));
                $action = $actions[$key] ?? ['on_update' => 'NO ACTION', 'on_delete' => 'NO ACTION', 'match' => 'NONE'];

                return [
                    ...$foreignKey,
                    'on_update' => $action['on_update'],
                    'on_delete' => $action['on_delete'],
                    'match' => $action['match'],
                ];
            },
            $foreignKeys,
        );
    }

    /**
     * @param list<array<string,mixed>> $foreignKeys
     * @return array<string,array{on_update:string,on_delete:string,match:string}>
     */
    private static function actionMap(array $foreignKeys): array
    {
        $map = [];
        foreach ($foreignKeys as $foreignKey) {
            $map[self::actionKey((string) ($foreignKey['table'] ?? ''), (int) ($foreignKey['id'] ?? -1))] = [
                'on_update' => self::action((string) ($foreignKey['on_update'] ?? 'NO ACTION')),
                'on_delete' => self::action((string) ($foreignKey['on_delete'] ?? 'NO ACTION')),
                'match' => self::matchName((string) ($foreignKey['match'] ?? 'NONE')),
            ];
        }

        return $map;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,array{on_update:string,on_delete:string,match:string}>
     */
    private static function actionsFromCatalog(array $records): array
    {
        $catalog = new SQLitePragmaSchemaCatalog($records);
        $actions = [];

        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next165 records must be SQLiteSchemaRecord instances');
            }
            if ($record->type !== 'table') {
                continue;
            }

            foreach ($catalog->execute('PRAGMA foreign_key_list(' . self::pragmaArgumentLiteral($record->name) . ')')['rows'] as $row) {
                $key = self::actionKey($record->name, (int) $row['id']);
                $actions[$key] ??= [
                    'on_update' => self::action((string) $row['on_update']),
                    'on_delete' => self::action((string) $row['on_delete']),
                    'match' => self::matchName((string) $row['match']),
                ];
            }
        }

        return $actions;
    }

    /**
     * @param list<array<string,mixed>> $foreignKeys
     * @return array{on_update:array<string,int>,on_delete:array<string,int>,match:array<string,int>}
     */
    private static function actionSummary(array $foreignKeys): array
    {
        $summary = ['on_update' => [], 'on_delete' => [], 'match' => []];
        foreach ($foreignKeys as $foreignKey) {
            self::increment($summary['on_update'], self::action((string) ($foreignKey['on_update'] ?? 'NO ACTION')));
            self::increment($summary['on_delete'], self::action((string) ($foreignKey['on_delete'] ?? 'NO ACTION')));
            self::increment($summary['match'], self::matchName((string) ($foreignKey['match'] ?? 'NONE')));
        }
        ksort($summary['on_update']);
        ksort($summary['on_delete']);
        ksort($summary['match']);

        return $summary;
    }

    /** @param array<string,int> $counts */
    private static function increment(array &$counts, string $key): void
    {
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    private static function actionKey(string $table, int $id): string
    {
        return strtolower($table) . '#' . $id;
    }

    private static function action(string $action): string
    {
        $normalized = strtoupper(preg_replace('/\s+/', ' ', trim($action)) ?? trim($action));
        return $normalized === '' ? 'NO ACTION' : $normalized;
    }

    private static function matchName(string $match): string
    {
        $normalized = strtoupper(trim($match));
        return $normalized === '' ? 'NONE' : $normalized;
    }

    private static function pragmaArgumentLiteral(string $identifier): string
    {
        return "'" . str_replace("'", "''", $identifier) . "'";
    }
}
