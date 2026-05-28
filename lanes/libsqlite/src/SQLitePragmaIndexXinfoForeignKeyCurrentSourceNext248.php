<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext248
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next248 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next248 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext245::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::externalParentKeyRows($currentRecords, 'current');
        $nextRows = self::externalParentKeyRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next248',
            'base' => $base['source_id'],
            'current_external_parent_keys' => self::rowSummary($currentRows),
            'next_external_parent_keys' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next248 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next248 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::externalCounts($currentRows);
        $nextCounts = self::externalCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next248',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_external_unique_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_list_origin_and_pragma_index_xinfo',
                'foreign_key_parent_external_unique' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_parent_external_unique_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_list_origin_and_pragma_index_xinfo',
                'foreign_key_parent_external_unique' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_external_unique' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_external_unique' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_external_unique_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_parent_external_unique_blockers' => $nextCounts['external_unique_parent_key'] - $currentCounts['external_unique_parent_key'],
                'foreign_key_parent_external_unique_repaired' => $currentCounts['external_unique_parent_key'] > 0 && $nextCounts['external_unique_parent_key'] === 0,
                'foreign_key_parent_external_unique_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-index-list-origin-foreign-key-parent-key',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function externalParentKeyRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase)) as $group) {
            $parent = (string) $group[0]['parent'];
            $parentColumns = array_values(array_map(static fn (array $row): string => (string) $row['to'], $group));
            if (in_array('', $parentColumns, true)) {
                continue;
            }

            $candidate = self::matchingParentKey($catalog, $parent, $parentColumns);
            if ($candidate['index'] === null) {
                continue;
            }

            $status = $candidate['origin'] === 'c' ? 'external_unique_parent_key' : 'inline_unique_parent_key';
            $rows[] = [
                'phase' => $phase,
                'kind' => 'foreign_key_parent_external_unique',
                'table' => (string) $group[0]['table'],
                'foreign_key_id' => (int) $group[0]['id'],
                'parent' => $parent,
                'parent_columns' => $parentColumns,
                'child_columns' => array_map(static fn (array $row): string => (string) $row['from'], $group),
                'parent_index' => $candidate['index'],
                'parent_index_origin' => $candidate['origin'],
                'parent_index_partial' => $candidate['partial'],
                'parent_index_columns' => $candidate['columns'],
                'parent_index_collations' => $candidate['collations'],
                'status' => $status,
                'drop_index_mismatch_risk' => $status === 'external_unique_parent_key',
                'message' => $status === 'external_unique_parent_key'
                    ? "foreign key {$group[0]['table']}->{$parent} parent key depends on external UNIQUE index {$candidate['index']}; dropping that index can create a foreign key mismatch"
                    : "foreign key {$group[0]['table']}->{$parent} parent key uses inline UNIQUE/PRIMARY KEY index {$candidate['index']}",
            ];
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['phase'], $left['table'], $left['foreign_key_id'], $left['parent_index']]
                <=> [$right['phase'], $right['table'], $right['foreign_key_id'], $right['parent_index']],
        );

        return $rows;
    }

    /**
     * @param list<string> $parentColumns
     * @return array{index:string|null,origin:string|null,partial:int|null,columns:list<string>,collations:list<string>}
     */
    private static function matchingParentKey(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns): array
    {
        foreach ($catalog->indexList($parent) as $index) {
            if ((int) ($index['unique'] ?? 0) !== 1 || (int) ($index['partial'] ?? 0) !== 0) {
                continue;
            }

            $keyRows = array_values(array_filter(
                $catalog->indexXInfo((string) $index['name']),
                static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1,
            ));
            $columns = array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $keyRows);
            if (array_map('strtolower', $columns) !== array_map('strtolower', $parentColumns)) {
                continue;
            }

            return [
                'index' => (string) $index['name'],
                'origin' => (string) ($index['origin'] ?? ''),
                'partial' => (int) ($index['partial'] ?? 0),
                'columns' => $columns,
                'collations' => array_map(static fn (array $row): string => strtoupper((string) ($row['coll'] ?? 'BINARY')), $keyRows),
            ];
        }

        return ['index' => null, 'origin' => null, 'partial' => null, 'columns' => [], 'collations' => []];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,external_unique_parent_key:int,inline_unique_parent_key:int,created_indexes:int,autoindexes:int,drop_index_mismatch_risks:int}
     */
    private static function externalCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'external_unique_parent_key' => 0,
            'inline_unique_parent_key' => 0,
            'created_indexes' => 0,
            'autoindexes' => 0,
            'drop_index_mismatch_risks' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
            if (($row['parent_index_origin'] ?? null) === 'c') {
                $counts['created_indexes']++;
            }
            if (($row['parent_index_origin'] ?? null) === 'u' || ($row['parent_index_origin'] ?? null) === 'pk') {
                $counts['autoindexes']++;
            }
            if (($row['drop_index_mismatch_risk'] ?? false) === true) {
                $counts['drop_index_mismatch_risks']++;
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
                (string) $row['table'] . '#' . (int) $row['foreign_key_id'] . '->' . (string) $row['parent'],
                'parent=' . implode(',', (array) $row['parent_columns']),
                'index=' . (string) ($row['parent_index'] ?? 'none'),
                'origin=' . (string) ($row['parent_index_origin'] ?? 'none'),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next248 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
