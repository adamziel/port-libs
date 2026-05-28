<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext252
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next252 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next252 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext250::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::missingChildColumnRows($currentRecords, 'current');
        $nextRows = self::missingChildColumnRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next252',
            'base' => $base['source_id'],
            'current_missing_child_columns' => self::rowSummary($currentRows),
            'next_missing_child_columns' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next252 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next252 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::missingChildCounts($currentRows);
        $nextCounts = self::missingChildCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next252',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_missing_child_column_source' => 'pragma_foreign_key_list_child_columns_plus_pragma_table_xinfo',
                'foreign_key_missing_child_columns' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_missing_child_column_source' => 'pragma_foreign_key_list_child_columns_plus_pragma_table_xinfo',
                'foreign_key_missing_child_columns' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_missing_child_columns' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_missing_child_columns' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_missing_child_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_missing_child_columns' => $nextCounts['missing_child_column'] - $currentCounts['missing_child_column'],
                'foreign_key_missing_child_repaired' => $currentCounts['missing_child_column'] > 0 && $nextCounts['missing_child_column'] === 0,
                'foreign_key_missing_child_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-table-xinfo-missing-child-columns',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function missingChildColumnRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $columnsByTable = self::tableXinfoByTable($catalog, $records);
        $rows = [];

        foreach (SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase) as $foreignKey) {
            $table = (string) $foreignKey['table'];
            $from = (string) ($foreignKey['from'] ?? '');
            if ($from === '') {
                continue;
            }

            $columns = $columnsByTable[strtolower($table)] ?? [];
            if (isset($columns[strtolower($from)])) {
                continue;
            }

            $available = array_values(array_map(static fn (array $column): string => (string) $column['name'], $columns));
            $rows[] = [
                'phase' => $phase,
                'kind' => 'foreign_key_missing_child_column',
                'table' => $table,
                'foreign_key_id' => (int) $foreignKey['id'],
                'seq' => (int) $foreignKey['seq'],
                'parent' => (string) $foreignKey['parent'],
                'from' => $from,
                'to' => (string) ($foreignKey['to'] ?? ''),
                'available_child_columns' => $available,
                'available_child_column_count' => count($available),
                'status' => 'missing_child_column',
                'message' => "foreign key {$table}.{$from} is not present in PRAGMA table_xinfo({$table})",
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
    private static function tableXinfoByTable(SQLitePragmaSchemaCatalog $catalog, array $records): array
    {
        $tables = [];
        foreach ($records as $record) {
            if ($record->type !== 'table') {
                continue;
            }

            foreach ($catalog->tableInfo($record->name, true) as $row) {
                $tables[strtolower($record->name)][strtolower((string) $row['name'])] = $row;
            }
        }

        return $tables;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,missing_child_column:int,foreign_keys:int,tables:int}
     */
    private static function missingChildCounts(array $rows): array
    {
        $foreignKeys = [];
        $tables = [];
        foreach ($rows as $row) {
            $foreignKeys[(string) $row['table'] . '#' . (int) $row['foreign_key_id']] = true;
            $tables[(string) $row['table']] = true;
        }

        return [
            'rows' => count($rows),
            'missing_child_column' => count($rows),
            'foreign_keys' => count($foreignKeys),
            'tables' => count($tables),
        ];
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
                'available=' . implode(',', (array) $row['available_child_columns']),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next252 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
