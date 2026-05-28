<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext230
{
    private const PSEUDO_ROWID_NAMES = ['rowid', '_rowid_', 'oid'];

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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next230 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next230 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext227::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::pseudoRowidParentRows($currentRecords, 'current');
        $nextRows = self::pseudoRowidParentRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next230',
            'base' => $base['source_id'],
            'current_pseudo_rowid_parent_keys' => self::rowSummary($currentRows),
            'next_pseudo_rowid_parent_keys' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next230 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next230 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::pseudoRowidCounts($currentRows);
        $nextCounts = self::pseudoRowidCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next230',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_pseudo_rowid_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_table_info_named_columns',
                'foreign_key_parent_pseudo_rowid' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_parent_pseudo_rowid_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_table_info_named_columns',
                'foreign_key_parent_pseudo_rowid' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_pseudo_rowid' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_pseudo_rowid' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_pseudo_rowid_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_parent_pseudo_rowid_blockers' => $nextCounts['pseudo_rowid_parent_key'] - $currentCounts['pseudo_rowid_parent_key'],
                'foreign_key_parent_pseudo_rowid_repaired' => $currentCounts['pseudo_rowid_parent_key'] > 0 && $nextCounts['pseudo_rowid_parent_key'] === 0,
                'foreign_key_parent_pseudo_rowid_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-parent-pseudo-rowid-rejection',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function pseudoRowidParentRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase) as $row) {
            $to = strtolower((string) ($row['to'] ?? ''));
            if (!in_array($to, self::PSEUDO_ROWID_NAMES, true)) {
                continue;
            }

            $declaredColumns = self::declaredColumnNames($catalog, (string) $row['parent']);
            $declared = in_array($to, $declaredColumns, true);
            $status = $declared ? 'declared_parent_column' : 'pseudo_rowid_parent_key';
            $rows[] = [
                'phase' => $phase,
                'kind' => 'foreign_key_parent_pseudo_rowid',
                'table' => (string) $row['table'],
                'foreign_key_id' => (int) $row['id'],
                'seq' => (int) $row['seq'],
                'parent' => (string) $row['parent'],
                'from' => (string) $row['from'],
                'to' => (string) $row['to'],
                'pseudo_rowid_name' => (string) $row['to'],
                'declared_parent_columns' => $declaredColumns,
                'declared_parent_column' => $declared,
                'status' => $status,
                'message' => $declared
                    ? "foreign key {$row['table']} references declared parent column {$row['parent']}.{$row['to']}"
                    : "foreign key {$row['table']} references SQLite pseudo-rowid name {$row['parent']}.{$row['to']}, which is not a named parent key",
            ];
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['phase'], $left['table'], $left['foreign_key_id'], $left['seq']]
                <=> [$right['phase'], $right['table'], $right['foreign_key_id'], $right['seq']],
        );

        return $rows;
    }

    /**
     * @return list<string>
     */
    private static function declaredColumnNames(SQLitePragmaSchemaCatalog $catalog, string $table): array
    {
        return array_map(
            static fn (array $row): string => strtolower((string) $row['name']),
            $catalog->tableInfo($table),
        );
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,pseudo_rowid_parent_key:int,declared_parent_column:int,foreign_keys:int,parent_tables:int}
     */
    private static function pseudoRowidCounts(array $rows): array
    {
        $foreignKeys = [];
        $parents = [];
        $counts = [
            'rows' => count($rows),
            'pseudo_rowid_parent_key' => 0,
            'declared_parent_column' => 0,
            'foreign_keys' => 0,
            'parent_tables' => 0,
        ];
        foreach ($rows as $row) {
            $foreignKeys[(string) $row['table'] . '#' . (int) $row['foreign_key_id']] = true;
            $parents[strtolower((string) $row['parent'])] = true;
            if (($row['status'] ?? null) === 'declared_parent_column') {
                $counts['declared_parent_column']++;
            } else {
                $counts['pseudo_rowid_parent_key']++;
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
                'declared=' . ($row['declared_parent_column'] ? '1' : '0'),
                (string) $row['status'],
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next230 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
