<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext213
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next213 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next213 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext212::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::actionColumnRows($currentRecords, 'current');
        $nextRows = self::actionColumnRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next213',
            'base' => $base['source_id'],
            'current_action_columns' => self::rowSummary($currentRows),
            'next_action_columns' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next213 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next213 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::actionColumnCounts($currentRows);
        $nextCounts = self::actionColumnCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next213',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_action_column_source' => 'pragma_foreign_key_list_actions_plus_pragma_table_info_notnull_default',
                'foreign_key_action_columns' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_action_column_source' => 'pragma_foreign_key_list_actions_plus_pragma_table_info_notnull_default',
                'foreign_key_action_columns' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_action_columns' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_action_columns' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_action_column_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_action_column_blockers' => $nextCounts['blocked'] - $currentCounts['blocked'],
                'foreign_key_action_column_repaired' => $currentCounts['blocked'] > 0 && $nextCounts['blocked'] === 0,
                'foreign_key_action_column_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-action-column-defaults',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function actionColumnRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach ($records as $record) {
            if ($record->type !== 'table') {
                continue;
            }

            $columns = self::tableColumnMap($catalog, $record->name);
            foreach ($catalog->foreignKeyList($record->name) as $foreignKey) {
                foreach (['on_delete', 'on_update'] as $actionKey) {
                    $action = strtoupper((string) ($foreignKey[$actionKey] ?? 'NO ACTION'));
                    if ($action !== 'SET NULL' && $action !== 'SET DEFAULT') {
                        continue;
                    }

                    $column = $columns[strtolower((string) $foreignKey['from'])] ?? null;
                    $status = self::actionColumnStatus($action, $column);
                    $rows[] = [
                        'phase' => $phase,
                        'kind' => 'foreign_key_action_column',
                        'table' => $record->name,
                        'foreign_key_id' => (int) $foreignKey['id'],
                        'seq' => (int) $foreignKey['seq'],
                        'parent' => (string) $foreignKey['table'],
                        'from' => (string) $foreignKey['from'],
                        'to' => (string) ($foreignKey['to'] ?? ''),
                        'action' => $action,
                        'action_key' => $actionKey,
                        'notnull' => (int) ($column['notnull'] ?? 0),
                        'dflt_value' => $column['dflt_value'] ?? null,
                        'status' => $status,
                        'message' => self::message($record->name, (string) $foreignKey['from'], $action, $status),
                    ];
                }
            }
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['phase'], $left['table'], $left['foreign_key_id'], $left['seq'], $left['action_key']]
                <=> [$right['phase'], $right['table'], $right['foreign_key_id'], $right['seq'], $right['action_key']],
        );

        return $rows;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function tableColumnMap(SQLitePragmaSchemaCatalog $catalog, string $table): array
    {
        $columns = [];
        foreach ($catalog->tableInfo($table) as $column) {
            $columns[strtolower((string) $column['name'])] = $column;
        }

        return $columns;
    }

    /**
     * @param array<string,mixed>|null $column
     */
    private static function actionColumnStatus(string $action, ?array $column): string
    {
        if ($column === null) {
            return 'missing_child_column';
        }
        $notNull = (int) ($column['notnull'] ?? 0) === 1;
        $default = $column['dflt_value'] ?? null;

        if ($action === 'SET NULL') {
            return $notNull ? 'set_null_notnull_child' : 'ok';
        }
        if ($default === null || trim((string) $default) === '') {
            return 'set_default_missing_child_default';
        }
        if ($notNull && strtoupper(trim((string) $default)) === 'NULL') {
            return 'set_default_null_notnull_child';
        }

        return 'ok';
    }

    private static function message(string $table, string $column, string $action, string $status): string
    {
        return match ($status) {
            'ok' => "foreign key {$table}.{$column} can apply {$action}",
            'set_null_notnull_child' => "foreign key {$table}.{$column} SET NULL action targets a NOT NULL child column",
            'set_default_missing_child_default' => "foreign key {$table}.{$column} SET DEFAULT action targets a child column with no default",
            'set_default_null_notnull_child' => "foreign key {$table}.{$column} SET DEFAULT action targets a NOT NULL child column whose default is NULL",
            default => "foreign key {$table}.{$column} action targets a missing child column",
        };
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,ok:int,blocked:int,set_null:int,set_default:int,set_null_notnull_child:int,set_default_missing_child_default:int,set_default_null_notnull_child:int,missing_child_column:int}
     */
    private static function actionColumnCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'ok' => 0,
            'blocked' => 0,
            'set_null' => 0,
            'set_default' => 0,
            'set_null_notnull_child' => 0,
            'set_default_missing_child_default' => 0,
            'set_default_null_notnull_child' => 0,
            'missing_child_column' => 0,
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
            if (($row['action'] ?? null) === 'SET NULL') {
                $counts['set_null']++;
            } elseif (($row['action'] ?? null) === 'SET DEFAULT') {
                $counts['set_default']++;
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
                (string) $row['action_key'] . '=' . (string) $row['action'],
                'notnull=' . (int) $row['notnull'],
                'default=' . (($row['dflt_value'] ?? null) === null ? 'NULL' : (string) $row['dflt_value']),
                (string) $row['status'],
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next213 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
