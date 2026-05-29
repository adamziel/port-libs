<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegrityPartialIndexCurrentSourceNext
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $indexEntries
     * @return list<array<string,mixed>>
     */
    public static function collect(
        array $rows,
        array $indexEntries,
        SQLiteIndexPredicate $partialPredicate,
        array $indexColumns,
        string $table = 'wp_options',
        string $index = 'idx_wp_options_partial',
        string $pragma = 'integrity_check',
    ): array {
        $pragma = self::normalizePragma($pragma);
        $deep = $pragma === 'integrity_check';
        $entryMap = self::entryMap($indexEntries, $indexColumns, $table, $index);
        $expected = [];
        $rowsOut = [];

        foreach ($rows as $row) {
            $rowid = self::rowid($row);
            $matches = self::predicateMatches($partialPredicate, $row);
            $key = self::keyForRow($row, $indexColumns, $rowid);
            $expected[$key] = [
                'rowid' => $rowid,
                'key' => $key,
                'row' => $row,
            ];
            $present = isset($entryMap[$key]);
            $status = 'ok';
            $message = null;

            if ($matches && !$present) {
                $status = 'missing_index_entry';
                $message = "partial index {$index} is missing rowid {$rowid} for table {$table}";
            } elseif (!$matches && $present && $deep) {
                $status = 'stale_index_entry';
                $message = "partial index {$index} contains rowid {$rowid} that does not satisfy the WHERE clause";
            }

            $rowsOut[] = [
                'kind' => 'partial_index_row',
                'source' => 'pragma_' . $pragma,
                'pragma' => $pragma,
                'table' => $table,
                'index' => $index,
                'rowid' => $rowid,
                'key' => $key,
                'predicate_matches' => $matches,
                'index_present' => $present,
                'status' => $status,
                'message' => $message,
            ];
        }

        if ($deep) {
            foreach ($entryMap as $key => $entry) {
                if (isset($expected[$key])) {
                    continue;
                }
                $rowid = self::rowid($entry);
                $rowsOut[] = [
                    'kind' => 'partial_index_entry',
                    'source' => 'pragma_' . $pragma,
                    'pragma' => $pragma,
                    'table' => $table,
                    'index' => $index,
                    'rowid' => $rowid,
                    'key' => $key,
                    'predicate_matches' => false,
                    'index_present' => true,
                    'status' => 'orphan_index_entry',
                    'message' => "partial index {$index} contains rowid {$rowid} with no table row in {$table}",
                ];
            }
        }

        return $rowsOut;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $indexEntries
     * @param list<string> $indexColumns
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array<string,mixed>
     */
    public static function page(
        array $rows,
        array $indexEntries,
        SQLiteIndexPredicate $partialPredicate,
        array $indexColumns,
        int $offset = 0,
        int $limit = 126,
        string $table = 'wp_options',
        string $index = 'idx_wp_options_partial',
        string $pragma = 'integrity_check',
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA partial-index integrity current-source next126 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA partial-index integrity current-source next126 limit must be positive');
        }
        if ($indexColumns === []) {
            throw new InvalidArgumentException('SQLite PRAGMA partial-index integrity current-source next126 requires at least one index column');
        }

        $pragma = self::normalizePragma($pragma);
        $source = self::source($rows, $indexEntries, $partialPredicate, $indexColumns, $table, $index, $pragma);
        if ($cursor !== null) {
            self::validateCursor($cursor, $source['source_id'], $offset);
        }

        $allRows = self::collect($rows, $indexEntries, $partialPredicate, $indexColumns, $table, $index, $pragma);
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($allRows);
        $counts = self::counts($allRows);

        return [
            'status' => $counts['errors'] === 0 ? 'ok' : 'blocked',
            'source_id' => $source['source_id'],
            'current_source' => $source['current_source'],
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => $counts,
            'next' => $complete ? null : [
                'source_id' => $source['source_id'],
                'offset' => $nextOffset,
            ],
            'next_row' => $pageRows[1] ?? null,
            'rows' => $pageRows,
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function predicateMatches(SQLiteIndexPredicate $predicate, array $row): bool
    {
        if ($predicate->operator === SQLiteIndexPredicate::AND) {
            return is_array($predicate->value)
                && $predicate->value !== []
                && array_reduce(
                    $predicate->value,
                    static fn (bool $carry, mixed $child): bool => $carry && $child instanceof SQLiteIndexPredicate && self::predicateMatches($child, $row),
                    true,
                );
        }
        if ($predicate->operator === SQLiteIndexPredicate::OR) {
            return is_array($predicate->value)
                && array_reduce(
                    $predicate->value,
                    static fn (bool $carry, mixed $child): bool => $carry || ($child instanceof SQLiteIndexPredicate && self::predicateMatches($child, $row)),
                    false,
                );
        }

        if (!array_key_exists($predicate->columnName, $row)) {
            return false;
        }

        $value = $row[$predicate->columnName];

        return match ($predicate->operator) {
            SQLiteIndexPredicate::IS_NOT_NULL => $value !== null,
            SQLiteIndexPredicate::EQUALS => self::compare($value, $predicate->value) === 0,
            SQLiteIndexPredicate::NOT_EQUALS => self::compare($value, $predicate->value) !== 0,
            SQLiteIndexPredicate::LESS_THAN => self::compare($value, $predicate->value) < 0,
            SQLiteIndexPredicate::LESS_THAN_OR_EQUAL => self::compare($value, $predicate->value) <= 0,
            SQLiteIndexPredicate::GREATER_THAN => self::compare($value, $predicate->value) > 0,
            SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL => self::compare($value, $predicate->value) >= 0,
            SQLiteIndexPredicate::BETWEEN => is_array($predicate->value)
                && array_key_exists('lower', $predicate->value)
                && array_key_exists('upper', $predicate->value)
                && self::compare($value, $predicate->value['lower']) >= 0
                && self::compare($value, $predicate->value['upper']) <= 0,
            SQLiteIndexPredicate::IN_LIST => is_array($predicate->value) && self::inList($value, $predicate->value),
            default => false,
        };
    }

    /**
     * @param list<mixed> $values
     */
    private static function inList(mixed $value, array $values): bool
    {
        foreach ($values as $candidate) {
            if (self::compare($value, $candidate) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function compare(mixed $left, mixed $right): int
    {
        if ($left === null || $right === null) {
            return $left <=> $right;
        }
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left <=> $right;
        }

        return strcmp((string) $left, (string) $right);
    }

    /**
     * @param list<array<string,mixed>> $entries
     * @param list<string> $indexColumns
     * @return array<string,array<string,mixed>>
     */
    private static function entryMap(array $entries, array $indexColumns, string $table, string $index): array
    {
        $map = [];
        foreach ($entries as $entry) {
            $rowid = self::rowid($entry);
            $key = self::keyForRow($entry, $indexColumns, $rowid);
            if (isset($map[$key])) {
                throw new InvalidArgumentException("SQLite partial index {$index} for {$table} contains duplicate key {$key}");
            }
            $map[$key] = $entry;
        }

        return $map;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $indexColumns
     */
    private static function keyForRow(array $row, array $indexColumns, int $rowid): string
    {
        $parts = [];
        foreach ($indexColumns as $column) {
            $parts[] = self::encodeKeyPart($row[$column] ?? null);
        }
        $parts[] = 'rowid:' . $rowid;

        return implode('|', $parts);
    }

    private static function encodeKeyPart(mixed $value): string
    {
        if ($value === null) {
            return 'null:';
        }
        if (is_bool($value)) {
            return 'bool:' . ($value ? '1' : '0');
        }
        if (is_int($value)) {
            return 'int:' . $value;
        }
        if (is_float($value)) {
            return 'float:' . sprintf('%.17G', $value);
        }

        return 'text:' . (string) $value;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowid(array $row): int
    {
        foreach (['rowid', '_rowid_', 'oid', 'option_id', 'ID', 'id'] as $column) {
            if (array_key_exists($column, $row) && is_int($row[$column])) {
                return $row[$column];
            }
        }

        throw new InvalidArgumentException('SQLite partial-index integrity row requires an integer rowid');
    }

    private static function normalizePragma(string $pragma): string
    {
        $pragma = strtolower(trim($pragma));
        if (preg_match('/^pragma\s+(?:[A-Za-z_][A-Za-z0-9_]*\s*\.\s*)?(integrity_check|quick_check)(?:\s*(?:\(\s*\d+\s*\)|=\s*\d+))?\s*;?$/i', $pragma, $matches) === 1) {
            return strtolower($matches[1]);
        }
        if ($pragma === 'integrity_check' || $pragma === 'quick_check') {
            return $pragma;
        }

        throw new InvalidArgumentException('SQLite partial-index integrity next126 supports only integrity_check and quick_check');
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $entries
     * @param list<string> $indexColumns
     * @return array{source_id:string,current_source:array<string,mixed>}
     */
    private static function source(array $rows, array $entries, SQLiteIndexPredicate $predicate, array $indexColumns, string $table, string $index, string $pragma): array
    {
        $source = [
            'table' => $table,
            'index' => $index,
            'pragma' => $pragma,
            'index_columns' => array_values($indexColumns),
            'rows' => $rows,
            'index_entries' => $entries,
            'partial_predicate' => self::predicateSource($predicate),
        ];
        $json = json_encode($source, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return [
            'source_id' => hash('sha256', $json),
            'current_source' => [
                'table' => $table,
                'index' => $index,
                'pragma' => $pragma,
                'index_columns' => array_values($indexColumns),
                'row_count' => count($rows),
                'index_entry_count' => count($entries),
                'source_hash' => hash('sha256', $json),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function predicateSource(SQLiteIndexPredicate $predicate): array
    {
        return [
            'column' => $predicate->columnName,
            'operator' => $predicate->operator,
            'value' => is_array($predicate->value)
                ? array_map(static fn (mixed $value): mixed => $value instanceof SQLiteIndexPredicate ? self::predicateSource($value) : $value, $predicate->value)
                : $predicate->value,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,index_entries:int,predicate_matches:int,missing:int,stale:int,orphan:int,errors:int}
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'rows' => 0,
            'index_entries' => 0,
            'predicate_matches' => 0,
            'missing' => 0,
            'stale' => 0,
            'orphan' => 0,
            'errors' => 0,
        ];
        foreach ($rows as $row) {
            if (($row['kind'] ?? null) === 'partial_index_row') {
                $counts['rows']++;
            } elseif (($row['kind'] ?? null) === 'partial_index_entry') {
                $counts['index_entries']++;
            }
            if (($row['predicate_matches'] ?? false) === true) {
                $counts['predicate_matches']++;
            }
            $status = (string) ($row['status'] ?? 'ok');
            if ($status !== 'ok') {
                $counts['errors']++;
            }
            if ($status === 'missing_index_entry') {
                $counts['missing']++;
            } elseif ($status === 'stale_index_entry') {
                $counts['stale']++;
            } elseif ($status === 'orphan_index_entry') {
                $counts['orphan']++;
            }
        }

        return $counts;
    }

    /**
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null} $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA partial-index integrity current-source next126 cursor source is stale');
        }
        $expected = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($expected !== null && (int) $expected !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA partial-index integrity current-source next126 cursor offset does not match requested offset');
        }
    }
}
