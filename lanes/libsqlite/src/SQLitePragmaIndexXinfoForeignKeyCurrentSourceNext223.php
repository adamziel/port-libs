<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext223
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next223 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next223 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext218::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::matchRows($currentRecords, 'current');
        $nextRows = self::matchRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next223',
            'base' => $base['source_id'],
            'current_match' => self::rowSummary($currentRows),
            'next_match' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next223 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next223 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::matchCounts($currentRows);
        $nextCounts = self::matchCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next223',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_match_clause_source' => 'pragma_foreign_key_list_match_column',
                'foreign_key_match_clause' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_match_clause_source' => 'pragma_foreign_key_list_match_column',
                'foreign_key_match_clause' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_match_clause' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_match_clause' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_match_clause_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_match_clause_custom' => $nextCounts['custom_match'] - $currentCounts['custom_match'],
                'foreign_key_match_clause_repaired' => $currentCounts['custom_match'] > 0 && $nextCounts['custom_match'] === 0,
                'foreign_key_match_clause_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-match-clause',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function matchRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase)) as $group) {
            $first = $group[0];
            $match = strtoupper((string) ($first['match'] ?? 'NONE'));
            if ($match === '') {
                $match = 'NONE';
            }
            $status = $match === 'NONE' || $match === 'SIMPLE' ? 'default_match_semantics' : 'custom_match_name';

            foreach ($group as $row) {
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_match_clause',
                    'table' => (string) $row['table'],
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => (int) $row['seq'],
                    'parent' => (string) $row['parent'],
                    'from' => (string) $row['from'],
                    'to' => (string) ($row['to'] ?? ''),
                    'match' => $match,
                    'uses_default_match' => $status === 'default_match_semantics',
                    'status' => $status,
                    'message' => $status === 'default_match_semantics'
                        ? "foreign key {$row['table']}->{$row['parent']} uses SQLite default MATCH semantics"
                        : "foreign key {$row['table']}->{$row['parent']} declares MATCH {$match}; SQLite records the name but still uses built-in MATCH SIMPLE semantics",
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
     * @return array{rows:int,default_match:int,custom_match:int,composite_columns:int}
     */
    private static function matchCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'default_match' => 0,
            'custom_match' => 0,
            'composite_columns' => 0,
        ];
        foreach ($rows as $row) {
            if (($row['status'] ?? null) === 'custom_match_name') {
                $counts['custom_match']++;
            } else {
                $counts['default_match']++;
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
                (string) $row['match'],
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next223 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
