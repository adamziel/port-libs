<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext245
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next245 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next245 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext242::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::generatedParentKeyRows($currentRecords, 'current');
        $nextRows = self::generatedParentKeyRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next245',
            'base' => $base['source_id'],
            'current_generated_parent_key_rows' => self::rowSummary($currentRows),
            'next_generated_parent_key_rows' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next245 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next245 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::generatedCounts($currentRows);
        $nextCounts = self::generatedCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next245',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_generated_key_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_table_xinfo_hidden_and_pragma_index_xinfo',
                'foreign_key_parent_generated_key' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_parent_generated_key_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_table_xinfo_hidden_and_pragma_index_xinfo',
                'foreign_key_parent_generated_key' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_generated_key' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_generated_key' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_generated_key_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_parent_generated_key_blockers' => $nextCounts['hidden_parent_key_requires_table_xinfo'] - $currentCounts['hidden_parent_key_requires_table_xinfo'],
                'foreign_key_parent_generated_key_repaired' => $currentCounts['hidden_parent_key_requires_table_xinfo'] > 0 && $nextCounts['hidden_parent_key_requires_table_xinfo'] === 0,
                'foreign_key_parent_generated_key_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-table-xinfo-generated-foreign-key-parent-key',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function generatedParentKeyRows(array $records, string $phase = 'current'): array
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

            $hidden = self::hiddenParentColumns($catalog, $parent);
            $hiddenParentColumns = array_values(array_filter($parentColumns, static fn (string $column): bool => isset($hidden[strtolower($column)])));
            if ($hiddenParentColumns === []) {
                continue;
            }

            $candidate = self::matchingGeneratedParentIndex($catalog, $parent, $parentColumns);
            foreach ($group as $row) {
                $to = (string) $row['to'];
                if (!isset($hidden[strtolower($to)])) {
                    continue;
                }

                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_parent_generated_key',
                    'table' => (string) $row['table'],
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => (int) $row['seq'],
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => $to,
                    'parent_columns' => $parentColumns,
                    'hidden_parent_columns' => $hiddenParentColumns,
                    'hidden_code' => $hidden[strtolower($to)]['hidden'],
                    'parent_unique_index' => $candidate['index'],
                    'index_key_columns' => $candidate['columns'],
                    'status' => $candidate['index'] === null ? 'hidden_parent_key_missing_unique_index' : 'hidden_parent_key_requires_table_xinfo',
                    'message' => $candidate['index'] === null
                        ? "foreign key {$row['table']}->{$parent} references generated parent column {$to} without a UNIQUE parent key"
                        : "foreign key {$row['table']}->{$parent} parent key {$to} is visible through PRAGMA table_xinfo and UNIQUE index {$candidate['index']}",
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
     * @return array<string,array{name:string,hidden:int}>
     */
    private static function hiddenParentColumns(SQLitePragmaSchemaCatalog $catalog, string $parent): array
    {
        $hidden = [];
        foreach ($catalog->tableInfo($parent, true) as $row) {
            if ((int) ($row['hidden'] ?? 0) === 0) {
                continue;
            }
            $name = (string) $row['name'];
            $hidden[strtolower($name)] = ['name' => $name, 'hidden' => (int) $row['hidden']];
        }

        return $hidden;
    }

    /**
     * @param list<string> $parentColumns
     * @return array{index:string|null,columns:list<string>}
     */
    private static function matchingGeneratedParentIndex(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns): array
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
            if (array_map('strtolower', $columns) === $wanted) {
                return ['index' => (string) $index['name'], 'columns' => $columns];
            }
        }

        return ['index' => null, 'columns' => []];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,hidden_parent_key_requires_table_xinfo:int,hidden_parent_key_missing_unique_index:int,hidden_columns:int,unique_indexes:int}
     */
    private static function generatedCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'hidden_parent_key_requires_table_xinfo' => 0,
            'hidden_parent_key_missing_unique_index' => 0,
            'hidden_columns' => 0,
            'unique_indexes' => 0,
        ];
        $columns = [];
        $indexes = [];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
            $columns[strtolower((string) ($row['to'] ?? ''))] = true;
            if (($row['parent_unique_index'] ?? null) !== null) {
                $indexes[(string) $row['parent_unique_index']] = true;
            }
        }
        $counts['hidden_columns'] = count($columns);
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
                'hidden=' . (int) ($row['hidden_code'] ?? 0),
                'index=' . (string) ($row['parent_unique_index'] ?? 'none'),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next245 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
