<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext226
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next226 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next226 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext219::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::missingParentTableRows($currentRecords, 'current');
        $nextRows = self::missingParentTableRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next226',
            'base' => $base['source_id'],
            'current_missing_parent_tables' => self::rowSummary($currentRows),
            'next_missing_parent_tables' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next226 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next226 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::missingParentCounts($currentRows);
        $nextCounts = self::missingParentCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next226',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_missing_parent_table_source' => 'pragma_foreign_key_list_parent_table_plus_schema_table_catalog',
                'foreign_key_missing_parent_table' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_missing_parent_table_source' => 'pragma_foreign_key_list_parent_table_plus_schema_table_catalog',
                'foreign_key_missing_parent_table' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_missing_parent_table' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_missing_parent_table' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_missing_parent_table_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_missing_parent_table_blockers' => $nextCounts['missing_parent_table'] - $currentCounts['missing_parent_table'],
                'foreign_key_missing_parent_table_repaired' => $currentCounts['missing_parent_table'] > 0 && $nextCounts['missing_parent_table'] === 0,
                'foreign_key_missing_parent_table_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-parent-table-catalog-resolution',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function missingParentTableRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $tables = [];
        foreach ($records as $record) {
            if ($record->type === 'table') {
                $tables[strtolower($record->name)] = $record->name;
            }
        }

        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase)) as $group) {
            $parent = (string) $group[0]['parent'];
            if ($parent === '' || isset($tables[strtolower($parent)])) {
                continue;
            }

            foreach ($group as $row) {
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_missing_parent_table',
                    'table' => (string) $row['table'],
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => (int) $row['seq'],
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => $row['to'] === null ? null : (string) $row['to'],
                    'available_parent_tables' => array_values($tables),
                    'status' => 'missing_parent_table',
                    'message' => "foreign key {$row['table']} references missing parent table {$parent}",
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
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,missing_parent_table:int,foreign_keys:int,parent_tables:int}
     */
    private static function missingParentCounts(array $rows): array
    {
        $foreignKeys = [];
        $parents = [];
        foreach ($rows as $row) {
            $foreignKeys[(string) $row['table'] . '#' . (int) $row['foreign_key_id']] = true;
            $parents[strtolower((string) $row['parent'])] = true;
        }

        return [
            'rows' => count($rows),
            'missing_parent_table' => count($rows),
            'foreign_keys' => count($foreignKeys),
            'parent_tables' => count($parents),
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
                (string) $row['from'] . '->' . (string) $row['parent'] . '.' . (($row['to'] ?? null) === null ? 'null' : (string) $row['to']),
                (string) $row['status'],
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next226 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
