<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAffinityRangeCurrentSourceCursor
{
    /** @var list<array{key:mixed,rowid:int,payload:array<string,mixed>}> */
    private array $entries = [];
    private int $position = 0;
    private readonly string $affinity;
    private readonly string $collation;

    /**
     * @param iterable<array{key?:mixed,rowid?:mixed,payload?:array<string,mixed>}> $entries
     */
    public function __construct(
        iterable $entries,
        private readonly mixed $lowerInclusive,
        private readonly mixed $upperExclusive,
        string $affinity = 'NONE',
        string $collation = 'BINARY',
    ) {
        $this->affinity = self::normalizeAffinity($affinity);
        $this->collation = self::normalizeCollation($collation);
        $this->assertComparable($lowerInclusive);
        $this->assertComparable($upperExclusive);

        foreach ($entries as $entry) {
            if (!is_array($entry) || !array_key_exists('key', $entry) || !array_key_exists('rowid', $entry)) {
                throw new \InvalidArgumentException('SQLite affinity range current-source entries require key and rowid');
            }
            if (!is_int($entry['rowid'])) {
                throw new \InvalidArgumentException('SQLite affinity range current-source rowid must be an integer');
            }
            $this->assertComparable($entry['key']);
            $payload = $entry['payload'] ?? [];
            if (!is_array($payload)) {
                throw new \InvalidArgumentException('SQLite affinity range current-source payload must be an array');
            }
            $this->entries[] = [
                'key' => $entry['key'],
                'rowid' => $entry['rowid'],
                'payload' => $payload,
            ];
        }

        usort($this->entries, fn (array $left, array $right): int => $this->compareEntries($left, $right));
        $this->rewind();
    }

    public function rewind(): void
    {
        $this->position = count($this->entries);
        foreach ($this->entries as $index => $entry) {
            $comparison = $this->compareKeyToBound($entry['key'], $this->lowerInclusive);
            if ($comparison !== null && $comparison >= 0) {
                $this->position = $index;
                return;
            }
        }
    }

    public function next(): void
    {
        if ($this->position < count($this->entries)) {
            $this->position++;
        }
    }

    /**
     * @return array{
     *   affinity:string,
     *   collation:string,
     *   position:int,
     *   eof:bool,
     *   currentRowid:?int,
     *   currentKey:mixed,
     *   currentStorage:?string,
     *   currentInRange:?bool,
     *   currentLowerComparison:?int,
     *   currentUpperComparison:?int,
     *   nextRowid:?int,
     *   nextKey:mixed,
     *   nextStorage:?string,
     *   nextInRange:?bool,
     *   nextLowerComparison:?int,
     *   nextUpperComparison:?int,
     *   lowerStorage:string,
     *   upperStorage:string
     * }
     */
    public function currentNextPlan(): array
    {
        $current = $this->entries[$this->position] ?? null;
        $next = $this->entries[$this->position + 1] ?? null;

        return [
            'affinity' => $this->affinity,
            'collation' => $this->collation,
            'position' => $this->position,
            'eof' => $current === null,
            'currentRowid' => $current['rowid'] ?? null,
            'currentKey' => $current['key'] ?? null,
            'currentStorage' => $current === null ? null : SQLiteAffinityComparison::storageClass($current['key']),
            'currentInRange' => $current === null ? null : $this->inRange($current['key']),
            'currentLowerComparison' => $current === null ? null : self::sign($this->compareKeyToBound($current['key'], $this->lowerInclusive)),
            'currentUpperComparison' => $current === null ? null : self::sign($this->compareKeyToBound($current['key'], $this->upperExclusive)),
            'nextRowid' => $next['rowid'] ?? null,
            'nextKey' => $next['key'] ?? null,
            'nextStorage' => $next === null ? null : SQLiteAffinityComparison::storageClass($next['key']),
            'nextInRange' => $next === null ? null : $this->inRange($next['key']),
            'nextLowerComparison' => $next === null ? null : self::sign($this->compareKeyToBound($next['key'], $this->lowerInclusive)),
            'nextUpperComparison' => $next === null ? null : self::sign($this->compareKeyToBound($next['key'], $this->upperExclusive)),
            'lowerStorage' => SQLiteAffinityComparison::coercedPair($this->lowerInclusive, $this->lowerInclusive, $this->affinity, $this->affinity)['leftStorageClass'],
            'upperStorage' => SQLiteAffinityComparison::coercedPair($this->upperExclusive, $this->upperExclusive, $this->affinity, $this->affinity)['leftStorageClass'],
        ];
    }

    /**
     * @return list<array{key:mixed,rowid:int,payload:array<string,mixed>,position:int,storage:string}>
     */
    public function matchedRows(): array
    {
        $rows = [];
        foreach ($this->entries as $position => $entry) {
            if (!$this->inRange($entry['key'])) {
                continue;
            }
            $rows[] = [
                'key' => $entry['key'],
                'rowid' => $entry['rowid'],
                'payload' => $entry['payload'],
                'position' => $position,
                'storage' => SQLiteAffinityComparison::storageClass($entry['key']),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $filters
     * @return list<array{key:mixed,rowid:int,payload:array<string,mixed>,position:int,storage:string}>
     */
    public static function optionRowRange(
        array $rows,
        string $column,
        mixed $lowerInclusive,
        mixed $upperExclusive,
        string $affinity = 'NONE',
        string $collation = 'BINARY',
        array $filters = [],
    ): array {
        $entries = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("Application option range row is missing {$column}");
            }
            foreach ($filters as $filterColumn => $expected) {
                if (!array_key_exists($filterColumn, $row) || $row[$filterColumn] !== $expected) {
                    continue 2;
                }
            }
            $entries[] = [
                'key' => $row[$column],
                'rowid' => is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1,
                'payload' => $row,
            ];
        }

        return (new self($entries, $lowerInclusive, $upperExclusive, $affinity, $collation))->matchedRows();
    }

    private function compareEntries(array $left, array $right): int
    {
        $comparison = SQLiteAffinityComparison::compare($this->coerce($left['key']), $this->coerce($right['key']), 'NONE', 'NONE', $this->collation);
        if ($comparison !== null && $comparison !== 0) {
            return $comparison;
        }
        if ($comparison === null) {
            $leftRank = $this->nullAwareRank($left['key']);
            $rightRank = $this->nullAwareRank($right['key']);
            if ($leftRank !== $rightRank) {
                return $leftRank <=> $rightRank;
            }
        }

        return $left['rowid'] <=> $right['rowid'];
    }

    private function inRange(mixed $key): bool
    {
        $lower = $this->compareKeyToBound($key, $this->lowerInclusive);
        $upper = $this->compareKeyToBound($key, $this->upperExclusive);

        return $lower !== null && $upper !== null && $lower >= 0 && $upper < 0;
    }

    private function compareKeyToBound(mixed $key, mixed $bound): ?int
    {
        return SQLiteAffinityComparison::compare($this->coerce($key), $this->coerce($bound), 'NONE', 'NONE', $this->collation);
    }

    private function coerce(mixed $value): mixed
    {
        return match ($this->affinity) {
            'INTEGER', 'REAL', 'NUMERIC' => $this->coerceNumeric($value),
            'TEXT' => $this->coerceText($value),
            default => $value,
        };
    }

    private function coerceNumeric(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if ($value instanceof SQLiteBlobValue) {
            return $value;
        }
        if (!is_string($value)) {
            return $value;
        }
        $trimmed = trim($value);
        if (preg_match('/^[+-]?(?:(?:[0-9]+(?:\.[0-9]*)?)|(?:\.[0-9]+))(?:[eE][+-]?[0-9]+)?$/', $trimmed) !== 1) {
            return $value;
        }
        if (preg_match('/^[+-]?[0-9]+$/', $trimmed) === 1 && self::integerLiteralFitsInt64($trimmed)) {
            return (int) $trimmed;
        }
        $real = (float) $trimmed;

        return is_finite($real) && floor($real) === $real && preg_match('/[.eE]/', $trimmed) === 1 && self::integerLiteralFitsInt64(sprintf('%.0F', $real)) ? (int) $real : $real;
    }

    private function coerceText(mixed $value): mixed
    {
        if ($value === null || is_string($value) || $value instanceof SQLiteBlobValue) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private function nullAwareRank(mixed $value): int
    {
        return $value === null ? 0 : 1;
    }

    private static function normalizeAffinity(string $affinity): string
    {
        SQLiteAffinityComparison::coercedPair(0, 0, $affinity, $affinity);
        $normalized = strtoupper($affinity);

        return match ($normalized) {
            'INT' => 'INTEGER',
            'NUM' => 'NUMERIC',
            'CHAR', 'CLOB', 'VARCHAR' => 'TEXT',
            'BLOB', '' => 'NONE',
            default => $normalized,
        };
    }

    private static function normalizeCollation(string $collation): string
    {
        $normalized = strtoupper($collation);
        SQLiteAffinityComparison::compare('', '', 'TEXT', 'TEXT', $normalized);

        return $normalized;
    }

    private static function assertComparable(mixed $value): void
    {
        SQLiteAffinityComparison::storageClass($value);
    }

    private static function sign(?int $comparison): ?int
    {
        if ($comparison === null || $comparison === 0) {
            return $comparison;
        }

        return $comparison < 0 ? -1 : 1;
    }

    private static function integerLiteralFitsInt64(string $literal): bool
    {
        $literal = trim($literal);
        $negative = str_starts_with($literal, '-');
        $digits = ltrim($literal, '+-');
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return true;
        }

        $limit = $negative ? '9223372036854775808' : '9223372036854775807';
        $length = strlen($digits);
        $limitLength = strlen($limit);
        if ($length !== $limitLength) {
            return $length < $limitLength;
        }

        return strcmp($digits, $limit) <= 0;
    }
}
