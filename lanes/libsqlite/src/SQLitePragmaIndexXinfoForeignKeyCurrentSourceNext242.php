<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext242
{
    private const ROWID_ALIASES = ['rowid', '_rowid_', 'oid'];

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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next242 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next242 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext239::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::rowidParentKeyRows($currentRecords, 'current');
        $nextRows = self::rowidParentKeyRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next242',
            'base' => $base['source_id'],
            'current_rowid_parent_key_rows' => self::rowSummary($currentRows),
            'next_rowid_parent_key_rows' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next242 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next242 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::rowidCounts($currentRows);
        $nextCounts = self::rowidCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next242',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_rowid_alias_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_rowid_auxiliary_rejection',
                'foreign_key_parent_rowid_alias' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_parent_rowid_alias_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_rowid_auxiliary_rejection',
                'foreign_key_parent_rowid_alias' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_rowid_alias' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_rowid_alias' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_rowid_alias_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_parent_rowid_alias_blockers' => $nextCounts['rowid_alias_parent_key'] - $currentCounts['rowid_alias_parent_key'],
                'foreign_key_parent_rowid_alias_repaired' => $currentCounts['rowid_alias_parent_key'] > 0 && $nextCounts['rowid_alias_parent_key'] === 0,
                'foreign_key_parent_rowid_alias_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-rowid-parent-alias-rejection',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function rowidParentKeyRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase)) as $group) {
            $parent = (string) $group[0]['parent'];
            $declaredParentColumns = self::declaredParentColumns($catalog, $parent);
            $parentColumns = array_map(static fn (array $row): string => (string) $row['to'], $group);
            $candidate = self::matchingRowidAuxiliaryIndex($catalog, $parent);

            foreach ($group as $row) {
                $to = (string) $row['to'];
                $alias = strtolower($to);
                if (!in_array($alias, self::ROWID_ALIASES, true)) {
                    continue;
                }

                $declaredColumn = in_array($alias, $declaredParentColumns, true);
                $status = $declaredColumn ? 'ok_declared_parent_column' : 'rowid_alias_parent_key';
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_parent_rowid_alias',
                    'table' => (string) $row['table'],
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => (int) $row['seq'],
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => $to,
                    'parent_columns' => $parentColumns,
                    'parent_declares_column' => $declaredColumn,
                    'rowid_alias' => $alias,
                    'rowid_auxiliary_index' => $candidate['index'],
                    'rowid_auxiliary_columns' => $candidate['auxiliary_columns'],
                    'rowid_auxiliary_cids' => $candidate['auxiliary_cids'],
                    'status' => $status,
                    'message' => $status === 'rowid_alias_parent_key'
                        ? "foreign key {$row['table']}->{$parent} references rowid alias {$to}; PRAGMA index_xinfo rowid auxiliary rows are not named parent-key columns"
                        : "foreign key {$row['table']}->{$parent} references declared parent column {$to}, not the rowid alias",
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
     * @return list<string>
     */
    private static function declaredParentColumns(SQLitePragmaSchemaCatalog $catalog, string $parent): array
    {
        return array_map(
            static fn (array $row): string => strtolower((string) ($row['name'] ?? '')),
            $catalog->tableInfo($parent),
        );
    }

    /**
     * @return array{index:string|null,auxiliary_columns:list<string>,auxiliary_cids:list<int>}
     */
    private static function matchingRowidAuxiliaryIndex(SQLitePragmaSchemaCatalog $catalog, string $parent): array
    {
        foreach ($catalog->indexList($parent) as $index) {
            if ((int) ($index['unique'] ?? 0) !== 1 || (int) ($index['partial'] ?? 0) !== 0) {
                continue;
            }

            $auxiliaryRows = array_values(array_filter(
                $catalog->indexXInfo((string) $index['name']),
                static fn (array $row): bool => (int) ($row['key'] ?? 0) === 0,
            ));
            $rowidRows = array_values(array_filter(
                $auxiliaryRows,
                static fn (array $row): bool => (int) ($row['cid'] ?? -999) === -1,
            ));
            if ($rowidRows === []) {
                continue;
            }

            return [
                'index' => (string) $index['name'],
                'auxiliary_columns' => array_map(
                    static fn (array $row): string => $row['name'] === null ? 'rowid' : (string) $row['name'],
                    $rowidRows,
                ),
                'auxiliary_cids' => array_map(static fn (array $row): int => (int) ($row['cid'] ?? -999), $rowidRows),
            ];
        }

        return ['index' => null, 'auxiliary_columns' => [], 'auxiliary_cids' => []];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,rowid_alias_parent_key:int,ok_declared_parent_column:int,rowid_auxiliary_indexes:int,rowid_auxiliary_rows:int}
     */
    private static function rowidCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'rowid_alias_parent_key' => 0,
            'ok_declared_parent_column' => 0,
            'rowid_auxiliary_indexes' => 0,
            'rowid_auxiliary_rows' => 0,
        ];
        $indexes = [];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
            if (($row['rowid_auxiliary_index'] ?? null) !== null) {
                $indexes[(string) $row['rowid_auxiliary_index']] = true;
                $counts['rowid_auxiliary_rows'] += count((array) ($row['rowid_auxiliary_columns'] ?? []));
            }
        }
        $counts['rowid_auxiliary_indexes'] = count($indexes);

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
                'alias=' . (string) ($row['rowid_alias'] ?? ''),
                'aux=' . (string) ($row['rowid_auxiliary_index'] ?? 'none'),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next242 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
