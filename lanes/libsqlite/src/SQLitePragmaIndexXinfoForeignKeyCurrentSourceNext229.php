<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext229
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next229 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next229 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext224::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::parentKeyExactArityRows($currentRecords, 'current');
        $nextRows = self::parentKeyExactArityRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next229',
            'base' => $base['source_id'],
            'current_parent_key_exact_arity' => self::rowSummary($currentRows),
            'next_parent_key_exact_arity' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next229 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next229 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::arityCounts($currentRows);
        $nextCounts = self::arityCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next229',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_key_exact_arity_source' => 'pragma_foreign_key_list_parent_columns_plus_exact_unique_index_arity',
                'foreign_key_parent_key_exact_arity' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_parent_key_exact_arity_source' => 'pragma_foreign_key_list_parent_columns_plus_exact_unique_index_arity',
                'foreign_key_parent_key_exact_arity' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_key_exact_arity' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_key_exact_arity' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_key_exact_arity_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_parent_key_exact_arity_blockers' => $nextCounts['blocked'] - $currentCounts['blocked'],
                'foreign_key_parent_key_exact_arity_repaired' => $currentCounts['blocked'] > 0 && $nextCounts['blocked'] === 0,
                'foreign_key_parent_key_exact_arity_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-parent-key-exact-unique-arity',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function parentKeyExactArityRows(array $records, string $phase = 'current'): array
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

            $candidate = self::matchingExactParentKey($catalog, $parent, $parentColumns);
            foreach ($group as $row) {
                $seq = (int) $row['seq'];
                $indexRow = $candidate['rows'][$seq] ?? null;
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_parent_key_exact_arity',
                    'table' => (string) $row['table'],
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => $seq,
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'parent_columns' => $parentColumns,
                    'parent_column_count' => count($parentColumns),
                    'parent_unique_index' => $candidate['index'],
                    'index_key_columns' => $candidate['index_columns'],
                    'index_key_count' => $candidate['index_key_count'],
                    'index_column' => $indexRow['name'] ?? null,
                    'extra_index_columns' => $candidate['extra_columns'],
                    'status' => $candidate['status'],
                    'message' => match ($candidate['status']) {
                        'ok' => "foreign key {$row['table']}->{$parent} parent key exactly matches UNIQUE index {$candidate['index']}",
                        'primary_key_ok' => "foreign key {$row['table']}->{$parent} parent key exactly matches the parent primary key",
                        'wider_parent_unique_index' => "foreign key {$row['table']}->{$parent} parent key is only a prefix of wider UNIQUE index {$candidate['index']}",
                        default => "foreign key {$row['table']}->{$parent} parent key has no exact UNIQUE index arity",
                    },
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
     * @return array{status:string,index:string|null,rows:list<array<string,mixed>>,index_columns:list<string>,index_key_count:int,extra_columns:list<string>}
     */
    private static function matchingExactParentKey(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns): array
    {
        $wanted = array_map('strtolower', $parentColumns);
        $primaryKey = self::primaryKeyColumns($catalog, $parent);
        if (array_map('strtolower', $primaryKey) === $wanted) {
            return [
                'status' => 'primary_key_ok',
                'index' => 'sqlite_primary_key',
                'rows' => array_map(
                    static fn (string $column, int $seqno): array => ['seqno' => $seqno, 'name' => $column, 'key' => 1],
                    $primaryKey,
                    array_keys($primaryKey),
                ),
                'index_columns' => $primaryKey,
                'index_key_count' => count($primaryKey),
                'extra_columns' => [],
            ];
        }

        $firstWider = null;
        $firstWiderRows = [];
        $firstWiderColumns = [];

        foreach ($catalog->indexList($parent) as $index) {
            if ((int) ($index['unique'] ?? 0) !== 1 || (int) ($index['partial'] ?? 0) !== 0) {
                continue;
            }

            $indexName = (string) $index['name'];
            $keyRows = array_values(array_filter(
                $catalog->indexXInfo($indexName),
                static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1,
            ));
            $indexColumns = array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $keyRows);
            $foldedColumns = array_map('strtolower', $indexColumns);
            $prefix = array_slice($foldedColumns, 0, count($wanted));
            if ($prefix !== $wanted) {
                continue;
            }

            if (count($foldedColumns) === count($wanted)) {
                return [
                    'status' => 'ok',
                    'index' => $indexName,
                    'rows' => $keyRows,
                    'index_columns' => $indexColumns,
                    'index_key_count' => count($foldedColumns),
                    'extra_columns' => [],
                ];
            }

            $firstWider ??= $indexName;
            $firstWiderRows = $firstWiderRows === [] ? array_slice($keyRows, 0, count($wanted)) : $firstWiderRows;
            $firstWiderColumns = $firstWiderColumns === [] ? $indexColumns : $firstWiderColumns;
        }

        if ($firstWider !== null) {
            return [
                'status' => 'wider_parent_unique_index',
                'index' => $firstWider,
                'rows' => $firstWiderRows,
                'index_columns' => $firstWiderColumns,
                'index_key_count' => count($firstWiderColumns),
                'extra_columns' => array_slice($firstWiderColumns, count($wanted)),
            ];
        }

        return [
            'status' => 'missing_parent_unique_index',
            'index' => null,
            'rows' => [],
            'index_columns' => [],
            'index_key_count' => 0,
            'extra_columns' => [],
        ];
    }

    /**
     * @return list<string>
     */
    private static function primaryKeyColumns(SQLitePragmaSchemaCatalog $catalog, string $table): array
    {
        $columns = [];
        foreach ($catalog->tableInfo($table) as $row) {
            $pk = (int) ($row['pk'] ?? 0);
            if ($pk > 0) {
                $columns[$pk] = (string) $row['name'];
            }
        }
        ksort($columns);

        return array_values($columns);
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
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,ok:int,primary_key_ok:int,blocked:int,wider_parent_unique_index:int,missing_parent_unique_index:int,extra_index_columns:int}
     */
    private static function arityCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'ok' => 0,
            'primary_key_ok' => 0,
            'blocked' => 0,
            'wider_parent_unique_index' => 0,
            'missing_parent_unique_index' => 0,
            'extra_index_columns' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'ok' || $status === 'primary_key_ok') {
                $counts['ok']++;
                if ($status === 'primary_key_ok') {
                    $counts['primary_key_ok']++;
                }
            } else {
                $counts['blocked']++;
                if (isset($counts[$status])) {
                    $counts[$status]++;
                }
            }
            $counts['extra_index_columns'] += count((array) ($row['extra_index_columns'] ?? []));
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
                (string) ($row['parent_unique_index'] ?? 'missing-index'),
                'parent=' . implode(',', (array) ($row['parent_columns'] ?? [])),
                'index=' . implode(',', (array) ($row['index_key_columns'] ?? [])),
                'extra=' . implode(',', (array) ($row['extra_index_columns'] ?? [])),
                (string) ($row['status'] ?? ''),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next229 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
