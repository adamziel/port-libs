<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext208
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next208 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next208 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext206::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::implicitParentKeyRows($currentRecords, 'current');
        $nextRows = self::implicitParentKeyRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next208',
            'base' => $base['source_id'],
            'current_implicit_parent_keys' => self::rowSummary($currentRows),
            'next_implicit_parent_keys' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next208 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next208 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::implicitCounts($currentRows);
        $nextCounts = self::implicitCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next208',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_implicit_parent_key_source' => 'pragma_foreign_key_list_omitted_parent_columns_plus_table_info_primary_key',
                'foreign_key_implicit_parent_keys' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_implicit_parent_key_source' => 'pragma_foreign_key_list_omitted_parent_columns_plus_table_info_primary_key',
                'foreign_key_implicit_parent_keys' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_implicit_parent_keys' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_implicit_parent_keys' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_implicit_parent_key_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_implicit_parent_key_mismatch_delta' => $nextCounts['arity_mismatch'] + $nextCounts['missing_parent_primary_key'] - $currentCounts['arity_mismatch'] - $currentCounts['missing_parent_primary_key'],
                'foreign_key_implicit_parent_key_repaired' => ($currentCounts['arity_mismatch'] + $currentCounts['missing_parent_primary_key']) > 0
                    && ($nextCounts['arity_mismatch'] + $nextCounts['missing_parent_primary_key']) === 0,
                'foreign_key_implicit_parent_key_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-implicit-parent-key-coverage',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function implicitParentKeyRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (self::tableRecords($records) as $record) {
            $groups = [];
            foreach ($catalog->execute('PRAGMA foreign_key_list(' . self::pragmaArgumentLiteral($record->name) . ')')['rows'] as $row) {
                $groups[(int) $row['id']][] = $row;
            }

            ksort($groups);
            foreach ($groups as $id => $group) {
                usort($group, static fn (array $left, array $right): int => (int) $left['seq'] <=> (int) $right['seq']);
                $omitsParentColumns = false;
                foreach ($group as $row) {
                    if (($row['to'] ?? null) === null || (string) ($row['to'] ?? '') === '') {
                        $omitsParentColumns = true;
                        break;
                    }
                }
                if (!$omitsParentColumns) {
                    continue;
                }

                $parent = (string) $group[0]['table'];
                $parentPrimaryKey = self::primaryKeyColumns($catalog, $parent);
                $childColumns = array_map(static fn (array $row): string => (string) $row['from'], $group);
                $childCount = count($childColumns);
                $parentCount = count($parentPrimaryKey);
                $status = match (true) {
                    $parentCount === 0 => 'missing_parent_primary_key',
                    $childCount !== $parentCount => 'arity_mismatch',
                    default => 'implicit_parent_key_resolved',
                };

                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_implicit_parent_key',
                    'table' => $record->name,
                    'foreign_key_id' => $id,
                    'parent' => $parent,
                    'child_columns' => $childColumns,
                    'child_column_count' => $childCount,
                    'resolved_parent_columns' => $parentPrimaryKey,
                    'parent_primary_key_count' => $parentCount,
                    'status' => $status,
                    'message' => match ($status) {
                        'implicit_parent_key_resolved' => "foreign key {$record->name}->{$parent} omits parent columns and resolves to parent primary key " . implode(',', $parentPrimaryKey),
                        'arity_mismatch' => "foreign key {$record->name}->{$parent} omits parent columns but child arity {$childCount} does not match parent primary-key arity {$parentCount}",
                        default => "foreign key {$record->name}->{$parent} omits parent columns but parent table has no primary key",
                    },
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
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,implicit_parent_key_resolved:int,arity_mismatch:int,missing_parent_primary_key:int,child_columns:int,parent_primary_key_columns:int}
     */
    private static function implicitCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'implicit_parent_key_resolved' => 0,
            'arity_mismatch' => 0,
            'missing_parent_primary_key' => 0,
            'child_columns' => 0,
            'parent_primary_key_columns' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
            $counts['child_columns'] += (int) ($row['child_column_count'] ?? 0);
            $counts['parent_primary_key_columns'] += (int) ($row['parent_primary_key_count'] ?? 0);
        }

        return $counts;
    }

    /**
     * @return list<SQLiteSchemaRecord>
     */
    private static function tableRecords(array $records): array
    {
        return array_values(array_filter($records, static fn (SQLiteSchemaRecord $record): bool => $record->type === 'table'));
    }

    /**
     * @return list<string>
     */
    private static function primaryKeyColumns(SQLitePragmaSchemaCatalog $catalog, string $table): array
    {
        $rows = array_values(array_filter(
            $catalog->tableInfo($table),
            static fn (array $row): bool => (int) ($row['pk'] ?? 0) > 0,
        ));
        usort($rows, static fn (array $left, array $right): int => (int) $left['pk'] <=> (int) $right['pk']);

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
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
                . ':parent_pk=' . implode(',', (array) $row['resolved_parent_columns'])
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next208 records must be SQLiteSchemaRecord instances');
            }
        }
    }

    private static function pragmaArgumentLiteral(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
