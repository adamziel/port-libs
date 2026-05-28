<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext167
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
        int $limit = 167,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        $currentForeignKeys = self::foreignKeysFromCatalog($currentRecords);
        $nextForeignKeys = self::foreignKeysFromCatalog($nextRecords);
        $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext164::currentNextPageFromCatalog(
            $currentRecords,
            $currentTables,
            $nextRecords,
            $nextTables,
            $indexXinfoSql,
            $offset,
            $limit,
            null,
            $tableValuedIndexXinfo,
        );

        $currentActions = self::actionRows($currentForeignKeys);
        $nextActions = self::actionRows($nextForeignKeys);
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next167',
            'base' => $page['source_id'],
            'current_actions' => self::actionSummary($currentActions),
            'next_actions' => self::actionSummary($nextActions),
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $currentActionMap = self::actionMap($currentActions);
        $nextActionMap = self::actionMap($nextActions);
        $rows = array_map(
            static fn (array $row): array => self::decorateRow($row, $row['side'] === 'current' ? $currentActionMap : $nextActionMap),
            $page['rows'],
        );

        return [
            ...$page,
            'source_id' => $sourceId,
            'current_source' => [
                ...$page['current_source'],
                'foreign_key_action_source' => 'pragma_foreign_key_list_actions',
                'foreign_key_actions' => self::actionSummary($currentActions),
            ],
            'next_source' => [
                ...$page['next_source'],
                'foreign_key_action_source' => 'pragma_foreign_key_list_actions',
                'foreign_key_actions' => self::actionSummary($nextActions),
            ],
            'current' => [
                ...$page['current'],
                'foreign_key_actions' => self::actionCounts($currentActions),
            ],
            'next_counts' => [
                ...$page['next_counts'],
                'foreign_key_actions' => self::actionCounts($nextActions),
            ],
            'delta' => [
                ...$page['delta'],
                'foreign_key_action_changes' => self::actionChangeCount($currentActions, $nextActions),
                'foreign_key_action_changed' => self::actionSummary($currentActions) !== self::actionSummary($nextActions),
            ],
            'next' => $page['next'] === null ? null : [
                'source_id' => $sourceId,
                'offset' => $page['next']['offset'],
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
        $foreignKeys = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext161::foreignKeysFromCatalog($records);
        $catalog = new SQLitePragmaSchemaCatalog($records);
        $actions = [];

        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next167 records must be SQLiteSchemaRecord instances');
            }
            if ($record->type !== 'table') {
                continue;
            }
            foreach ($catalog->execute('PRAGMA foreign_key_list(' . self::pragmaArgumentLiteral($record->name) . ')')['rows'] as $row) {
                $actions[self::actionKey($record->name, (int) $row['id'])] = [
                    'on_update' => (string) $row['on_update'],
                    'on_delete' => (string) $row['on_delete'],
                    'match' => (string) $row['match'],
                ];
            }
        }

        return array_map(
            static function (array $foreignKey) use ($actions): array {
                $action = $actions[self::actionKey((string) $foreignKey['table'], (int) $foreignKey['id'])] ?? [
                    'on_update' => 'NO ACTION',
                    'on_delete' => 'NO ACTION',
                    'match' => 'NONE',
                ];

                return [
                    ...$foreignKey,
                    ...$action,
                ];
            },
            $foreignKeys,
        );
    }

    /**
     * @param list<array<string,mixed>> $foreignKeys
     * @return list<array{table:string,parent:string,fkid:int,on_update:string,on_delete:string,match:string}>
     */
    private static function actionRows(array $foreignKeys): array
    {
        $rows = [];
        foreach ($foreignKeys as $foreignKey) {
            $rows[] = [
                'table' => (string) $foreignKey['table'],
                'parent' => (string) $foreignKey['parent'],
                'fkid' => (int) $foreignKey['id'],
                'on_update' => (string) ($foreignKey['on_update'] ?? 'NO ACTION'),
                'on_delete' => (string) ($foreignKey['on_delete'] ?? 'NO ACTION'),
                'match' => (string) ($foreignKey['match'] ?? 'NONE'),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array{table:string,parent:string,fkid:int,on_update:string,on_delete:string,match:string}> $actions
     * @return array<string,array{on_update:string,on_delete:string,match:string}>
     */
    private static function actionMap(array $actions): array
    {
        $map = [];
        foreach ($actions as $action) {
            $map[self::actionKey($action['table'], $action['fkid'])] = [
                'on_update' => $action['on_update'],
                'on_delete' => $action['on_delete'],
                'match' => $action['match'],
            ];
        }

        return $map;
    }

    /**
     * @param array<string,array{on_update:string,on_delete:string,match:string}> $actions
     * @return array<string,mixed>
     */
    private static function decorateRow(array $row, array $actions): array
    {
        if (($row['kind'] ?? null) !== 'index_admission' && ($row['kind'] ?? null) !== 'foreign_key_check') {
            return $row;
        }

        $action = $actions[self::actionKey((string) ($row['table'] ?? ''), (int) ($row['fkid'] ?? -1))] ?? null;
        if ($action === null) {
            return $row;
        }

        return [
            ...$row,
            'on_update' => $action['on_update'],
            'on_delete' => $action['on_delete'],
            'match' => $action['match'],
        ];
    }

    /**
     * @param list<array{table:string,parent:string,fkid:int,on_update:string,on_delete:string,match:string}> $actions
     * @return list<string>
     */
    private static function actionSummary(array $actions): array
    {
        $summary = array_map(
            static fn (array $action): string => $action['table'] . '#' . $action['fkid'] . '->' . $action['parent'] . ':update=' . $action['on_update'] . ',delete=' . $action['on_delete'] . ',match=' . $action['match'],
            $actions,
        );
        sort($summary);

        return $summary;
    }

    /**
     * @param list<array{table:string,parent:string,fkid:int,on_update:string,on_delete:string,match:string}> $actions
     * @return array<string,int>
     */
    private static function actionCounts(array $actions): array
    {
        $counts = [];
        foreach ($actions as $action) {
            foreach (['on_update', 'on_delete', 'match'] as $field) {
                $key = strtolower($field . ':' . $action[$field]);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }
        ksort($counts);

        return $counts;
    }

    /**
     * @param list<array{table:string,parent:string,fkid:int,on_update:string,on_delete:string,match:string}> $current
     * @param list<array{table:string,parent:string,fkid:int,on_update:string,on_delete:string,match:string}> $next
     */
    private static function actionChangeCount(array $current, array $next): int
    {
        return count(array_diff(self::actionSummary($next), self::actionSummary($current)))
            + count(array_diff(self::actionSummary($current), self::actionSummary($next)));
    }

    private static function actionKey(string $table, int $id): string
    {
        return strtolower($table) . '#' . $id;
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next167 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next167 cursor offset does not match the requested page offset');
        }
    }

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private static function pragmaArgumentLiteral(string $identifier): string
    {
        return "'" . str_replace("'", "''", $identifier) . "'";
    }
}
