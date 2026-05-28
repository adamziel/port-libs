<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext209
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next209 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next209 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext206::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::implicitParentPrimaryKeyRows($currentRecords, 'current');
        $nextRows = self::implicitParentPrimaryKeyRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next209',
            'base' => $base['source_id'],
            'current_implicit_parent_primary_keys' => self::rowSummary($currentRows),
            'next_implicit_parent_primary_keys' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next209 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next209 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::implicitParentPrimaryKeyCounts($currentRows);
        $nextCounts = self::implicitParentPrimaryKeyCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next209',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_implicit_parent_primary_key_source' => 'pragma_foreign_key_list_null_to_plus_pragma_table_info_primary_key_arity',
                'foreign_key_implicit_parent_primary_key' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_implicit_parent_primary_key_source' => 'pragma_foreign_key_list_null_to_plus_pragma_table_info_primary_key_arity',
                'foreign_key_implicit_parent_primary_key' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_implicit_parent_primary_key' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_implicit_parent_primary_key' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_implicit_parent_primary_key_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_implicit_parent_primary_key_valid' => $nextCounts['valid_implicit_parent_key'] - $currentCounts['valid_implicit_parent_key'],
                'foreign_key_implicit_parent_primary_key_mismatches' => $nextCounts['arity_mismatch'] - $currentCounts['arity_mismatch'],
                'foreign_key_implicit_parent_primary_key_repaired' => $currentCounts['arity_mismatch'] > 0 && $nextCounts['arity_mismatch'] === 0,
                'foreign_key_implicit_parent_primary_key_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-implicit-parent-primary-key-arity',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function implicitParentPrimaryKeyRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach ($records as $record) {
            if ($record->type !== 'table') {
                continue;
            }

            $groups = [];
            foreach ($catalog->foreignKeyList($record->name) as $row) {
                $groups[(int) $row['id']][] = $row;
            }

            ksort($groups);
            foreach ($groups as $id => $foreignKeyRows) {
                usort($foreignKeyRows, static fn (array $left, array $right): int => (int) $left['seq'] <=> (int) $right['seq']);
                $parent = (string) $foreignKeyRows[0]['table'];
                $hasImplicitParent = false;
                foreach ($foreignKeyRows as $foreignKeyRow) {
                    if (($foreignKeyRow['to'] ?? null) === null || (string) $foreignKeyRow['to'] === '') {
                        $hasImplicitParent = true;
                        break;
                    }
                }
                if (!$hasImplicitParent) {
                    continue;
                }

                $parentPrimaryKey = self::primaryKeyColumns($catalog, $parent);
                $childColumns = array_map(static fn (array $row): string => (string) $row['from'], $foreignKeyRows);
                $status = match (true) {
                    $parentPrimaryKey === [] => 'missing_parent_primary_key',
                    count($childColumns) !== count($parentPrimaryKey) => 'arity_mismatch',
                    default => 'valid_implicit_parent_key',
                };

                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_implicit_parent_primary_key',
                    'table' => $record->name,
                    'foreign_key_id' => $id,
                    'parent' => $parent,
                    'child_columns' => $childColumns,
                    'child_column_count' => count($childColumns),
                    'parent_primary_key_columns' => $parentPrimaryKey,
                    'parent_primary_key_count' => count($parentPrimaryKey),
                    'status' => $status,
                    'message' => $status === 'valid_implicit_parent_key'
                        ? "foreign key {$record->name}->{$parent} implicit parent key matches the parent primary-key arity"
                        : "foreign key {$record->name}->{$parent} implicit parent key does not match the parent primary-key arity",
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
     * @return list<string>
     */
    private static function primaryKeyColumns(SQLitePragmaSchemaCatalog $catalog, string $table): array
    {
        $columns = [];
        foreach ($catalog->tableInfo($table) as $row) {
            $pk = (int) $row['pk'];
            if ($pk > 0) {
                $columns[$pk] = (string) $row['name'];
            }
        }
        ksort($columns);

        return array_values($columns);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,valid_implicit_parent_key:int,arity_mismatch:int,missing_parent_primary_key:int,single_child:int,composite_child:int}
     */
    private static function implicitParentPrimaryKeyCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'valid_implicit_parent_key' => 0,
            'arity_mismatch' => 0,
            'missing_parent_primary_key' => 0,
            'single_child' => 0,
            'composite_child' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
            if ((int) ($row['child_column_count'] ?? 0) === 1) {
                $counts['single_child']++;
            } else {
                $counts['composite_child']++;
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
            static fn (array $row): string => ($includePhase ? $row['phase'] . ':' : '')
                . $row['table'] . '#' . $row['foreign_key_id'] . '->' . $row['parent']
                . ':child=' . implode(',', (array) $row['child_columns'])
                . ':parent-pk=' . implode(',', (array) $row['parent_primary_key_columns'])
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next209 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
