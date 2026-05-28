<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext224
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next224 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next224 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext217::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::parentKeyCollationRows($currentRecords, 'current');
        $nextRows = self::parentKeyCollationRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next224',
            'base' => $base['source_id'],
            'current_parent_key_collation' => self::rowSummary($currentRows),
            'next_parent_key_collation' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next224 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next224 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::collationCounts($currentRows);
        $nextCounts = self::collationCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next224',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_key_collation_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_collations',
                'foreign_key_parent_key_collation' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_parent_key_collation_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_collations',
                'foreign_key_parent_key_collation' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_key_collation' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_key_collation' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_key_collation_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_parent_key_collation_blockers' => $nextCounts['blocked'] - $currentCounts['blocked'],
                'foreign_key_parent_key_collation_repaired' => $currentCounts['blocked'] > 0 && $nextCounts['blocked'] === 0,
                'foreign_key_parent_key_collation_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-parent-collation-match',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function parentKeyCollationRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase)) as $group) {
            $parent = (string) $group[0]['parent'];
            $parentColumns = array_map(static fn (array $row): string => (string) $row['to'], $group);
            if (in_array('', $parentColumns, true)) {
                continue;
            }

            $candidate = self::matchingUniqueParentIndex($catalog, $parent, $parentColumns);
            foreach ($group as $row) {
                $seq = (int) $row['seq'];
                $indexRow = $candidate['rows'][$seq] ?? null;
                $expectedCollation = $candidate['expected_collations'][$seq] ?? null;
                $actualCollation = $indexRow['coll'] ?? null;
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_parent_key_collation',
                    'table' => (string) $row['table'],
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => $seq,
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'parent_columns' => $parentColumns,
                    'parent_column_collation' => $expectedCollation,
                    'parent_unique_index' => $candidate['index'],
                    'index_column_collation' => $actualCollation,
                    'collation_matches' => $expectedCollation !== null && $actualCollation !== null && strcasecmp((string) $expectedCollation, (string) $actualCollation) === 0,
                    'mismatch_columns' => $candidate['mismatch_columns'],
                    'status' => $candidate['status'],
                    'message' => $candidate['status'] === 'ok'
                        ? "foreign key {$row['table']}->{$parent} parent key collations match UNIQUE index {$candidate['index']}"
                        : ($candidate['status'] === 'parent_key_collation_mismatch'
                            ? "foreign key {$row['table']}->{$parent} parent key UNIQUE index {$candidate['index']} uses mismatched collation"
                            : "foreign key {$row['table']}->{$parent} parent key has no UNIQUE index for collation validation"),
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
     * @param list<string> $parentColumns
     * @return array{status:string,index:string|null,rows:list<array<string,mixed>>,expected_collations:list<string>,mismatch_columns:list<string>}
     */
    private static function matchingUniqueParentIndex(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns): array
    {
        $wanted = array_map('strtolower', $parentColumns);
        $expected = self::parentColumnCollations($catalog, $parent, $parentColumns);
        $firstMismatch = null;
        $firstMismatchRows = [];
        $firstMismatchColumns = [];

        foreach ($catalog->indexList($parent) as $index) {
            if ((int) ($index['unique'] ?? 0) !== 1 || (int) ($index['partial'] ?? 0) !== 0) {
                continue;
            }

            $indexName = (string) $index['name'];
            $xinfo = $catalog->indexXInfo($indexName);
            $keyRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1));
            $prefix = array_slice($keyRows, 0, count($wanted));
            $prefixColumns = array_map(static fn (array $row): string => strtolower((string) ($row['name'] ?? '')), $prefix);
            if ($prefixColumns !== $wanted) {
                continue;
            }

            $mismatch = self::mismatchedColumns($prefix, $parentColumns, $expected);
            if ($mismatch === []) {
                return [
                    'status' => 'ok',
                    'index' => $indexName,
                    'rows' => $prefix,
                    'expected_collations' => $expected,
                    'mismatch_columns' => [],
                ];
            }

            $firstMismatch ??= $indexName;
            $firstMismatchRows = $firstMismatchRows === [] ? $prefix : $firstMismatchRows;
            $firstMismatchColumns = $firstMismatchColumns === [] ? $mismatch : $firstMismatchColumns;
        }

        if ($firstMismatch !== null) {
            return [
                'status' => 'parent_key_collation_mismatch',
                'index' => $firstMismatch,
                'rows' => $firstMismatchRows,
                'expected_collations' => $expected,
                'mismatch_columns' => $firstMismatchColumns,
            ];
        }

        return [
            'status' => 'missing_parent_unique_index',
            'index' => null,
            'rows' => [],
            'expected_collations' => $expected,
            'mismatch_columns' => $parentColumns,
        ];
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private static function parentColumnCollations(SQLitePragmaSchemaCatalog $catalog, string $table, array $columns): array
    {
        $declared = self::declaredColumnCollations($catalog, $table);

        return array_map(
            static fn (string $column): string => $declared[strtolower($column)] ?? 'BINARY',
            $columns,
        );
    }

    /**
     * @return array<string,string>
     */
    private static function declaredColumnCollations(SQLitePragmaSchemaCatalog $catalog, string $table): array
    {
        $record = null;
        foreach ($catalog->records() as $candidate) {
            if ($candidate->type === 'table' && strcasecmp($candidate->name, $table) === 0) {
                $record = $candidate;
                break;
            }
        }
        if ($record === null || $record->sql === null) {
            return [];
        }

        $body = self::parenthesizedBody($record->sql);
        if ($body === null) {
            return [];
        }

        $collations = [];
        foreach (self::splitTopLevel($body, ',') as $definition) {
            $definition = trim($definition);
            if ($definition === '' || preg_match('/^(?:CONSTRAINT|PRIMARY|UNIQUE|CHECK|FOREIGN)\b/i', $definition) === 1) {
                continue;
            }
            $identifier = self::readIdentifier($definition, 0);
            if ($identifier === null) {
                continue;
            }
            $tail = substr($definition, $identifier['end']);
            $collations[strtolower($identifier['identifier'])] = self::declaredCollation($tail);
        }

        return $collations;
    }

    /**
     * @param list<array<string,mixed>> $indexRows
     * @param list<string> $columns
     * @param list<string> $expected
     * @return list<string>
     */
    private static function mismatchedColumns(array $indexRows, array $columns, array $expected): array
    {
        $mismatch = [];
        foreach ($columns as $offset => $column) {
            $actual = strtoupper((string) ($indexRows[$offset]['coll'] ?? ''));
            if ($actual !== strtoupper($expected[$offset] ?? 'BINARY')) {
                $mismatch[] = $column;
            }
        }

        return $mismatch;
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
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,ok:int,blocked:int,parent_key_collation_mismatch:int,missing_parent_unique_index:int,mismatch_columns:int}
     */
    private static function collationCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'ok' => 0,
            'blocked' => 0,
            'parent_key_collation_mismatch' => 0,
            'missing_parent_unique_index' => 0,
            'mismatch_columns' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'ok') {
                $counts['ok']++;
            } else {
                $counts['blocked']++;
                if (isset($counts[$status])) {
                    $counts[$status]++;
                }
            }
            $counts['mismatch_columns'] += count((array) ($row['mismatch_columns'] ?? []));
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
                (string) ($row['parent_unique_index'] ?? 'missing-index'),
                (string) ($row['parent_column_collation'] ?? 'missing-parent-collation') . '!=' . (string) ($row['index_column_collation'] ?? 'missing-index-collation'),
                'mismatch=' . implode(',', (array) ($row['mismatch_columns'] ?? [])),
                (string) ($row['status'] ?? ''),
            ], static fn (?string $part): bool => $part !== null)),
            $rows,
        );
        sort($summary);

        return $summary;
    }

    private static function declaredCollation(string $tail): string
    {
        if (preg_match('/\bCOLLATE\s+(?:"(?<dq>(?:""|[^"])*)"|`(?<bt>[^`]*)`|\[(?<br>[^\]]*)\]|(?<id>[A-Za-z_][A-Za-z0-9_]*))/i', $tail, $matches) !== 1) {
            return 'BINARY';
        }

        foreach (['dq', 'bt', 'br', 'id'] as $key) {
            if (($matches[$key] ?? '') !== '') {
                return strtoupper(str_replace('""', '"', (string) $matches[$key]));
            }
        }

        return 'BINARY';
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $value, string $separator): array
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
                    if ($quote === "'" && ($value[$i + 1] ?? null) === "'") {
                        $i++;
                        continue;
                    }
                    if ($quote === '"' && ($value[$i + 1] ?? null) === '"') {
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
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
            if ($char === ')' && $depth > 0) {
                $depth--;
                continue;
            }
            if ($char === $separator && $depth === 0) {
                $parts[] = substr($value, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $parts[] = substr($value, $start);

        return $parts;
    }

    private static function parenthesizedBody(string $sql): ?string
    {
        $open = strpos($sql, '(');
        if ($open === false) {
            return null;
        }

        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        for ($i = $open; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if (($quote === "'" || $quote === '"') && ($sql[$i + 1] ?? null) === $quote) {
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
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
                $depth--;
                if ($depth === 0) {
                    return substr($sql, $open + 1, $i - $open - 1);
                }
            }
        }

        return null;
    }

    /**
     * @return array{identifier:string,end:int}|null
     */
    private static function readIdentifier(string $value, int $offset): ?array
    {
        $length = strlen($value);
        while ($offset < $length && ctype_space($value[$offset])) {
            $offset++;
        }
        if ($offset >= $length) {
            return null;
        }

        $char = $value[$offset];
        if ($char === '"' || $char === '`' || $char === '[') {
            $close = $char === '[' ? ']' : $char;
            $end = $offset + 1;
            $identifier = '';
            while ($end < $length) {
                if ($value[$end] === $close) {
                    if ($close === '"' && ($value[$end + 1] ?? null) === '"') {
                        $identifier .= '"';
                        $end += 2;
                        continue;
                    }

                    return ['identifier' => $identifier, 'end' => $end + 1];
                }
                $identifier .= $value[$end];
                $end++;
            }

            return null;
        }

        if (preg_match('/[A-Za-z_][A-Za-z0-9_]*/A', substr($value, $offset), $matches) !== 1) {
            return null;
        }

        return ['identifier' => $matches[0], 'end' => $offset + strlen($matches[0])];
    }

    /**
     * @param list<mixed> $records
     */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next224 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
