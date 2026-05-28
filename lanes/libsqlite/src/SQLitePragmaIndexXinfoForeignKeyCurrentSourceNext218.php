<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext218
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next218 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next218 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext212::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::restrictTimingRows($currentRecords, 'current');
        $nextRows = self::restrictTimingRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next218',
            'base' => $base['source_id'],
            'current_restrict_timing' => self::rowSummary($currentRows),
            'next_restrict_timing' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next218 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next218 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::restrictTimingCounts($currentRows);
        $nextCounts = self::restrictTimingCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next218',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_restrict_timing_source' => 'pragma_foreign_key_list_actions_plus_schema_deferral',
                'foreign_key_restrict_timing' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_restrict_timing_source' => 'pragma_foreign_key_list_actions_plus_schema_deferral',
                'foreign_key_restrict_timing' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_restrict_timing' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_restrict_timing' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_restrict_timing_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_restrict_timing_immediate' => $nextCounts['restrict_immediate'] - $currentCounts['restrict_immediate'],
                'foreign_key_restrict_timing_repaired' => $currentCounts['restrict_immediate'] > 0 && $nextCounts['restrict_immediate'] === 0,
                'foreign_key_restrict_timing_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-restrict-deferral-timing',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function restrictTimingRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $deferrals = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext169::deferralMap($records);
        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase)) as $group) {
            $first = $group[0];
            $table = (string) $first['table'];
            $id = (int) $first['id'];
            $onDelete = strtoupper((string) ($first['on_delete'] ?? 'NO ACTION'));
            $onUpdate = strtoupper((string) ($first['on_update'] ?? 'NO ACTION'));
            if ($onDelete !== 'RESTRICT' && $onUpdate !== 'RESTRICT') {
                continue;
            }

            $deferral = $deferrals[strtolower($table) . '#' . $id] ?? ['deferrable' => false, 'initially_deferred' => false];
            if (!$deferral['deferrable']) {
                continue;
            }

            foreach ($group as $row) {
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_restrict_timing',
                    'table' => $table,
                    'foreign_key_id' => $id,
                    'seq' => (int) $row['seq'],
                    'parent' => (string) $row['parent'],
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'on_delete' => $onDelete,
                    'on_update' => $onUpdate,
                    'deferrable' => true,
                    'initially_deferred' => (bool) $deferral['initially_deferred'],
                    'deferred_until_commit' => false,
                    'restrict_actions' => array_values(array_filter([
                        $onDelete === 'RESTRICT' ? 'delete' : null,
                        $onUpdate === 'RESTRICT' ? 'update' : null,
                    ])),
                    'status' => 'restrict_immediate',
                    'message' => "foreign key {$table} uses RESTRICT action, so SQLite checks it immediately even when deferrable",
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
     * @return array{rows:int,restrict_immediate:int,initially_deferred:int,delete_restrict:int,update_restrict:int,composite_columns:int}
     */
    private static function restrictTimingCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'restrict_immediate' => 0,
            'initially_deferred' => 0,
            'delete_restrict' => 0,
            'update_restrict' => 0,
            'composite_columns' => 0,
        ];
        foreach ($rows as $row) {
            if (($row['status'] ?? null) === 'restrict_immediate') {
                $counts['restrict_immediate']++;
            }
            if (($row['initially_deferred'] ?? false) === true) {
                $counts['initially_deferred']++;
            }
            if (in_array('delete', $row['restrict_actions'] ?? [], true)) {
                $counts['delete_restrict']++;
            }
            if (in_array('update', $row['restrict_actions'] ?? [], true)) {
                $counts['update_restrict']++;
            }
            if ((int) ($row['seq'] ?? 0) > 0) {
                $counts['composite_columns']++;
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
                (string) $row['from'] . '->' . (string) $row['parent'] . '.' . (string) $row['to'],
                (string) $row['on_delete'] . '/' . (string) $row['on_update'],
                ($row['initially_deferred'] ?? false) ? 'initially-deferred' : 'initially-immediate',
                implode(',', $row['restrict_actions'] ?? []),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next218 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
