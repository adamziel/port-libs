<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext227
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next227 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next227 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext219::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::childKeySuffixRows($currentRecords, 'current');
        $nextRows = self::childKeySuffixRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next227',
            'base' => $base['source_id'],
            'current_child_suffix_indexes' => self::rowSummary($currentRows),
            'next_child_suffix_indexes' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next227 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next227 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::suffixCounts($currentRows);
        $nextCounts = self::suffixCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next227',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_child_suffix_index_source' => 'pragma_foreign_key_list_child_columns_plus_pragma_index_xinfo_nonleading_terms',
                'foreign_key_child_suffix_indexes' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_child_suffix_index_source' => 'pragma_foreign_key_list_child_columns_plus_pragma_index_xinfo_nonleading_terms',
                'foreign_key_child_suffix_indexes' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_child_suffix_indexes' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_child_suffix_indexes' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_child_suffix_index_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_child_suffix_index_blockers' => $nextCounts['suffix_child_index'] - $currentCounts['suffix_child_index'],
                'foreign_key_child_suffix_index_repaired' => $currentCounts['suffix_child_index'] > 0 && $nextCounts['suffix_child_index'] === 0,
                'foreign_key_child_suffix_index_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-child-index-leftmost-prefix',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function childKeySuffixRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase)) as $group) {
            $table = (string) $group[0]['table'];
            $childColumns = array_map(static fn (array $row): string => (string) $row['from'], $group);
            if (in_array('', $childColumns, true)) {
                continue;
            }

            $candidate = self::suffixChildIndex($catalog, $table, $childColumns);
            if ($candidate === null) {
                continue;
            }

            foreach ($group as $row) {
                $seq = (int) $row['seq'];
                $actualPosition = $candidate['offset'] + $seq;
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_child_suffix_index',
                    'table' => $table,
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => $seq,
                    'parent' => (string) $row['parent'],
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'child_columns' => $childColumns,
                    'suffix_child_index' => $candidate['index'],
                    'suffix_child_index_columns' => $candidate['original_columns'],
                    'suffix_child_index_collations' => $candidate['collations'],
                    'leading_columns' => $candidate['leading_columns'],
                    'leading_terms' => $candidate['offset'],
                    'expected_position' => $seq,
                    'actual_position' => $actualPosition,
                    'status' => 'suffix_child_index',
                    'message' => "foreign key {$table} child columns are present in index {$candidate['index']} only after non-leading key terms",
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
     * @param list<string> $childColumns
     * @return array{index:string,offset:int,original_columns:list<string>,collations:list<string>,leading_columns:list<string>}|null
     */
    private static function suffixChildIndex(SQLitePragmaSchemaCatalog $catalog, string $table, array $childColumns): ?array
    {
        $wanted = array_map('strtolower', $childColumns);

        foreach ($catalog->indexList($table) as $index) {
            if ((int) ($index['partial'] ?? 0) !== 0) {
                continue;
            }

            $xinfo = $catalog->indexXInfo((string) $index['name']);
            $keyRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1));
            $columns = array_map(static fn (array $row): string => strtolower((string) ($row['name'] ?? '')), $keyRows);
            if (array_slice($columns, 0, count($wanted)) === $wanted) {
                continue;
            }

            for ($offset = 1; $offset <= count($columns) - count($wanted); $offset++) {
                if (array_slice($columns, $offset, count($wanted)) !== $wanted) {
                    continue;
                }

                return [
                    'index' => (string) $index['name'],
                    'offset' => $offset,
                    'original_columns' => array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $keyRows),
                    'collations' => array_map(static fn (array $row): string => strtoupper((string) ($row['coll'] ?? 'BINARY')), $keyRows),
                    'leading_columns' => array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), array_slice($keyRows, 0, $offset)),
                ];
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,suffix_child_index:int,foreign_keys:int,leading_terms:int,max_leading_terms:int}
     */
    private static function suffixCounts(array $rows): array
    {
        $foreignKeys = [];
        $leadingTerms = 0;
        $maxLeadingTerms = 0;
        foreach ($rows as $row) {
            $foreignKeys[(string) $row['table'] . '#' . (int) $row['foreign_key_id']] = true;
            $leadingTerms += (int) ($row['leading_terms'] ?? 0);
            $maxLeadingTerms = max($maxLeadingTerms, (int) ($row['leading_terms'] ?? 0));
        }

        return [
            'rows' => count($rows),
            'suffix_child_index' => count($rows),
            'foreign_keys' => count($foreignKeys),
            'leading_terms' => $leadingTerms,
            'max_leading_terms' => $maxLeadingTerms,
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
                (string) $row['from'] . '->' . (string) $row['parent'] . '.' . (string) $row['to'],
                'child=' . implode(',', (array) $row['child_columns']),
                (string) $row['suffix_child_index'],
                'columns=' . implode(',', (array) $row['suffix_child_index_columns']),
                'leading=' . implode(',', (array) $row['leading_columns']),
                'expected=' . (int) $row['expected_position'],
                'actual=' . (int) $row['actual_position'],
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next227 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
