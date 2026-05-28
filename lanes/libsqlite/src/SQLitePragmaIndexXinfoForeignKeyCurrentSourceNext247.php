<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext247
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next247 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next247 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext244::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::setDefaultActionRows($currentRecords, 'current');
        $nextRows = self::setDefaultActionRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next247',
            'base' => $base['source_id'],
            'current_set_default' => self::rowSummary($currentRows),
            'next_set_default' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next247 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next247 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::setDefaultCounts($currentRows);
        $nextCounts = self::setDefaultCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next247',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_set_default_source' => 'pragma_foreign_key_list_actions_plus_table_info_child_defaults',
                'foreign_key_set_default' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_set_default_source' => 'pragma_foreign_key_list_actions_plus_table_info_child_defaults',
                'foreign_key_set_default' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_set_default' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_set_default' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_set_default_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_set_default_blockers' => $nextCounts['blocked'] - $currentCounts['blocked'],
                'foreign_key_set_default_repaired' => $currentCounts['blocked'] > 0 && $nextCounts['blocked'] === 0,
                'foreign_key_set_default_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-set-default-child-defaults',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function setDefaultActionRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $tableInfo = self::tableInfoByTable($catalog, $records);
        $rows = [];
        foreach (SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase) as $foreignKey) {
            $onDelete = strtoupper((string) ($foreignKey['on_delete'] ?? 'NO ACTION'));
            $onUpdate = strtoupper((string) ($foreignKey['on_update'] ?? 'NO ACTION'));
            if ($onDelete !== 'SET DEFAULT' && $onUpdate !== 'SET DEFAULT') {
                continue;
            }

            $table = (string) $foreignKey['table'];
            $from = (string) $foreignKey['from'];
            $child = ($tableInfo[strtolower($table)] ?? [])[strtolower($from)] ?? null;
            $default = $child['dflt_value'] ?? null;
            $notNull = (int) ($child['notnull'] ?? 0) === 1;
            $defaultIsNull = self::defaultIsNull($default);
            $status = $child === null
                ? 'missing_child_column'
                : ($notNull && $defaultIsNull ? 'notnull_default_null' : 'ok');

            $rows[] = [
                'phase' => $phase,
                'kind' => 'foreign_key_set_default',
                'table' => $table,
                'foreign_key_id' => (int) $foreignKey['id'],
                'seq' => (int) $foreignKey['seq'],
                'parent' => (string) $foreignKey['parent'],
                'from' => $from,
                'to' => (string) ($foreignKey['to'] ?? ''),
                'on_delete' => $onDelete,
                'on_update' => $onUpdate,
                'notnull' => $notNull,
                'default' => $default,
                'default_is_null' => $defaultIsNull,
                'set_default_on_delete' => $onDelete === 'SET DEFAULT',
                'set_default_on_update' => $onUpdate === 'SET DEFAULT',
                'status' => $status,
                'blocked' => $status !== 'ok',
                'message' => match ($status) {
                    'ok' => "foreign key {$table}.{$from} SET DEFAULT action has a usable child default",
                    'missing_child_column' => "foreign key {$table}.{$from} SET DEFAULT action references a missing child column",
                    default => "foreign key {$table}.{$from} SET DEFAULT action would store NULL into a NOT NULL child column",
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
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,array<string,array<string,mixed>>>
     */
    private static function tableInfoByTable(SQLitePragmaSchemaCatalog $catalog, array $records): array
    {
        $tables = [];
        foreach ($records as $record) {
            if ($record->type !== 'table') {
                continue;
            }

            foreach ($catalog->tableInfo($record->name) as $row) {
                $tables[strtolower($record->name)][strtolower((string) $row['name'])] = $row;
            }
        }

        return $tables;
    }

    private static function defaultIsNull(mixed $default): bool
    {
        if ($default === null) {
            return true;
        }

        $normalized = strtoupper(trim((string) $default));

        return $normalized === '' || $normalized === 'NULL';
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,ok:int,blocked:int,notnull_default_null:int,missing_child_column:int,set_default_delete:int,set_default_update:int,explicit_default:int,nullable_default_null:int}
     */
    private static function setDefaultCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'ok' => 0,
            'blocked' => 0,
            'notnull_default_null' => 0,
            'missing_child_column' => 0,
            'set_default_delete' => 0,
            'set_default_update' => 0,
            'explicit_default' => 0,
            'nullable_default_null' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'ok') {
                $counts['ok']++;
            } else {
                $counts['blocked']++;
                if (array_key_exists($status, $counts)) {
                    $counts[$status]++;
                }
            }
            if (($row['set_default_on_delete'] ?? false) === true) {
                $counts['set_default_delete']++;
            }
            if (($row['set_default_on_update'] ?? false) === true) {
                $counts['set_default_update']++;
            }
            if (($row['default_is_null'] ?? true) !== true) {
                $counts['explicit_default']++;
            } elseif (($row['notnull'] ?? false) !== true && $status === 'ok') {
                $counts['nullable_default_null']++;
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
                (string) $row['on_update'] . '/' . (string) $row['on_delete'],
                'notnull=' . (($row['notnull'] ?? false) ? '1' : '0'),
                'default=' . (string) (($row['default'] ?? null) ?? 'NULL'),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next247 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
