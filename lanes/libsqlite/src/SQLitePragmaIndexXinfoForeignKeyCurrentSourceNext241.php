<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext241
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next241 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next241 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext238::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::implicitParentReferenceRows($currentRecords, 'current');
        $nextRows = self::implicitParentReferenceRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next241',
            'base' => $base['source_id'],
            'current_implicit_parent_references' => self::rowSummary($currentRows),
            'next_implicit_parent_references' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next241 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next241 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::implicitCounts($currentRows);
        $nextCounts = self::implicitCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next241',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_implicit_parent_reference_source' => 'raw_pragma_foreign_key_list_null_to_plus_parent_primary_key_resolution',
                'foreign_key_implicit_parent_references' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_implicit_parent_reference_source' => 'raw_pragma_foreign_key_list_null_to_plus_parent_primary_key_resolution',
                'foreign_key_implicit_parent_references' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_implicit_parent_references' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_implicit_parent_references' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_implicit_parent_reference_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_implicit_parent_reference_blockers' => $nextCounts['blocked'] - $currentCounts['blocked'],
                'foreign_key_implicit_parent_reference_repaired' => $currentCounts['blocked'] > 0 && $nextCounts['blocked'] === 0,
                'foreign_key_implicit_parent_reference_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-implicit-parent-primary-key-resolution',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function implicitParentReferenceRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $derived = self::derivedForeignKeyRows($records, $phase);
        $primaryKeys = self::primaryKeys($catalog, $records);
        $rows = [];

        foreach ($records as $record) {
            if ($record->type !== 'table') {
                continue;
            }

            foreach ($catalog->foreignKeyList($record->name) as $raw) {
                $key = self::fkKey($record->name, (int) $raw['id'], (int) $raw['seq']);
                $resolved = $derived[$key]['to'] ?? null;
                $rawTo = $raw['to'];
                $parent = (string) $raw['table'];
                $parentPk = $primaryKeys[strtolower($parent)] ?? [];
                $implicit = $rawTo === null || $rawTo === '';
                $status = 'explicit_parent_column';
                if ($implicit) {
                    $status = $resolved === null || $resolved === ''
                        ? 'missing_implicit_parent_primary_key'
                        : 'ok_implicit_parent_primary_key';
                }

                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_implicit_parent_reference',
                    'table' => $record->name,
                    'foreign_key_id' => (int) $raw['id'],
                    'seq' => (int) $raw['seq'],
                    'parent' => $parent,
                    'from' => (string) $raw['from'],
                    'raw_to' => $rawTo,
                    'resolved_to' => $resolved,
                    'parent_primary_key' => $parentPk,
                    'implicit_parent_reference' => $implicit,
                    'parent_primary_key_complete' => $parentPk !== [] && $resolved !== null && $resolved !== '',
                    'status' => $status,
                    'message' => match ($status) {
                        'ok_implicit_parent_primary_key' => "foreign key {$record->name}->{$parent} omits parent columns and resolves to parent PRIMARY KEY {$resolved}",
                        'missing_implicit_parent_primary_key' => "foreign key {$record->name}->{$parent} omits parent columns but the parent PRIMARY KEY cannot be resolved",
                        default => "foreign key {$record->name}->{$parent} names explicit parent column {$rawTo}",
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
     * @return array<string,array<string,mixed>>
     */
    private static function derivedForeignKeyRows(array $records, string $phase): array
    {
        $rows = [];
        try {
            $derivedRows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase);
        } catch (InvalidArgumentException) {
            $derivedRows = [];
        }

        foreach ($derivedRows as $row) {
            $rows[self::fkKey((string) $row['table'], (int) $row['id'], (int) $row['seq'])] = $row;
        }

        return $rows;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,list<string>>
     */
    private static function primaryKeys(SQLitePragmaSchemaCatalog $catalog, array $records): array
    {
        $primaryKeys = [];
        foreach ($records as $record) {
            if ($record->type !== 'table') {
                continue;
            }

            $columns = [];
            foreach ($catalog->tableInfo($record->name) as $row) {
                $pk = (int) ($row['pk'] ?? 0);
                if ($pk > 0) {
                    $columns[$pk] = (string) $row['name'];
                }
            }
            ksort($columns);
            $primaryKeys[strtolower($record->name)] = array_values($columns);
        }

        return $primaryKeys;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,implicit:int,explicit:int,ok_implicit_parent_primary_key:int,missing_implicit_parent_primary_key:int,blocked:int,resolved:int}
     */
    private static function implicitCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'implicit' => 0,
            'explicit' => 0,
            'ok_implicit_parent_primary_key' => 0,
            'missing_implicit_parent_primary_key' => 0,
            'blocked' => 0,
            'resolved' => 0,
        ];
        foreach ($rows as $row) {
            if (($row['implicit_parent_reference'] ?? false) === true) {
                $counts['implicit']++;
            } else {
                $counts['explicit']++;
            }
            if (($row['resolved_to'] ?? null) !== null && ($row['resolved_to'] ?? '') !== '') {
                $counts['resolved']++;
            }
            $status = (string) ($row['status'] ?? '');
            if ($status === 'ok_implicit_parent_primary_key') {
                $counts['ok_implicit_parent_primary_key']++;
            }
            if ($status === 'missing_implicit_parent_primary_key') {
                $counts['missing_implicit_parent_primary_key']++;
                $counts['blocked']++;
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
                (string) $row['from'] . '->' . (string) $row['parent'] . '.raw=' . (string) ($row['raw_to'] ?? ''),
                'resolved=' . (string) ($row['resolved_to'] ?? ''),
                'pk=' . implode(',', $row['parent_primary_key'] ?? []),
                (string) ($row['status'] ?? ''),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next241 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
