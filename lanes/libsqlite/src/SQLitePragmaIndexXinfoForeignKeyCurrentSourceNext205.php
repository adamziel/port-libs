<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext205
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next205 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next205 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext203::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::childPrefixQualityRows($currentRecords, 'current');
        $nextRows = self::childPrefixQualityRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next205',
            'base' => $base['source_id'],
            'current_child_prefix_quality' => self::rowSummary($currentRows),
            'next_child_prefix_quality' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next205 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next205 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::qualityCounts($currentRows);
        $nextCounts = self::qualityCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next205',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_child_prefix_quality_source' => 'pragma_foreign_key_list_child_groups_plus_pragma_index_xinfo_collation_desc',
                'foreign_key_child_prefix_quality' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_child_prefix_quality_source' => 'pragma_foreign_key_list_child_groups_plus_pragma_index_xinfo_collation_desc',
                'foreign_key_child_prefix_quality' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_child_prefix_quality' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_child_prefix_quality' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_child_prefix_quality_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_child_prefix_quality_mismatches' => $nextCounts['mismatched'] - $currentCounts['mismatched'],
                'foreign_key_child_prefix_quality_repaired' => $currentCounts['mismatched'] > 0 && $nextCounts['mismatched'] === 0,
                'foreign_key_child_prefix_quality_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-child-index-prefix-quality',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function childPrefixQualityRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $collations = self::tableColumnCollations($records);
        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase)) as $group) {
            $table = (string) $group[0]['table'];
            $childColumns = array_map(static fn (array $row): string => (string) $row['from'], $group);
            $candidate = self::matchingChildPrefix($catalog, $table, $childColumns);

            foreach ($group as $row) {
                $seq = (int) $row['seq'];
                $indexRow = $candidate['rows'][$seq] ?? null;
                $declared = strtoupper($collations[strtolower($table)][strtolower((string) $row['from'])] ?? 'BINARY');
                $indexCollation = strtoupper((string) ($indexRow['coll'] ?? ''));
                $descending = (int) ($indexRow['desc'] ?? 0);
                $mapped = $candidate['name'] !== null;
                $collationMatches = !$mapped || $indexCollation === $declared;
                $ascending = !$mapped || $descending === 0;
                $status = !$mapped ? 'missing_child_prefix' : (($collationMatches && $ascending) ? 'ok' : 'mismatched_child_prefix');

                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_child_prefix_quality',
                    'table' => $table,
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => $seq,
                    'parent' => (string) $row['parent'],
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'child_columns' => $childColumns,
                    'child_index' => $candidate['name'],
                    'child_index_unique' => $candidate['unique'],
                    'child_index_partial' => $candidate['partial'],
                    'child_index_seqno' => $indexRow['seqno'] ?? null,
                    'child_index_name' => $indexRow['name'] ?? null,
                    'child_declared_collation' => $declared,
                    'child_index_collation' => $indexCollation === '' ? null : $indexCollation,
                    'child_index_desc' => $mapped ? $descending : null,
                    'collation_matches' => $collationMatches,
                    'ascending_prefix' => $ascending,
                    'covered_prefix_columns' => $candidate['covered_prefix_columns'],
                    'extra_key_columns' => $candidate['extra_key_columns'],
                    'status' => $status,
                    'message' => $status === 'ok'
                        ? "foreign key {$table} child column {$row['from']} uses matching child index prefix {$candidate['name']}"
                        : ($mapped
                            ? "foreign key {$table} child column {$row['from']} prefix {$candidate['name']} does not match declared collation/order"
                            : "foreign key {$table} child columns have no PRAGMA index_xinfo prefix"),
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
     * @param list<string> $childColumns
     * @return array{name:string|null,unique:int|null,partial:int|null,rows:list<array<string,mixed>>,covered_prefix_columns:int,extra_key_columns:int}
     */
    private static function matchingChildPrefix(SQLitePragmaSchemaCatalog $catalog, string $table, array $childColumns): array
    {
        foreach ($catalog->indexList($table) as $index) {
            $xinfo = $catalog->indexXInfo((string) $index['name']);
            $keyRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1));
            $prefix = array_slice($keyRows, 0, count($childColumns));
            $prefixColumns = array_map(static fn (array $row): string => strtolower((string) ($row['name'] ?? '')), $prefix);
            if ($prefixColumns !== array_map('strtolower', $childColumns)) {
                continue;
            }

            return [
                'name' => (string) $index['name'],
                'unique' => (int) $index['unique'],
                'partial' => (int) $index['partial'],
                'rows' => $prefix,
                'covered_prefix_columns' => count($prefix),
                'extra_key_columns' => max(0, count($keyRows) - count($childColumns)),
            ];
        }

        return [
            'name' => null,
            'unique' => null,
            'partial' => null,
            'rows' => [],
            'covered_prefix_columns' => 0,
            'extra_key_columns' => 0,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,array<string,string>>
     */
    private static function tableColumnCollations(array $records): array
    {
        $collations = [];
        foreach ($records as $record) {
            if ($record->type !== 'table' || $record->sql === null) {
                continue;
            }
            $catalog = new SQLitePragmaSchemaCatalog([$record]);
            foreach ($catalog->tableInfo($record->name, true) as $column) {
                $collations[strtolower($record->name)][strtolower((string) $column['name'])] = self::declaredColumnCollation($record->sql, (string) $column['name']);
            }
        }

        return $collations;
    }

    private static function declaredColumnCollation(string $createTableSql, string $columnName): string
    {
        $open = strpos($createTableSql, '(');
        $close = $open === false ? false : strrpos($createTableSql, ')');
        if ($open === false || $close === false || $close <= $open) {
            return 'BINARY';
        }
        foreach (self::splitTopLevel(substr($createTableSql, $open + 1, $close - $open - 1), ',') as $definition) {
            $definition = trim($definition);
            if ($definition === '') {
                continue;
            }
            if (!preg_match('/^(?:"(?<dq>(?:""|[^"])*)"|`(?<bt>[^`]*)`|\[(?<br>[^\]]*)\]|(?<bare>[A-Za-z_][A-Za-z0-9_]*))(?<tail>.*)$/s', $definition, $matches)) {
                continue;
            }
            $name = $matches['bare'] !== '' ? $matches['bare'] : (($matches['dq'] ?? '') !== '' ? str_replace('""', '"', $matches['dq']) : (($matches['bt'] ?? '') !== '' ? $matches['bt'] : ($matches['br'] ?? '')));
            if (strcasecmp($name, $columnName) !== 0) {
                continue;
            }
            if (!preg_match('/\bCOLLATE\s+(?:"(?<cdq>(?:""|[^"])*)"|`(?<cbt>[^`]*)`|\[(?<cbr>[^\]]*)\]|(?<cbare>[A-Za-z_][A-Za-z0-9_]*))/i', (string) ($matches['tail'] ?? ''), $collation)) {
                return 'BINARY';
            }
            foreach (['cbare', 'cdq', 'cbt', 'cbr'] as $key) {
                if (($collation[$key] ?? '') !== '') {
                    return strtoupper(str_replace('""', '"', $collation[$key]));
                }
            }
        }

        return 'BINARY';
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $value, string $delimiter): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $quote = null;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if ($quote === '"' && ($value[$i + 1] ?? null) === '"') {
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
                continue;
            }
            if ($char === '[') {
                $quote = ']';
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth = max(0, $depth - 1);
                continue;
            }
            if ($depth === 0 && $char === $delimiter) {
                $parts[] = substr($value, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $parts[] = substr($value, $start);

        return $parts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,ok:int,mismatched:int,missing_child_prefix:int,collation_mismatch:int,descending_prefix:int,partial_index:int,extra_key_columns:int}
     */
    private static function qualityCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'ok' => 0,
            'mismatched' => 0,
            'missing_child_prefix' => 0,
            'collation_mismatch' => 0,
            'descending_prefix' => 0,
            'partial_index' => 0,
            'extra_key_columns' => 0,
        ];
        foreach ($rows as $row) {
            if (($row['status'] ?? null) === 'ok') {
                $counts['ok']++;
            } else {
                $counts['mismatched']++;
            }
            if (($row['status'] ?? null) === 'missing_child_prefix') {
                $counts['missing_child_prefix']++;
            }
            if (($row['collation_matches'] ?? true) === false) {
                $counts['collation_mismatch']++;
            }
            if (($row['ascending_prefix'] ?? true) === false) {
                $counts['descending_prefix']++;
            }
            if (($row['child_index_partial'] ?? null) === 1) {
                $counts['partial_index']++;
            }
            $counts['extra_key_columns'] += (int) ($row['extra_key_columns'] ?? 0);
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
                (string) ($row['child_index'] ?? 'missing'),
                (string) ($row['child_declared_collation'] ?? ''),
                (string) ($row['child_index_collation'] ?? ''),
                (string) ($row['child_index_desc'] ?? 'null'),
                (string) ($row['status'] ?? ''),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next205 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
