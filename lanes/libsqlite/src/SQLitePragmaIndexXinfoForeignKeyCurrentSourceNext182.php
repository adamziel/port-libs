<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext182
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
        int $limit = 182,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next182 offset must be non-negative');
        }
        if ($limit <= 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next182 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext178::currentNextPageFromCatalog(
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

        $currentCollations = self::parentKeyCollationRows($currentRecords, 'current');
        $nextCollations = self::parentKeyCollationRows($nextRecords, 'next');
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next182',
            'base' => $base['source_id'],
            'current_collations' => self::collationSummary($currentCollations),
            'next_collations' => self::collationSummary($nextCollations),
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $collationRows = array_values(array_merge($currentCollations, $nextCollations));
        $allRows = array_values(array_merge(
            array_map(static fn (array $row): array => self::decorateParentKeyRow($row, $collationRows), $base['rows']),
            $collationRows,
        ));
        $total = count($allRows);
        $rows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($rows);
        $complete = $nextOffset >= $total;
        $currentCounts = self::collationCounts($currentCollations);
        $nextCounts = self::collationCounts($nextCollations);
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
                'foreign_key_parent_collation_source' => 'create_table_column_collate_and_pragma_index_xinfo',
                'foreign_key_parent_collations' => self::collationSummary($currentCollations),
            ],
            'next_source' => [
                ...$base['next_source'],
                'foreign_key_parent_collation_source' => 'create_table_column_collate_and_pragma_index_xinfo',
                'foreign_key_parent_collations' => self::collationSummary($nextCollations),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_collation_rows' => count($currentCollations),
                'foreign_key_parent_collations' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_collation_rows' => count($nextCollations),
                'foreign_key_parent_collations' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_collation_rows' => count($nextCollations) - count($currentCollations),
                'foreign_key_parent_collation_mismatches' => $nextCounts['mismatch'] - $currentCounts['mismatch'],
                'foreign_key_parent_collation_repaired' => $currentCounts['mismatch'] > 0 && $nextCounts['mismatch'] === 0,
                'foreign_key_parent_collation_changed' => self::collationSummary($currentCollations, false) !== self::collationSummary($nextCollations, false),
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
    public static function parentKeyCollationRows(array $records, string $side = 'current'): array
    {
        self::validateRecords($records);

        $tableCollations = self::tableColumnCollations($records);
        $rows = [];
        foreach (SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext178::parentKeyRows($records, $side) as $row) {
            $expected = strtoupper($tableCollations[strtolower((string) $row['parent'])][strtolower((string) $row['to'])] ?? 'BINARY');
            $actual = strtoupper((string) ($row['index_coll'] ?? ''));
            $matches = ($row['status'] ?? null) === 'ok' && strcasecmp($expected, $actual) === 0;
            $status = ($row['status'] ?? null) !== 'ok' ? 'missing_parent_key' : ($matches ? 'ok' : 'collation_mismatch');

            $rows[] = [
                'side' => $side,
                'kind' => 'foreign_key_parent_collation',
                'table' => (string) $row['table'],
                'fkid' => (int) $row['fkid'],
                'seq' => (int) $row['seq'],
                'parent' => (string) $row['parent'],
                'from' => (string) $row['from'],
                'to' => (string) $row['to'],
                'index' => $row['index'],
                'index_coll' => $row['index_coll'],
                'parent_column_collation' => $expected,
                'collation_matches' => $matches,
                'status' => $status,
                'message' => $status === 'collation_mismatch'
                    ? "foreign key {$row['table']}->{$row['parent']} column {$row['to']} uses {$actual} index collation but parent column declares {$expected}"
                    : (string) $row['message'],
            ];
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['side'], $left['table'], $left['fkid'], $left['seq']]
                <=> [$right['side'], $right['table'], $right['fkid'], $right['seq']],
        );

        return $rows;
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

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,int>
     */
    private static function collationCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'matched' => 0,
            'mismatch' => 0,
            'missing_parent_key' => 0,
            'binary' => 0,
            'nocase' => 0,
            'rtrim' => 0,
            'custom' => 0,
        ];
        foreach ($rows as $row) {
            if (($row['status'] ?? null) === 'missing_parent_key') {
                $counts['missing_parent_key']++;
            } elseif (($row['collation_matches'] ?? false) === true) {
                $counts['matched']++;
            } else {
                $counts['mismatch']++;
            }

            $collation = strtoupper((string) ($row['parent_column_collation'] ?? 'BINARY'));
            if ($collation === 'BINARY') {
                $counts['binary']++;
            } elseif ($collation === 'NOCASE') {
                $counts['nocase']++;
            } elseif ($collation === 'RTRIM') {
                $counts['rtrim']++;
            } else {
                $counts['custom']++;
            }
        }

        return $counts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function collationSummary(array $rows, bool $includeSide = true): array
    {
        $summary = array_map(
            static fn (array $row): string => ($includeSide ? $row['side'] . ':' : '')
                . $row['table'] . '#' . $row['fkid'] . '.' . $row['seq']
                . ':' . $row['parent'] . '.' . $row['to']
                . ':parent=' . $row['parent_column_collation']
                . ':index=' . ($row['index_coll'] ?? '')
                . ':status=' . $row['status'],
            $rows,
        );
        sort($summary);

        return $summary;
    }

    /**
     * @param list<string> $baseBlocking
     * @param array<string,int> $nextCounts
     * @return list<string>
     */
    private static function blocking(array $baseBlocking, array $nextCounts): array
    {
        $blocking = $baseBlocking;
        if ($nextCounts['mismatch'] > 0) {
            $blocking[] = 'foreign_key_parent_collation';
        }
        if ($nextCounts['missing_parent_key'] > 0 && !in_array('foreign_key_parent_unique_index', $blocking, true)) {
            $blocking[] = 'foreign_key_parent_unique_index';
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
                && (int) ($row['seq'] ?? -1) === (int) ($collation['seq'] ?? -2)
            ) {
                return [
                    ...$row,
                    'parent_column_collation' => $collation['parent_column_collation'],
                    'parent_key_collation_matches' => $collation['collation_matches'],
                    'parent_key_collation_status' => $collation['status'],
                ];
            }
        }

        return $row;
    }

    /** @param list<mixed> $records */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next182 records must be SQLiteSchemaRecord instances');
            }
        }
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next182 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next182 cursor offset does not match the requested page offset');
        }
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
                    if (($quote === "'" || $quote === '"') && ($sql[$i + 1] ?? '') === $quote) {
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
                    if (($quote === "'" || $quote === '"') && ($value[$i + 1] ?? '') === $quote) {
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

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
