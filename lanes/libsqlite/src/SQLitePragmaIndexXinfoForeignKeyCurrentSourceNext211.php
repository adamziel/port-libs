<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext211
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next211 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next211 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext209::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::childNullabilityRows($currentRecords, 'current');
        $nextRows = self::childNullabilityRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next211',
            'base' => $base['source_id'],
            'current_child_nullability' => self::rowSummary($currentRows),
            'next_child_nullability' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next211 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next211 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::nullabilityCounts($currentRows);
        $nextCounts = self::nullabilityCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next211',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_child_nullability_source' => 'pragma_foreign_key_list_child_groups_plus_pragma_table_info_notnull',
                'foreign_key_child_nullability' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_child_nullability_source' => 'pragma_foreign_key_list_child_groups_plus_pragma_table_info_notnull',
                'foreign_key_child_nullability' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_child_nullability' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_child_nullability' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_child_nullability_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_child_nullability_nullable' => $nextCounts['nullable_child_key'] - $currentCounts['nullable_child_key'],
                'foreign_key_child_nullability_all_not_null' => $nextCounts['all_not_null_child_key'] - $currentCounts['all_not_null_child_key'],
                'foreign_key_child_nullability_repaired' => $currentCounts['nullable_child_key'] > 0 && $nextCounts['nullable_child_key'] === 0,
                'foreign_key_child_nullability_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-child-nullability',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function childNullabilityRows(array $records, string $phase = 'current'): array
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
            foreach ($catalog->foreignKeyList($record->name) as $foreignKeyRow) {
                $groups[(int) $foreignKeyRow['id']][] = $foreignKeyRow;
            }

            ksort($groups);
            foreach ($groups as $id => $foreignKeyRows) {
                usort($foreignKeyRows, static fn (array $left, array $right): int => (int) $left['seq'] <=> (int) $right['seq']);
                $childColumns = array_map(static fn (array $row): string => (string) $row['from'], $foreignKeyRows);
                $nullableColumns = [];
                $notNullColumns = [];
                foreach ($childColumns as $column) {
                    $info = $tableInfo[strtolower($column)] ?? null;
                    $isNotNull = $info !== null && ((int) ($info['notnull'] ?? 0) === 1 || (int) ($info['pk'] ?? 0) > 0);
                    if ($isNotNull) {
                        $notNullColumns[] = $column;
                    } else {
                        $nullableColumns[] = $column;
                    }
                }

                $status = $nullableColumns === [] ? 'all_not_null_child_key' : 'nullable_child_key';
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_child_nullability',
                    'table' => $record->name,
                    'foreign_key_id' => $id,
                    'parent' => (string) $foreignKeyRows[0]['table'],
                    'child_columns' => $childColumns,
                    'child_column_count' => count($childColumns),
                    'not_null_columns' => $notNullColumns,
                    'nullable_columns' => $nullableColumns,
                    'not_null_count' => count($notNullColumns),
                    'nullable_count' => count($nullableColumns),
                    'status' => $status,
                    'message' => $status === 'all_not_null_child_key'
                        ? "foreign key {$record->name}->{$foreignKeyRows[0]['table']} child key columns are all NOT NULL"
                        : "foreign key {$record->name}->{$foreignKeyRows[0]['table']} child key can be skipped by NULL child columns",
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
        $columns = [];
        foreach ($catalog->tableInfo($table) as $row) {
            $columns[strtolower((string) $row['name'])] = $row;
        }

        return $columns;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,all_not_null_child_key:int,nullable_child_key:int,single_column:int,composite:int,nullable_columns:int,not_null_columns:int}
     */
    private static function nullabilityCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'all_not_null_child_key' => 0,
            'nullable_child_key' => 0,
            'single_column' => 0,
            'composite' => 0,
            'nullable_columns' => 0,
            'not_null_columns' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
            if ((int) ($row['child_column_count'] ?? 0) === 1) {
                $counts['single_column']++;
            } else {
                $counts['composite']++;
            }
            $counts['nullable_columns'] += (int) ($row['nullable_count'] ?? 0);
            $counts['not_null_columns'] += (int) ($row['not_null_count'] ?? 0);
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
                . ':nullable=' . implode(',', (array) $row['nullable_columns'])
                . ':' . $row['status'],
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next211 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
