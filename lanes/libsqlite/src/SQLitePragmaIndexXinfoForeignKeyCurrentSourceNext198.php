<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext198
{
    /**
     * @param list<SQLiteSchemaRecord> $currentRecords
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param list<SQLiteSchemaRecord> $nextRecords
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array<string,mixed>
     */
    public static function currentNextPageFromCatalog(
        array $currentRecords,
        array $currentTables,
        array $nextRecords,
        array $nextTables,
        string $indexXinfoSql,
        int $offset = 0,
        int $limit = 198,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next198 offset must be non-negative');
        }
        if ($limit <= 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next198 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext195::currentNextPageFromCatalog(
            $currentRecords,
            $currentTables,
            $nextRecords,
            $nextTables,
            $indexXinfoSql,
            0,
            PHP_INT_MAX,
            null,
            $tableValuedIndexXinfo,
        );

        $currentRows = self::withoutRowidParentKeyRows($currentRecords, 'current');
        $nextRows = self::withoutRowidParentKeyRows($nextRecords, 'next');
        $covered = self::coveredForeignKeyKeys([...$currentRows, ...$nextRows]);
        $decoratedBaseRows = array_map(
            static fn (array $row): array => self::decorateCoveredParentKeyRow($row, $covered),
            $base['rows'],
        );

        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next198',
            'base' => $base['source_id'],
            'current_without_rowid_parent_keys' => self::rowSummary($currentRows),
            'next_without_rowid_parent_keys' => self::rowSummary($nextRows),
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $allRows = array_values(array_merge($decoratedBaseRows, $currentRows, $nextRows));
        $total = count($allRows);
        $rows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($rows);
        $complete = $nextOffset >= $total;
        $currentCounts = self::withoutRowidCounts($currentRows);
        $nextCounts = self::withoutRowidCounts($nextRows);
        $blocking = self::adjustBlocking($base['next_state']['blocking'] ?? [], $decoratedBaseRows, $covered);

        return [
            ...$base,
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($rows),
            'total' => $total,
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_without_rowid_parent_source' => 'pragma_table_info_without_rowid_primary_key',
                'foreign_key_without_rowid_parent_keys' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...$base['next_source'],
                'foreign_key_without_rowid_parent_source' => 'pragma_table_info_without_rowid_primary_key',
                'foreign_key_without_rowid_parent_keys' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_without_rowid_parent_rows' => count($currentRows),
                'foreign_key_without_rowid_parent' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_without_rowid_parent_rows' => count($nextRows),
                'foreign_key_without_rowid_parent' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_without_rowid_parent_rows' => count($nextRows) - count($currentRows),
                'foreign_key_without_rowid_parent_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
                'foreign_key_without_rowid_parent_repaired' => $currentCounts['covered_foreign_keys'] === 0 && $nextCounts['covered_foreign_keys'] > 0,
                'foreign_key_without_rowid_parent_regressed' => $currentCounts['covered_foreign_keys'] > 0 && $nextCounts['covered_foreign_keys'] === 0,
            ],
            'next_state' => [
                ...$base['next_state'],
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
            'next' => $complete ? null : [
                'source_id' => $sourceId,
                'offset' => $nextOffset,
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function withoutRowidParentKeyRows(array $records, string $side = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $tables = self::tableRecordMap($records);
        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $side)) as $group) {
            $table = (string) $group[0]['table'];
            $parent = (string) $group[0]['parent'];
            $parentRecord = $tables[strtolower($parent)] ?? null;
            if ($parentRecord === null || $parentRecord->sql === null || !self::isWithoutRowid($parentRecord->sql)) {
                continue;
            }

            $parentColumns = array_map(static fn (array $row): string => (string) $row['to'], $group);
            $primaryKeyRows = self::primaryKeyRows($catalog, $parent);
            $primaryKeyColumns = array_map(static fn (array $row): string => (string) $row['name'], $primaryKeyRows);
            if (!self::sameColumns($parentColumns, $primaryKeyColumns)) {
                continue;
            }

            foreach ($group as $row) {
                $pkRow = $primaryKeyRows[(int) $row['seq']] ?? null;
                $rows[] = [
                    'side' => $side,
                    'kind' => 'foreign_key_without_rowid_parent_key',
                    'table' => $table,
                    'fkid' => (int) $row['id'],
                    'seq' => (int) $row['seq'],
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'index' => 'without-rowid-primary-key',
                    'index_unique' => 1,
                    'index_partial' => 0,
                    'index_seqno' => $pkRow['pk'] ?? null,
                    'index_cid' => $pkRow['cid'] ?? null,
                    'index_name' => $pkRow['name'] ?? null,
                    'primary_key_columns' => $primaryKeyColumns,
                    'status' => 'ok',
                    'message' => "foreign key {$table}->{$parent} parent key is covered by WITHOUT ROWID primary key column {$row['to']}",
                ];
            }
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['side'], $left['table'], $left['fkid'], $left['seq']]
                <=> [$right['side'], $right['table'], $right['fkid'], $right['seq']],
        );

        return $rows;
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
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,SQLiteSchemaRecord>
     */
    private static function tableRecordMap(array $records): array
    {
        $tables = [];
        foreach ($records as $record) {
            if ($record->type === 'table') {
                $tables[strtolower($record->name)] = $record;
            }
        }

        return $tables;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function primaryKeyRows(SQLitePragmaSchemaCatalog $catalog, string $table): array
    {
        $rows = array_values(array_filter(
            $catalog->execute('PRAGMA table_info(' . self::pragmaArgumentLiteral($table) . ')')['rows'],
            static fn (array $row): bool => (int) ($row['pk'] ?? 0) > 0,
        ));
        usort($rows, static fn (array $left, array $right): int => (int) $left['pk'] <=> (int) $right['pk']);

        return $rows;
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private static function sameColumns(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            return false;
        }
        foreach ($left as $index => $column) {
            if (strcasecmp($column, $right[$index]) !== 0) {
                return false;
            }
        }

        return true;
    }

    private static function isWithoutRowid(string $sql): bool
    {
        return preg_match('/\)\s*WITHOUT\s+ROWID\b/i', $sql) === 1;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,bool>
     */
    private static function coveredForeignKeyKeys(array $rows): array
    {
        $covered = [];
        foreach ($rows as $row) {
            $covered[self::foreignKeyKey((string) $row['side'], (string) $row['table'], (int) $row['fkid'])] = true;
        }

        return $covered;
    }

    /**
     * @param array<string,bool> $covered
     */
    private static function decorateCoveredParentKeyRow(array $row, array $covered): array
    {
        if (($row['kind'] ?? null) !== 'foreign_key_parent_key' || ($row['status'] ?? null) !== 'missing_parent_key') {
            return $row;
        }

        if (!isset($covered[self::foreignKeyKey((string) $row['side'], (string) $row['table'], (int) $row['fkid'])])) {
            return $row;
        }

        return [
            ...$row,
            'index' => 'without-rowid-primary-key',
            'status' => 'ok',
            'without_rowid_parent_key' => true,
            'message' => "foreign key {$row['table']}->{$row['parent']} parent columns are backed by the WITHOUT ROWID table primary key",
        ];
    }

    /**
     * @param list<string> $baseBlocking
     * @param list<array<string,mixed>> $rows
     * @param array<string,bool> $covered
     * @return list<string>
     */
    private static function adjustBlocking(array $baseBlocking, array $rows, array $covered): array
    {
        if ($covered === []) {
            return $baseBlocking;
        }

        $hasUncoveredMissingParentKey = false;
        foreach ($rows as $row) {
            if (($row['kind'] ?? null) !== 'foreign_key_parent_key' || ($row['side'] ?? null) !== 'next') {
                continue;
            }
            if (($row['status'] ?? null) === 'missing_parent_key') {
                $hasUncoveredMissingParentKey = true;
                break;
            }
        }

        if ($hasUncoveredMissingParentKey) {
            return $baseBlocking;
        }

        return array_values(array_filter(
            $baseBlocking,
            static fn (string $blocker): bool => $blocker !== 'foreign_key_parent_unique_index',
        ));
    }

    private static function foreignKeyKey(string $side, string $table, int $id): string
    {
        return strtolower($side) . ':' . strtolower($table) . '#' . $id;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,covered_foreign_keys:int,covered_columns:int,composite_columns:int}
     */
    private static function withoutRowidCounts(array $rows): array
    {
        $keys = [];
        $columns = 0;
        foreach ($rows as $row) {
            $keys[self::foreignKeyKey((string) $row['side'], (string) $row['table'], (int) $row['fkid'])] = true;
            $columns++;
        }

        return [
            'rows' => count($rows),
            'covered_foreign_keys' => count($keys),
            'covered_columns' => $columns,
            'composite_columns' => count(array_filter($rows, static fn (array $row): bool => count($row['primary_key_columns'] ?? []) > 1)),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function rowSummary(array $rows, bool $includeSide = true): array
    {
        $summary = array_map(
            static fn (array $row): string => ($includeSide ? $row['side'] . ':' : '')
                . $row['table'] . '#' . $row['fkid'] . '.' . $row['seq'] . ':' . $row['from']
                . '->' . $row['parent'] . '.' . $row['to'] . ':without-rowid-pk=' . implode('|', $row['primary_key_columns'] ?? []),
            $rows,
        );
        sort($summary);

        return $summary;
    }

    /** @param list<mixed> $records */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next198 records must be SQLiteSchemaRecord instances');
            }
        }
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next198 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next198 cursor offset does not match the requested page offset');
        }
    }

    private static function pragmaArgumentLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
