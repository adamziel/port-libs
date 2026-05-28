<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext192
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
        int $limit = 192,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next192 offset must be non-negative');
        }
        if ($limit <= 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next192 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext189::currentNextPageFromCatalog(
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

        $currentRows = self::rejectedParentCollationRows($currentRecords, 'current');
        $nextRows = self::rejectedParentCollationRows($nextRecords, 'next');
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next192',
            'base' => $base['source_id'],
            'current_rejected_parent_collations' => self::rowSummary($currentRows),
            'next_rejected_parent_collations' => self::rowSummary($nextRows),
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $collationRows = array_values(array_merge($currentRows, $nextRows));
        $allRows = array_values(array_merge(
            array_map(static fn (array $row): array => self::decorateParentKeyRow($row, $collationRows), $base['rows']),
            $collationRows,
        ));
        $total = count($allRows);
        $rows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($rows);
        $complete = $nextOffset >= $total;
        $currentCounts = self::collationCounts($currentRows);
        $nextCounts = self::collationCounts($nextRows);
        $blocking = self::blocking($base['next_state']['blocking'] ?? [], $nextCounts);

        return [
            ...$base,
            'source_id' => $sourceId,
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($rows),
            'total' => $total,
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_rejected_parent_collation_source' => 'create_table_column_collate_and_pragma_index_xinfo_parent_unique',
                'foreign_key_rejected_parent_collations' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...$base['next_source'],
                'foreign_key_rejected_parent_collation_source' => 'create_table_column_collate_and_pragma_index_xinfo_parent_unique',
                'foreign_key_rejected_parent_collations' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_rejected_parent_collations' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_rejected_parent_collations' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_rejected_parent_collation_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_rejected_parent_collation_mismatches' => $nextCounts['mismatch'] - $currentCounts['mismatch'],
                'foreign_key_rejected_parent_collation_repaired' => $currentCounts['mismatch'] > 0 && $nextCounts['mismatch'] === 0,
                'foreign_key_rejected_parent_collation_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'next_state' => [
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
    public static function rejectedParentCollationRows(array $records, string $side = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $collations = self::tableColumnCollations($records);
        $rows = [];
        foreach (self::groupForeignKeys(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext167::foreignKeysFromCatalog($records)) as $foreignKey) {
            $parent = (string) $foreignKey['parent'];
            $parentColumns = array_map(static fn (array $column): string => (string) $column['parent'], $foreignKey['columns']);
            foreach ($catalog->execute('PRAGMA index_list(' . self::pragmaArgumentLiteral($parent) . ')')['rows'] as $index) {
                if ((int) ($index['unique'] ?? 0) !== 1 || (int) ($index['partial'] ?? 0) === 1) {
                    continue;
                }

                $indexName = (string) $index['name'];
                $xinfo = $catalog->execute('PRAGMA index_xinfo(' . self::pragmaArgumentLiteral($indexName) . ')')['rows'];
                $keyRows = array_values(array_filter($xinfo, static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1));
                $keyColumns = array_map(static fn (array $row): ?string => isset($row['name']) ? (string) $row['name'] : null, $keyRows);
                if (!self::sameColumns($keyColumns, $parentColumns)) {
                    continue;
                }

                $mismatches = [];
                foreach ($keyRows as $seq => $keyRow) {
                    $column = $parentColumns[$seq];
                    $expected = strtoupper($collations[strtolower($parent)][strtolower($column)] ?? 'BINARY');
                    $actual = strtoupper((string) ($keyRow['coll'] ?? 'BINARY'));
                    if (strcasecmp($expected, $actual) !== 0) {
                        $mismatches[] = [
                            'seq' => $seq,
                            'column' => $column,
                            'expected' => $expected,
                            'actual' => $actual,
                        ];
                    }
                }
                if ($mismatches === []) {
                    continue;
                }

                foreach ($keyRows as $seq => $keyRow) {
                    $column = $parentColumns[$seq];
                    $expected = strtoupper($collations[strtolower($parent)][strtolower($column)] ?? 'BINARY');
                    $actual = strtoupper((string) ($keyRow['coll'] ?? 'BINARY'));
                    $rows[] = [
                        'side' => $side,
                        'kind' => 'foreign_key_rejected_parent_collation',
                        'table' => (string) $foreignKey['table'],
                        'fkid' => (int) $foreignKey['id'],
                        'seq' => $seq,
                        'parent' => $parent,
                        'from' => (string) $foreignKey['columns'][$seq]['child'],
                        'to' => $column,
                        'index' => $indexName,
                        'index_seqno' => $keyRow['seqno'] ?? null,
                        'index_name' => $keyRow['name'] ?? null,
                        'index_coll' => $actual,
                        'parent_column_collation' => $expected,
                        'collation_matches' => strcasecmp($expected, $actual) === 0,
                        'mismatched_columns' => $mismatches,
                        'status' => 'rejected',
                        'reason' => 'parent_collation_mismatch',
                        'message' => "foreign key {$foreignKey['table']}->{$parent} cannot use UNIQUE index {$indexName} because parent column collations do not match",
                    ];
                }
            }
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['side'], $left['table'], $left['fkid'], $left['index'], $left['seq']]
                <=> [$right['side'], $right['table'], $right['fkid'], $right['index'], $right['seq']],
        );

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $foreignKeys
     * @return list<array<string,mixed>>
     */
    private static function groupForeignKeys(array $foreignKeys): array
    {
        $grouped = [];
        foreach ($foreignKeys as $foreignKey) {
            $key = strtolower((string) $foreignKey['table']) . '#' . (int) $foreignKey['id'];
            $grouped[$key] ??= [
                ...$foreignKey,
                'columns' => [],
            ];
            foreach ((array) ($foreignKey['columns'] ?? []) as $column) {
                if (!is_array($column)) {
                    continue;
                }
                $grouped[$key]['columns'][] = [
                    'child' => (string) ($column['child'] ?? ''),
                    'parent' => (string) ($column['parent'] ?? ''),
                ];
            }
        }

        foreach ($grouped as $foreignKey) {
            if ($foreignKey['columns'] === []) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next192 requires foreign key columns');
            }
        }

        return array_values($grouped);
    }

    /**
     * @param list<string|null> $left
     * @param list<string> $right
     */
    private static function sameColumns(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            return false;
        }
        foreach ($left as $index => $column) {
            if ($column === null || strcasecmp($column, $right[$index]) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,array<string,string>>
     */
    private static function tableColumnCollations(array $records): array
    {
        $tables = [];
        foreach ($records as $record) {
            if ($record->type !== 'table' || $record->sql === null) {
                continue;
            }
            $body = self::parenthesizedBody($record->sql);
            if ($body === null) {
                continue;
            }
            foreach (self::splitTopLevel($body, ',') as $definition) {
                $definition = trim($definition);
                if ($definition === '' || preg_match('/^(?:CONSTRAINT\b|PRIMARY\s+KEY\b|FOREIGN\s+KEY\b|UNIQUE\b|CHECK\b)/i', $definition) === 1) {
                    continue;
                }
                $column = self::firstIdentifier($definition);
                if ($column === null) {
                    continue;
                }
                $tables[strtolower($record->name)][strtolower($column)] = self::collationFromColumnDefinition($definition);
            }
        }

        return $tables;
    }

    private static function collationFromColumnDefinition(string $definition): string
    {
        if (preg_match('/\bCOLLATE\s+(?<coll>"(?:""|[^"])*"|`[^`]*`|\[[^\]]*\]|[A-Za-z_][A-Za-z0-9_]*)/i', $definition, $matches) !== 1) {
            return 'BINARY';
        }

        return strtoupper(self::normalizeIdentifier($matches['coll']));
    }

    private static function firstIdentifier(string $definition): ?string
    {
        if (preg_match('/^\s*(?<identifier>"(?:""|[^"])*"|`[^`]*`|\[[^\]]*\]|[A-Za-z_][A-Za-z0-9_]*)\b/is', $definition, $matches) !== 1) {
            return null;
        }

        return self::normalizeIdentifier($matches['identifier']);
    }

    private static function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return '';
        }
        $first = $identifier[0];
        $last = $identifier[strlen($identifier) - 1];
        if (($first === '"' && $last === '"') || ($first === '`' && $last === '`')) {
            return str_replace($first . $first, $first, substr($identifier, 1, -1));
        }
        if ($first === '[' && $last === ']') {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }

    private static function parenthesizedBody(string $sql): ?string
    {
        $start = strpos($sql, '(');
        $end = strrpos($sql, ')');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return substr($sql, $start + 1, $end - $start - 1);
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $sql, string $delimiter): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === $quote) {
                    if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                        $buffer .= $sql[++$i];
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === '\'' || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === '[') {
                $quote = ']';
                $buffer .= $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')' && $depth > 0) {
                $depth--;
            }
            if ($char === $delimiter && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        $parts[] = $buffer;

        return $parts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,mismatch:int,matched_columns:int,nocase_expected:int,binary_actual:int,rtrim_expected:int}
     */
    private static function collationCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'mismatch' => 0,
            'matched_columns' => 0,
            'nocase_expected' => 0,
            'binary_actual' => 0,
            'rtrim_expected' => 0,
        ];
        foreach ($rows as $row) {
            if (($row['collation_matches'] ?? false) === true) {
                $counts['matched_columns']++;
            } else {
                $counts['mismatch']++;
            }
            if (($row['parent_column_collation'] ?? null) === 'NOCASE') {
                $counts['nocase_expected']++;
            }
            if (($row['parent_column_collation'] ?? null) === 'RTRIM') {
                $counts['rtrim_expected']++;
            }
            if (($row['index_coll'] ?? null) === 'BINARY') {
                $counts['binary_actual']++;
            }
        }

        return $counts;
    }

    /**
     * @param list<string> $baseBlocking
     * @param array{rows:int,mismatch:int,matched_columns:int,nocase_expected:int,binary_actual:int,rtrim_expected:int} $nextCounts
     * @return list<string>
     */
    private static function blocking(array $baseBlocking, array $nextCounts): array
    {
        $blocking = $baseBlocking;
        if ($nextCounts['mismatch'] > 0) {
            $blocking[] = 'foreign_key_parent_collation_mismatch';
        }

        return array_values(array_unique($blocking));
    }

    /**
     * @param list<array<string,mixed>> $collationRows
     */
    private static function decorateParentKeyRow(array $row, array $collationRows): array
    {
        if (($row['kind'] ?? null) !== 'foreign_key_parent_key') {
            return $row;
        }

        foreach ($collationRows as $collation) {
            if (
                ($row['side'] ?? null) === ($collation['side'] ?? null)
                && ($row['table'] ?? null) === ($collation['table'] ?? null)
                && (int) ($row['fkid'] ?? -1) === (int) ($collation['fkid'] ?? -2)
            ) {
                return [
                    ...$row,
                    'rejected_parent_unique_index' => $collation['index'],
                    'rejected_parent_unique_reason' => 'parent_collation_mismatch',
                ];
            }
        }

        return $row;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function rowSummary(array $rows, bool $includeSide = true): array
    {
        $summary = array_map(
            static fn (array $row): string => ($includeSide ? $row['side'] . ':' : '')
                . $row['table'] . '#' . $row['fkid'] . '.' . $row['seq'] . '->'
                . $row['parent'] . '.' . $row['to'] . ':' . $row['index'] . ':'
                . $row['parent_column_collation'] . '!=' . $row['index_coll'],
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
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next192 records must be SQLiteSchemaRecord instances');
            }
        }
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next192 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next192 cursor offset does not match the requested page offset');
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
