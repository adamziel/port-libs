<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext250
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next250 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next250 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext246::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::generatedChildColumnRows($currentRecords, 'current');
        $nextRows = self::generatedChildColumnRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next250',
            'base' => $base['source_id'],
            'current_generated_child_columns' => self::rowSummary($currentRows),
            'next_generated_child_columns' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next250 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next250 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::generatedChildCounts($currentRows);
        $nextCounts = self::generatedChildCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next250',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_generated_child_column_source' => 'pragma_foreign_key_list_child_columns_plus_pragma_table_xinfo_generated_columns',
                'foreign_key_generated_child_columns' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_generated_child_column_source' => 'pragma_foreign_key_list_child_columns_plus_pragma_table_xinfo_generated_columns',
                'foreign_key_generated_child_columns' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_generated_child_columns' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_generated_child_columns' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_generated_child_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_generated_child_columns' => $nextCounts['generated_child_column'] - $currentCounts['generated_child_column'],
                'foreign_key_generated_child_repaired' => $currentCounts['generated_child_column'] > 0 && $nextCounts['generated_child_column'] === 0,
                'foreign_key_generated_child_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-table-xinfo-generated-child-columns',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function generatedChildColumnRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $tableXinfo = self::tableInfoByTable($catalog, $records, true);
        $tableInfo = self::tableInfoByTable($catalog, $records, false);
        $rows = [];

        foreach (SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase) as $foreignKey) {
            $table = (string) $foreignKey['table'];
            $from = (string) ($foreignKey['from'] ?? '');
            if ($from === '') {
                continue;
            }

            $childTableInfoColumn = ($tableInfo[strtolower($table)] ?? [])[strtolower($from)] ?? null;
            $childXinfoColumn = ($tableXinfo[strtolower($table)] ?? [])[strtolower($from)] ?? null;
            if ($childXinfoColumn === null || (int) ($childXinfoColumn['hidden'] ?? 0) === 0) {
                continue;
            }

            $hidden = (int) $childXinfoColumn['hidden'];
            $status = $childTableInfoColumn === null ? 'generated_child_column' : 'visible_child_column';
            $rows[] = [
                'phase' => $phase,
                'kind' => 'foreign_key_generated_child_column',
                'table' => $table,
                'foreign_key_id' => (int) $foreignKey['id'],
                'seq' => (int) $foreignKey['seq'],
                'parent' => (string) $foreignKey['parent'],
                'from' => $from,
                'to' => (string) ($foreignKey['to'] ?? ''),
                'child_hidden' => $hidden,
                'child_generated_storage' => $hidden === 3 ? 'stored' : 'virtual',
                'child_type' => (string) ($childXinfoColumn['type'] ?? ''),
                'child_notnull' => (int) ($childXinfoColumn['notnull'] ?? 0),
                'table_info_visible' => $childTableInfoColumn !== null,
                'status' => $status,
                'message' => $status === 'generated_child_column'
                    ? "foreign key {$table}.{$from} uses generated child column; PRAGMA table_info omits it but table_xinfo exposes hidden code {$hidden}"
                    : "foreign key {$table}.{$from} uses visible child column",
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
     * @return array{rows:int,generated_child_column:int,visible_child_column:int,virtual:int,stored:int,notnull:int}
     */
    private static function generatedChildCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'generated_child_column' => 0,
            'visible_child_column' => 0,
            'virtual' => 0,
            'stored' => 0,
            'notnull' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
            $storage = (string) ($row['child_generated_storage'] ?? '');
            if (array_key_exists($storage, $counts)) {
                $counts[$storage]++;
            }
            if ((int) ($row['child_notnull'] ?? 0) !== 0) {
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
                'hidden=' . (int) $row['child_hidden'],
                'storage=' . (string) $row['child_generated_storage'],
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
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next250 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
