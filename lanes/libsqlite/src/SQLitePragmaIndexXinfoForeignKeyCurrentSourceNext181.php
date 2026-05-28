<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext181
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
        int $limit = 181,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next181 offset must be non-negative');
        }
        if ($limit <= 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next181 limit must be positive');
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

        $currentRows = self::parentKeyCollationRows($currentRecords, 'current');
        $nextRows = self::parentKeyCollationRows($nextRecords, 'next');
        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next181',
            'base' => $base['source_id'],
            'current_collation_rows' => self::rowSummary($currentRows),
            'next_collation_rows' => self::rowSummary($nextRows),
        ]);

        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $total = count($allRows);
        $rows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($rows);
        $complete = $nextOffset >= $total;

        return [
            ...$base,
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($rows),
            'total' => $total,
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_collation_source' => 'pragma_index_xinfo_parent_collation_columns',
                'foreign_key_parent_collation_rows' => count($currentRows),
            ],
            'next_source' => [
                ...$base['next_source'],
                'foreign_key_parent_collation_source' => 'pragma_index_xinfo_parent_collation_columns',
                'foreign_key_parent_collation_rows' => count($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_collation_rows' => count($currentRows),
                'foreign_key_parent_collations' => self::collationCounts($currentRows),
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_collation_rows' => count($nextRows),
                'foreign_key_parent_collations' => self::collationCounts($nextRows),
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_collation_rows' => count($nextRows) - count($currentRows),
                'foreign_key_parent_collation_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
                'foreign_key_parent_collation_mismatch_delta' => self::collationCounts($nextRows)['mismatched'] - self::collationCounts($currentRows)['mismatched'],
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
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next181 records must be SQLiteSchemaRecord instances');
            }
        }

        $collations = self::parentColumnCollations($records);
        $rows = [];
        foreach (SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext178::parentKeyRows($records, $side) as $row) {
            $parent = (string) $row['parent'];
            $column = (string) $row['to'];
            $declared = strtoupper($collations[strtolower($parent)][strtolower($column)] ?? 'BINARY');
            $indexCollation = strtoupper((string) ($row['index_coll'] ?? ''));
            if ($indexCollation === '') {
                $indexCollation = $declared;
            }
            $mapped = ($row['status'] ?? null) === 'ok';
            $matches = $mapped && $declared === $indexCollation;

            $rows[] = [
                'side' => $side,
                'kind' => 'foreign_key_parent_collation',
                'table' => (string) $row['table'],
                'fkid' => (int) $row['fkid'],
                'seq' => (int) $row['seq'],
                'parent' => $parent,
                'from' => (string) $row['from'],
                'to' => $column,
                'index' => $row['index'],
                'parent_collation' => $declared,
                'index_collation' => $indexCollation,
                'status' => $mapped ? ($matches ? 'ok' : 'collation_mismatch') : 'missing_parent_key',
                'message' => $mapped
                    ? ($matches
                        ? "foreign key {$row['table']}->{$parent} column {$column} uses parent collation {$declared}"
                        : "foreign key {$row['table']}->{$parent} column {$column} uses parent collation {$declared} but {$row['index']} key collation {$indexCollation}")
                    : "foreign key {$row['table']}->{$parent} column {$column} has no parent key for collation admission",
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
    private static function parentColumnCollations(array $records): array
    {
        $collations = [];
        foreach ($records as $record) {
            if ($record->type !== 'table' || $record->sql === null) {
                continue;
            }
            $columns = [];
            foreach (self::columnDefinitions($record->sql) as $definition) {
                $identifier = self::readIdentifier($definition);
                if ($identifier === null) {
                    continue;
                }
                $tail = ltrim(substr($definition, $identifier['end']));
                $columns[strtolower($identifier['name'])] = self::declaredCollation($tail);
            }
            $collations[strtolower($record->name)] = $columns;
        }

        return $collations;
    }

    /**
     * @return list<string>
     */
    private static function columnDefinitions(string $sql): array
    {
        $open = strpos($sql, '(');
        if ($open === false) {
            return [];
        }
        $close = self::matchingParen($sql, $open);
        if ($close === null) {
            return [];
        }

        $definitions = [];
        foreach (self::splitTopLevel(substr($sql, $open + 1, $close - $open - 1), ',') as $definition) {
            $definition = trim($definition);
            if ($definition === '') {
                continue;
            }
            $constraint = strtoupper(ltrim(preg_replace('/^CONSTRAINT\s+(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s+/i', '', $definition) ?? $definition));
            if (preg_match('/^(PRIMARY|UNIQUE|CHECK|FOREIGN)\b/', $constraint) === 1) {
                continue;
            }
            $definitions[] = $definition;
        }

        return $definitions;
    }

    private static function declaredCollation(string $tail): string
    {
        if (!preg_match('/\bCOLLATE\s+(?:"(?<dq>(?:""|[^"])*)"|`(?<bt>[^`]*)`|\[(?<br>[^\]]*)\]|(?<bare>[A-Za-z_][A-Za-z0-9_]*))/i', $tail, $matches)) {
            return 'BINARY';
        }

        $value = 'BINARY';
        foreach (['dq', 'bt', 'br', 'bare'] as $key) {
            if (($matches[$key] ?? '') !== '') {
                $value = $matches[$key];
                break;
            }
        }

        return strtoupper(str_replace('""', '"', $value));
    }

    /**
     * @return array{name:string,end:int}|null
     */
    private static function readIdentifier(string $value): ?array
    {
        $value = ltrim($value);
        if ($value === '') {
            return null;
        }
        if ($value[0] === '"') {
            if (!preg_match('/^"(?<name>(?:""|[^"])*)"/', $value, $matches)) {
                return null;
            }

            return ['name' => str_replace('""', '"', $matches['name']), 'end' => strlen($matches[0])];
        }
        if ($value[0] === '`') {
            if (!preg_match('/^`(?<name>[^`]*)`/', $value, $matches)) {
                return null;
            }

            return ['name' => $matches['name'], 'end' => strlen($matches[0])];
        }
        if ($value[0] === '[') {
            if (!preg_match('/^\[(?<name>[^\]]*)\]/', $value, $matches)) {
                return null;
            }

            return ['name' => $matches['name'], 'end' => strlen($matches[0])];
        }
        if (!preg_match('/^(?<name>[A-Za-z_][A-Za-z0-9_]*)/', $value, $matches)) {
            return null;
        }

        return ['name' => $matches['name'], 'end' => strlen($matches[0])];
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

    private static function matchingParen(string $value, int $open): ?int
    {
        $depth = 0;
        $quote = null;
        $length = strlen($value);
        for ($i = $open; $i < $length; $i++) {
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
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,matched:int,mismatched:int,missing_parent_key:int,binary:int,nocase:int,rtrim:int}
     */
    private static function collationCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'matched' => 0,
            'mismatched' => 0,
            'missing_parent_key' => 0,
            'binary' => 0,
            'nocase' => 0,
            'rtrim' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'ok') {
                $counts['matched']++;
            } elseif ($status === 'collation_mismatch') {
                $counts['mismatched']++;
            } else {
                $counts['missing_parent_key']++;
            }
            $bucket = strtolower((string) ($row['parent_collation'] ?? ''));
            if (isset($counts[$bucket])) {
                $counts[$bucket]++;
            }
        }

        return $counts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function rowSummary(array $rows, bool $includeSide = true): array
    {
        $summary = array_map(
            static fn (array $row): string => ($includeSide ? $row['side'] . ':' : '') . $row['table'] . '#' . $row['fkid'] . '.' . $row['seq'] . ':' . $row['from'] . '->' . $row['parent'] . '.' . $row['to'] . ':' . $row['parent_collation'] . '/' . $row['index_collation'] . ':' . $row['status'],
            $rows,
        );
        sort($summary);

        return $summary;
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next181 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next181 cursor offset does not match the requested page offset');
        }
    }

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
