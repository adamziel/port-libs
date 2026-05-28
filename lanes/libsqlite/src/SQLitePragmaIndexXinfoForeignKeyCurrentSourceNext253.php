<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext253
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next253 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next253 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext250::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::generatedChildActionRows($currentRecords, 'current');
        $nextRows = self::generatedChildActionRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next253',
            'base' => $base['source_id'],
            'current_generated_child_actions' => self::rowSummary($currentRows),
            'next_generated_child_actions' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next253 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next253 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::generatedChildActionCounts($currentRows);
        $nextCounts = self::generatedChildActionCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next253',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_generated_child_action_source' => 'pragma_foreign_key_list_actions_plus_pragma_table_xinfo_generated_child_columns',
                'foreign_key_generated_child_actions' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_generated_child_action_source' => 'pragma_foreign_key_list_actions_plus_pragma_table_xinfo_generated_child_columns',
                'foreign_key_generated_child_actions' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_generated_child_actions' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_generated_child_actions' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_generated_child_action_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_generated_child_action_blockers' => $nextCounts['blocked'] - $currentCounts['blocked'],
                'foreign_key_generated_child_action_repaired' => $currentCounts['blocked'] > 0 && $nextCounts['blocked'] === 0,
                'foreign_key_generated_child_action_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-table-xinfo-generated-child-actions',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function generatedChildActionRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $tableXinfo = self::tableInfoByTable($catalog, $records, true);
        $rows = [];

        foreach (SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase) as $foreignKey) {
            $table = (string) $foreignKey['table'];
            $from = (string) ($foreignKey['from'] ?? '');
            if ($from === '') {
                continue;
            }

            $child = ($tableXinfo[strtolower($table)] ?? [])[strtolower($from)] ?? null;
            if ($child === null || (int) ($child['hidden'] ?? 0) === 0) {
                continue;
            }

            $action = self::generatedChildAction($foreignKey);
            if ($action === null) {
                continue;
            }

            $notNull = (int) ($child['notnull'] ?? 0) !== 0;
            $default = array_key_exists('dflt_value', $child) ? $child['dflt_value'] : null;
            $defaultIsNull = $default === null || strtoupper(trim((string) $default)) === 'NULL';
            $status = match (true) {
                $action === 'SET NULL' && $notNull => 'set_null_generated_notnull_child',
                $action === 'SET DEFAULT' && $notNull && $defaultIsNull => 'set_default_generated_null_child',
                default => 'ok',
            };

            $rows[] = [
                'phase' => $phase,
                'kind' => 'foreign_key_generated_child_action',
                'table' => $table,
                'foreign_key_id' => (int) $foreignKey['id'],
                'seq' => (int) $foreignKey['seq'],
                'parent' => (string) $foreignKey['parent'],
                'from' => $from,
                'to' => (string) ($foreignKey['to'] ?? ''),
                'action' => $action,
                'on_update' => (string) ($foreignKey['on_update'] ?? 'NO ACTION'),
                'on_delete' => (string) ($foreignKey['on_delete'] ?? 'NO ACTION'),
                'child_hidden' => (int) ($child['hidden'] ?? 0),
                'child_generated_storage' => (int) ($child['hidden'] ?? 0) === 3 ? 'stored' : 'virtual',
                'child_notnull' => $notNull,
                'child_default' => $default,
                'blocked' => $status !== 'ok',
                'status' => $status,
                'message' => match ($status) {
                    'set_null_generated_notnull_child' => "foreign key {$table}.{$from} {$action} action targets a generated NOT NULL child column only visible through PRAGMA table_xinfo",
                    'set_default_generated_null_child' => "foreign key {$table}.{$from} {$action} action targets a generated NOT NULL child column with NULL default only visible through PRAGMA table_xinfo",
                    default => "foreign key {$table}.{$from} {$action} action targets generated child column exposed by PRAGMA table_xinfo",
                },
            ];
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['phase'], $left['table'], $left['foreign_key_id'], $left['seq']]
                <=> [$right['phase'], $right['table'], $right['foreign_key_id'], $right['seq']],
        );

        return $rows;
    }

    /**
     * @param array<string,mixed> $foreignKey
     */
    private static function generatedChildAction(array $foreignKey): ?string
    {
        foreach (['on_delete', 'on_update'] as $key) {
            $action = strtoupper((string) ($foreignKey[$key] ?? 'NO ACTION'));
            if ($action === 'SET NULL' || $action === 'SET DEFAULT') {
                return $action;
            }
        }

        return null;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,array<string,array<string,mixed>>>
     */
    private static function tableInfoByTable(SQLitePragmaSchemaCatalog $catalog, array $records, bool $includeHidden): array
    {
        $tables = [];
        foreach ($records as $record) {
            if ($record->type !== 'table') {
                continue;
            }
            foreach ($catalog->tableInfo($record->name, $includeHidden) as $row) {
                $tables[strtolower($record->name)][strtolower((string) $row['name'])] = $row;
            }
        }

        return $tables;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,ok:int,blocked:int,set_null:int,set_default:int,virtual:int,stored:int,notnull:int}
     */
    private static function generatedChildActionCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'ok' => 0,
            'blocked' => 0,
            'set_null' => 0,
            'set_default' => 0,
            'virtual' => 0,
            'stored' => 0,
            'notnull' => 0,
        ];
        foreach ($rows as $row) {
            $counts[($row['blocked'] ?? false) ? 'blocked' : 'ok']++;
            if (($row['action'] ?? null) === 'SET NULL') {
                $counts['set_null']++;
            } elseif (($row['action'] ?? null) === 'SET DEFAULT') {
                $counts['set_default']++;
            }
            $storage = (string) ($row['child_generated_storage'] ?? '');
            if (array_key_exists($storage, $counts)) {
                $counts[$storage]++;
            }
            if (($row['child_notnull'] ?? false) === true) {
                $counts['notnull']++;
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
                (string) $row['action'],
                'hidden=' . (int) $row['child_hidden'],
                'storage=' . (string) $row['child_generated_storage'],
                'notnull=' . (($row['child_notnull'] ?? false) ? '1' : '0'),
                (string) $row['status'],
            ], static fn (?string $part): bool => $part !== null)),
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
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next253 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
