<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext254
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next254 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next254 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext251::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::nullableParentKeyRows($currentRecords, 'current');
        $nextRows = self::nullableParentKeyRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next254',
            'base' => $base['source_id'],
            'current_nullable_parent_keys' => self::rowSummary($currentRows),
            'next_nullable_parent_keys' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next254 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next254 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::nullableParentCounts($currentRows);
        $nextCounts = self::nullableParentCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next254',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_nullable_parent_key_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_table_info_notnull_and_pragma_index_xinfo',
                'foreign_key_nullable_parent_key' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_nullable_parent_key_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_table_info_notnull_and_pragma_index_xinfo',
                'foreign_key_nullable_parent_key' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_nullable_parent_key' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_nullable_parent_key' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_nullable_parent_key_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_nullable_parent_key_blockers' => $nextCounts['nullable_parent_key'] - $currentCounts['nullable_parent_key'],
                'foreign_key_nullable_parent_key_repaired' => $currentCounts['nullable_parent_key'] > 0 && $nextCounts['nullable_parent_key'] === 0,
                'foreign_key_nullable_parent_key_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-nullable-parent-key',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function nullableParentKeyRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $tableInfo = self::tableInfoByTable($catalog, $records);
        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase)) as $group) {
            $parent = (string) $group[0]['parent'];
            $parentColumns = array_map(static fn (array $row): string => (string) ($row['to'] ?? ''), $group);
            if (in_array('', $parentColumns, true)) {
                continue;
            }

            $candidate = self::matchingUniqueParentIndex($catalog, $parent, $parentColumns);
            if ($candidate['index'] === null) {
                continue;
            }

            foreach ($group as $row) {
                $to = (string) ($row['to'] ?? '');
                $parentColumn = ($tableInfo[strtolower($parent)] ?? [])[strtolower($to)] ?? null;
                if ($parentColumn === null) {
                    continue;
                }

                $notNull = (int) ($parentColumn['notnull'] ?? 0) === 1 || (int) ($parentColumn['pk'] ?? 0) > 0;
                $status = $notNull ? 'not_null_parent_key' : 'nullable_parent_key';
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_nullable_parent_key',
                    'table' => (string) $row['table'],
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => (int) $row['seq'],
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => $to,
                    'parent_columns' => $parentColumns,
                    'parent_unique_index' => $candidate['index'],
                    'parent_index_origin' => $candidate['origin'],
                    'parent_index_columns' => $candidate['columns'],
                    'parent_index_collations' => $candidate['collations'],
                    'parent_notnull' => $notNull,
                    'parent_pk' => (int) ($parentColumn['pk'] ?? 0),
                    'status' => $status,
                    'blocked' => !$notNull,
                    'message' => $notNull
                        ? "foreign key {$row['table']}->{$parent} parent column {$to} is NOT NULL in parent key {$candidate['index']}"
                        : "foreign key {$row['table']}->{$parent} parent column {$to} is nullable even though {$candidate['index']} is UNIQUE",
                ];
            }
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

    /**
     * @param list<string> $parentColumns
     * @return array{index:string|null,origin:string|null,columns:list<string>,collations:list<string>}
     */
    private static function matchingUniqueParentIndex(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns): array
    {
        $wanted = array_map('strtolower', $parentColumns);
        foreach ($catalog->indexList($parent) as $index) {
            if ((int) ($index['unique'] ?? 0) !== 1 || (int) ($index['partial'] ?? 0) !== 0) {
                continue;
            }

            $keyRows = array_values(array_filter(
                $catalog->indexXInfo((string) $index['name']),
                static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1,
            ));
            $columns = array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $keyRows);
            if (array_map('strtolower', $columns) !== $wanted) {
                continue;
            }

            return [
                'index' => (string) $index['name'],
                'origin' => (string) ($index['origin'] ?? ''),
                'columns' => $columns,
                'collations' => array_map(static fn (array $row): string => strtoupper((string) ($row['coll'] ?? 'BINARY')), $keyRows),
            ];
        }

        return ['index' => null, 'origin' => null, 'columns' => [], 'collations' => []];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,nullable_parent_key:int,not_null_parent_key:int,blocked:int,foreign_keys:int,nullable_columns:int,not_null_columns:int,unique_indexes:int,autoindexes:int}
     */
    private static function nullableParentCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'nullable_parent_key' => 0,
            'not_null_parent_key' => 0,
            'blocked' => 0,
            'foreign_keys' => 0,
            'nullable_columns' => 0,
            'not_null_columns' => 0,
            'unique_indexes' => 0,
            'autoindexes' => 0,
        ];
        $foreignKeys = [];
        $indexes = [];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
            if (($row['blocked'] ?? false) === true) {
                $counts['blocked']++;
                $counts['nullable_columns']++;
            } else {
                $counts['not_null_columns']++;
            }
            $foreignKeys[(string) ($row['table'] ?? '') . '#' . (int) ($row['foreign_key_id'] ?? -1)] = true;
            if (($row['parent_unique_index'] ?? null) !== null) {
                $indexes[(string) $row['parent_unique_index']] = true;
            }
            if (($row['parent_index_origin'] ?? null) === 'u' || ($row['parent_index_origin'] ?? null) === 'pk') {
                $counts['autoindexes']++;
            }
        }
        $counts['foreign_keys'] = count($foreignKeys);
        $counts['unique_indexes'] = count($indexes);

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
                'parent=' . implode(',', (array) ($row['parent_columns'] ?? [])),
                'index=' . (string) ($row['parent_unique_index'] ?? 'none'),
                'notnull=' . (($row['parent_notnull'] ?? false) ? '1' : '0'),
                (string) ($row['status'] ?? ''),
            ], static fn (?string $part): bool => $part !== null)),
            $rows,
        );
        sort($summary);

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<list<array<string,mixed>>>
     */
    private static function groupForeignKeyRows(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[strtolower((string) $row['table']) . '#' . (int) $row['id']][] = $row;
        }
        foreach ($groups as &$group) {
            usort($group, static fn (array $left, array $right): int => (int) $left['seq'] <=> (int) $right['seq']);
        }

        return array_values($groups);
    }

    /**
     * @param list<mixed> $records
     */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next254 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
