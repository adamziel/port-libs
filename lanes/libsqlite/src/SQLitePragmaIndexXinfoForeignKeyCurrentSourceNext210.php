<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext210
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next210 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next210 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext209::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::setDefaultChildRows($currentRecords, 'current');
        $nextRows = self::setDefaultChildRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next210',
            'base' => $base['source_id'],
            'current_set_default_child_columns' => self::rowSummary($currentRows),
            'next_set_default_child_columns' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next210 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next210 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::setDefaultCounts($currentRows);
        $nextCounts = self::setDefaultCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next210',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_set_default_child_source' => 'pragma_foreign_key_list_set_default_plus_table_info_child_defaults',
                'foreign_key_set_default_child_columns' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_set_default_child_source' => 'pragma_foreign_key_list_set_default_plus_table_info_child_defaults',
                'foreign_key_set_default_child_columns' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_set_default_child_columns' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_set_default_child_columns' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_set_default_child_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_set_default_child_missing_defaults' => $nextCounts['missing_child_default'] - $currentCounts['missing_child_default'],
                'foreign_key_set_default_child_repaired' => $currentCounts['missing_child_default'] > 0 && $nextCounts['missing_child_default'] === 0,
                'foreign_key_set_default_child_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
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
    public static function setDefaultChildRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach ($records as $record) {
            if ($record->type !== 'table') {
                continue;
            }

            $tableInfo = self::tableInfoByName($catalog, $record->name);
            $groups = [];
            foreach ($catalog->foreignKeyList($record->name) as $row) {
                $groups[(int) $row['id']][] = $row;
            }

            ksort($groups);
            foreach ($groups as $id => $foreignKeyRows) {
                usort($foreignKeyRows, static fn (array $left, array $right): int => (int) $left['seq'] <=> (int) $right['seq']);
                $onUpdate = strtoupper((string) ($foreignKeyRows[0]['on_update'] ?? 'NO ACTION'));
                $onDelete = strtoupper((string) ($foreignKeyRows[0]['on_delete'] ?? 'NO ACTION'));
                if ($onUpdate !== 'SET DEFAULT' && $onDelete !== 'SET DEFAULT') {
                    continue;
                }

                $childColumns = array_map(static fn (array $row): string => (string) $row['from'], $foreignKeyRows);
                $defaults = [];
                $missing = [];
                foreach ($childColumns as $column) {
                    $info = $tableInfo[strtolower($column)] ?? null;
                    $default = $info['dflt_value'] ?? null;
                    $defaults[$column] = $default;
                    if ($default === null || $default === '') {
                        $missing[] = $column;
                    }
                }

                $status = $missing === [] ? 'set_default_child_defaults_present' : 'missing_child_default';
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_set_default_child_default',
                    'table' => $record->name,
                    'foreign_key_id' => $id,
                    'parent' => (string) $foreignKeyRows[0]['table'],
                    'child_columns' => $childColumns,
                    'child_defaults' => $defaults,
                    'missing_child_defaults' => $missing,
                    'on_update' => $onUpdate,
                    'on_delete' => $onDelete,
                    'set_default_actions' => array_values(array_filter([
                        $onUpdate === 'SET DEFAULT' ? 'on_update' : null,
                        $onDelete === 'SET DEFAULT' ? 'on_delete' : null,
                    ])),
                    'status' => $status,
                    'message' => $status === 'set_default_child_defaults_present'
                        ? "foreign key {$record->name}->{$foreignKeyRows[0]['table']} SET DEFAULT action has child defaults"
                        : "foreign key {$record->name}->{$foreignKeyRows[0]['table']} SET DEFAULT action is missing child defaults for " . implode(',', $missing),
                ];
            }
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['phase'], $left['table'], $left['foreign_key_id']]
                <=> [$right['phase'], $right['table'], $right['foreign_key_id']],
        );

        return $rows;
    }

    /**
     * @return array<string,array<string,int|string|null>>
     */
    private static function tableInfoByName(SQLitePragmaSchemaCatalog $catalog, string $table): array
    {
        $byName = [];
        foreach ($catalog->tableInfo($table) as $row) {
            $byName[strtolower((string) $row['name'])] = $row;
        }

        return $byName;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,set_default_child_defaults_present:int,missing_child_default:int,on_update_set_default:int,on_delete_set_default:int,child_columns:int,missing_columns:int}
     */
    private static function setDefaultCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'set_default_child_defaults_present' => 0,
            'missing_child_default' => 0,
            'on_update_set_default' => 0,
            'on_delete_set_default' => 0,
            'child_columns' => 0,
            'missing_columns' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
            $actions = (array) ($row['set_default_actions'] ?? []);
            if (in_array('on_update', $actions, true)) {
                $counts['on_update_set_default']++;
            }
            if (in_array('on_delete', $actions, true)) {
                $counts['on_delete_set_default']++;
            }
            $counts['child_columns'] += count((array) ($row['child_columns'] ?? []));
            $counts['missing_columns'] += count((array) ($row['missing_child_defaults'] ?? []));
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
            static fn (array $row): string => ($includePhase ? $row['phase'] . ':' : '')
                . $row['table'] . '#' . $row['foreign_key_id'] . '->' . $row['parent']
                . ':child=' . implode(',', (array) $row['child_columns'])
                . ':defaults=' . self::defaultsSummary((array) $row['child_defaults'])
                . ':actions=' . implode(',', (array) $row['set_default_actions'])
                . ':' . $row['status'],
            $rows,
        );
        sort($summary);

        return $summary;
    }

    /**
     * @param array<string,mixed> $defaults
     */
    private static function defaultsSummary(array $defaults): string
    {
        $parts = [];
        foreach ($defaults as $column => $default) {
            $parts[] = $column . '=' . ($default === null || $default === '' ? 'NULL' : (string) $default);
        }
        sort($parts);

        return implode('|', $parts);
    }

    /**
     * @param list<mixed> $records
     */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next210 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
