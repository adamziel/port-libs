<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext243
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next243 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next243 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext241::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::foreignKeyAffinityRows($currentRecords, 'current');
        $nextRows = self::foreignKeyAffinityRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next243',
            'base' => $base['source_id'],
            'current_fk_affinity' => self::rowSummary($currentRows),
            'next_fk_affinity' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next243 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next243 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::affinityCounts($currentRows);
        $nextCounts = self::affinityCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next243',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_affinity_source' => 'pragma_foreign_key_list_plus_table_info_parent_affinity',
                'foreign_key_affinity' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_affinity_source' => 'pragma_foreign_key_list_plus_table_info_parent_affinity',
                'foreign_key_affinity' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_affinity' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_affinity' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_affinity_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_affinity_mismatches' => $nextCounts['affinity_mismatch'] - $currentCounts['affinity_mismatch'],
                'foreign_key_affinity_repaired' => $currentCounts['affinity_mismatch'] > 0 && $nextCounts['affinity_mismatch'] === 0,
                'foreign_key_affinity_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-parent-affinity-comparison',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function foreignKeyAffinityRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $tableInfo = self::tableInfoByTable($catalog, $records);
        $implicitParentRows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext241::implicitParentReferenceRows($records, $phase);
        $implicitParentMap = [];
        foreach ($implicitParentRows as $row) {
            $implicitParentMap[self::fkKey((string) $row['table'], (int) $row['foreign_key_id'], (int) $row['seq'])] = $row['resolved_to'];
        }

        $rows = [];
        foreach ($records as $record) {
            if ($record->type !== 'table') {
                continue;
            }

            $childColumns = $tableInfo[strtolower($record->name)] ?? [];
            foreach ($catalog->foreignKeyList($record->name) as $foreignKey) {
                $parent = (string) $foreignKey['table'];
                $from = (string) $foreignKey['from'];
                $to = (string) ($foreignKey['to'] ?? '');
                if ($to === '') {
                    $to = (string) ($implicitParentMap[self::fkKey($record->name, (int) $foreignKey['id'], (int) $foreignKey['seq'])] ?? '');
                }

                $child = $childColumns[strtolower($from)] ?? null;
                $parentColumn = ($tableInfo[strtolower($parent)] ?? [])[strtolower($to)] ?? null;
                $childAffinity = self::affinity((string) ($child['type'] ?? ''));
                $parentAffinity = self::affinity((string) ($parentColumn['type'] ?? ''));
                $status = $to === '' || $parentColumn === null
                    ? 'missing_parent_column'
                    : ($child === null ? 'missing_child_column' : ($childAffinity === $parentAffinity ? 'affinity_match' : 'affinity_mismatch'));

                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_affinity',
                    'table' => $record->name,
                    'foreign_key_id' => (int) $foreignKey['id'],
                    'seq' => (int) $foreignKey['seq'],
                    'parent' => $parent,
                    'from' => $from,
                    'to' => $to === '' ? null : $to,
                    'child_type' => $child['type'] ?? null,
                    'parent_type' => $parentColumn['type'] ?? null,
                    'child_affinity' => $childAffinity,
                    'parent_affinity' => $parentAffinity,
                    'parent_affinity_applies' => $status !== 'missing_parent_column',
                    'status' => $status,
                    'message' => match ($status) {
                        'affinity_match' => "foreign key {$record->name}.{$from} uses matching {$parentAffinity} parent affinity from {$parent}.{$to}",
                        'affinity_mismatch' => "foreign key {$record->name}.{$from} uses child {$childAffinity} affinity but parent {$parent}.{$to} applies {$parentAffinity} affinity",
                        'missing_child_column' => "foreign key {$record->name}.{$from} references a missing child column",
                        default => "foreign key {$record->name}.{$from} cannot resolve parent affinity for {$parent}",
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

    private static function affinity(string $type): string
    {
        $type = strtoupper($type);
        if (str_contains($type, 'INT')) {
            return 'INTEGER';
        }
        if (str_contains($type, 'CHAR') || str_contains($type, 'CLOB') || str_contains($type, 'TEXT')) {
            return 'TEXT';
        }
        if (str_contains($type, 'BLOB') || trim($type) === '') {
            return 'BLOB';
        }
        if (str_contains($type, 'REAL') || str_contains($type, 'FLOA') || str_contains($type, 'DOUB')) {
            return 'REAL';
        }

        return 'NUMERIC';
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,affinity_match:int,affinity_mismatch:int,missing_child_column:int,missing_parent_column:int,parent_integer:int,parent_text:int,parent_numeric:int,parent_real:int,parent_blob:int}
     */
    private static function affinityCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'affinity_match' => 0,
            'affinity_mismatch' => 0,
            'missing_child_column' => 0,
            'missing_parent_column' => 0,
            'parent_integer' => 0,
            'parent_text' => 0,
            'parent_numeric' => 0,
            'parent_real' => 0,
            'parent_blob' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
            $parent = strtolower((string) ($row['parent_affinity'] ?? ''));
            $key = 'parent_' . $parent;
            if (array_key_exists($key, $counts)) {
                $counts[$key]++;
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
                (string) $row['from'] . '->' . (string) $row['parent'] . '.' . (string) ($row['to'] ?? ''),
                'child=' . (string) $row['child_affinity'],
                'parent=' . (string) $row['parent_affinity'],
                (string) $row['status'],
            ], static fn (?string $part): bool => $part !== null)),
            $rows,
        );
        sort($summary);

        return $summary;
    }

    private static function fkKey(string $table, int $id, int $seq): string
    {
        return strtolower($table) . '#' . $id . '.' . $seq;
    }

    /**
     * @param list<mixed> $records
     */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next243 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
