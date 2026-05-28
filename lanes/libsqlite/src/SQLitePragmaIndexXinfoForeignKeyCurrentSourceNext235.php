<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext235
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next235 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next235 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext233::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::parentDescendingUniqueRows($currentRecords, 'current');
        $nextRows = self::parentDescendingUniqueRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next235',
            'base' => $base['source_id'],
            'current_parent_desc_unique' => self::rowSummary($currentRows),
            'next_parent_desc_unique' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next235 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next235 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::descendingCounts($currentRows);
        $nextCounts = self::descendingCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next235',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_desc_unique_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_desc_terms',
                'foreign_key_parent_desc_unique' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_parent_desc_unique_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_desc_terms',
                'foreign_key_parent_desc_unique' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_desc_unique' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_desc_unique' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_desc_unique_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_parent_desc_unique_terms' => $nextCounts['descending_terms'] - $currentCounts['descending_terms'],
                'foreign_key_parent_desc_unique_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-parent-desc-unique-index',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function parentDescendingUniqueRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase)) as $group) {
            $parent = (string) $group[0]['parent'];
            $parentColumns = array_map(static fn (array $row): string => (string) $row['to'], $group);
            if (in_array('', $parentColumns, true)) {
                continue;
            }

            $candidate = self::matchingDescendingParentKey($catalog, $parent, $parentColumns);
            if ($candidate === null) {
                continue;
            }

            foreach ($group as $row) {
                $seq = (int) $row['seq'];
                $indexRow = $candidate['rows'][$seq] ?? null;
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_parent_desc_unique',
                    'table' => (string) $row['table'],
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => $seq,
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'parent_columns' => $parentColumns,
                    'parent_unique_index' => $candidate['index'],
                    'index_key_columns' => $candidate['columns'],
                    'index_desc_flags' => $candidate['desc_flags'],
                    'descending_key_columns' => $candidate['descending_columns'],
                    'descending_terms' => count($candidate['descending_columns']),
                    'index_column' => $indexRow['name'] ?? null,
                    'index_column_desc' => ((int) ($indexRow['desc'] ?? 0)) === 1,
                    'status' => 'ok',
                    'message' => "foreign key {$row['table']}->{$parent} can use descending UNIQUE parent index {$candidate['index']}; PRAGMA index_xinfo desc is not a blocker",
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
     * @param list<string> $parentColumns
     * @return array{index:string,rows:list<array<string,mixed>>,columns:list<string>,desc_flags:list<int>,descending_columns:list<string>}|null
     */
    private static function matchingDescendingParentKey(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns): ?array
    {
        $wanted = array_map('strtolower', $parentColumns);
        foreach ($catalog->indexList($parent) as $index) {
            if ((int) ($index['unique'] ?? 0) !== 1 || (int) ($index['partial'] ?? 0) !== 0) {
                continue;
            }

            $indexName = (string) $index['name'];
            $keyRows = array_values(array_filter(
                $catalog->indexXInfo($indexName),
                static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1,
            ));
            $columns = array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $keyRows);
            if (in_array('', $columns, true) || array_map('strtolower', $columns) !== $wanted) {
                continue;
            }

            $descFlags = array_map(static fn (array $row): int => (int) ($row['desc'] ?? 0), $keyRows);
            if (!in_array(1, $descFlags, true)) {
                continue;
            }

            return [
                'index' => $indexName,
                'rows' => $keyRows,
                'columns' => $columns,
                'desc_flags' => $descFlags,
                'descending_columns' => array_values(array_map(
                    static fn (array $row): string => (string) $row['name'],
                    array_filter($keyRows, static fn (array $row): bool => (int) ($row['desc'] ?? 0) === 1),
                )),
            ];
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,ok:int,foreign_keys:int,parent_tables:int,descending_terms:int,single_column:int,composite:int}
     */
    private static function descendingCounts(array $rows): array
    {
        $foreignKeys = [];
        $parents = [];
        $counts = [
            'rows' => count($rows),
            'ok' => count($rows),
            'foreign_keys' => 0,
            'parent_tables' => 0,
            'descending_terms' => 0,
            'single_column' => 0,
            'composite' => 0,
        ];

        foreach ($rows as $row) {
            $foreignKeys[(string) $row['table'] . '#' . (int) $row['foreign_key_id']] = true;
            $parents[strtolower((string) $row['parent'])] = true;
            $counts['descending_terms'] += (int) ($row['descending_terms'] ?? 0);
            if (count((array) ($row['parent_columns'] ?? [])) > 1) {
                $counts['composite']++;
            } else {
                $counts['single_column']++;
            }
        }

        $counts['foreign_keys'] = count($foreignKeys);
        $counts['parent_tables'] = count($parents);

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
                (string) ($row['parent_unique_index'] ?? ''),
                'columns=' . implode(',', (array) ($row['index_key_columns'] ?? [])),
                'desc=' . implode(',', array_map('strval', (array) ($row['index_desc_flags'] ?? []))),
                'descending=' . implode(',', (array) ($row['descending_key_columns'] ?? [])),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next235 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
