<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteMalformedTextCurrentNextCursor
{
    /** @var list<array{key:mixed,rowid:int,payload:array<string,mixed>}> */
    private array $entries;

    private int $offset = 0;
    private readonly string $collation;

    /**
     * @param iterable<array{key:mixed,rowid:int,payload?:array<string,mixed>}> $entries
     */
    public function __construct(iterable $entries, string $collation = 'BINARY')
    {
        $this->collation = self::normalizeCollation($collation);
        $normalized = [];
        foreach ($entries as $entry) {
            if (!is_array($entry) || !array_key_exists('key', $entry) || !array_key_exists('rowid', $entry)) {
                throw new \InvalidArgumentException('SQLite malformed text cursor entries require key and rowid fields');
            }
            if (!is_int($entry['rowid'])) {
                throw new \InvalidArgumentException('SQLite malformed text cursor rowid must be an integer');
            }
            $key = $entry['key'];
            if (!$key instanceof SQLiteBlobValue && !is_string($key) && !is_int($key) && !is_float($key) && !is_bool($key) && $key !== null) {
                throw new \InvalidArgumentException('SQLite malformed text cursor key must be scalar, BLOB, or NULL');
            }
            $payload = $entry['payload'] ?? [];
            if (!is_array($payload)) {
                throw new \InvalidArgumentException('SQLite malformed text cursor payload must be an array');
            }
            $normalized[] = [
                'key' => $key,
                'rowid' => $entry['rowid'],
                'payload' => $payload,
            ];
        }

        usort($normalized, fn (array $left, array $right): int => $this->compareEntries($left, $right));
        $this->entries = array_values($normalized);
    }

    public function next(): void
    {
        if ($this->offset < count($this->entries)) {
            $this->offset++;
        }
    }

    public function eof(): bool
    {
        return $this->offset >= count($this->entries);
    }

    /**
     * @return null|array{key:mixed,rowid:int,payload:array<string,mixed>}
     */
    public function current(): ?array
    {
        return $this->entries[$this->offset] ?? null;
    }

    /**
     * @return null|array{key:mixed,rowid:int,payload:array<string,mixed>}
     */
    public function nextEntry(): ?array
    {
        return $this->entries[$this->offset + 1] ?? null;
    }

    public function seek(mixed $probe): void
    {
        $this->assertProbe($probe);
        $this->offset = count($this->entries);
        foreach ($this->entries as $index => $entry) {
            $comparison = SQLiteAffinityComparison::compare($entry['key'], $probe, 'NONE', 'NONE', $this->collation);
            if ($comparison !== null && $comparison >= 0) {
                $this->offset = $index;
                break;
            }
        }
    }

    /**
     * @return array{
     *   collation:string,
     *   offset:int,
     *   eof:bool,
     *   currentRowid:?int,
     *   currentKey:mixed,
     *   currentStorage:?string,
     *   currentMalformedUtf8:?bool,
     *   nextRowid:?int,
     *   nextKey:mixed,
     *   nextStorage:?string,
     *   nextMalformedUtf8:?bool,
     *   currentToNext:?int,
     *   currentEqualsNext:bool,
     *   comparisonToProbe:?int
     * }
     */
    public function currentNextPlan(mixed $probe = null): array
    {
        if (func_num_args() > 0) {
            $this->assertProbe($probe);
        }
        $current = $this->current();
        $next = $this->nextEntry();
        $currentKey = $current['key'] ?? null;
        $nextKey = $next['key'] ?? null;
        $currentToNext = $current === null || $next === null
            ? null
            : self::sign(SQLiteAffinityComparison::compare($currentKey, $nextKey, 'NONE', 'NONE', $this->collation));
        $comparisonToProbe = $current === null || func_num_args() === 0
            ? null
            : self::sign(SQLiteAffinityComparison::compare($currentKey, $probe, 'NONE', 'NONE', $this->collation));

        return [
            'collation' => $this->collation,
            'offset' => $this->offset,
            'eof' => $this->eof(),
            'currentRowid' => $current['rowid'] ?? null,
            'currentKey' => $currentKey,
            'currentStorage' => $current === null ? null : SQLiteAffinityComparison::storageClass($currentKey),
            'currentMalformedUtf8' => is_string($currentKey) ? !self::isValidUtf8($currentKey) : null,
            'nextRowid' => $next['rowid'] ?? null,
            'nextKey' => $nextKey,
            'nextStorage' => $next === null ? null : SQLiteAffinityComparison::storageClass($nextKey),
            'nextMalformedUtf8' => is_string($nextKey) ? !self::isValidUtf8($nextKey) : null,
            'currentToNext' => $currentToNext,
            'currentEqualsNext' => $currentToNext === 0,
            'comparisonToProbe' => $comparisonToProbe,
        ];
    }

    /**
     * @return list<array{key:mixed,rowid:int,payload:array<string,mixed>}>
     */
    public function remaining(): array
    {
        return array_slice($this->entries, $this->offset);
    }

    /**
     * @return list<array{key:mixed,rowid:int,payload:array<string,mixed>}>
     */
    public function range(mixed $lowerInclusive, mixed $upperExclusive): array
    {
        $this->assertProbe($lowerInclusive);
        $this->assertProbe($upperExclusive);
        $matched = [];
        foreach ($this->entries as $entry) {
            $lower = SQLiteAffinityComparison::compare($entry['key'], $lowerInclusive, 'NONE', 'NONE', $this->collation);
            $upper = SQLiteAffinityComparison::compare($entry['key'], $upperExclusive, 'NONE', 'NONE', $this->collation);
            if ($lower !== null && $upper !== null && $lower >= 0 && $upper < 0) {
                $matched[] = $entry;
            }
        }

        return $matched;
    }

    /**
     * @param array<string,mixed> $filters
     * @return list<array{key:mixed,rowid:int,payload:array<string,mixed>}>
     */
    public static function optionRowNameRange(array $rows, mixed $lowerInclusive, mixed $upperExclusive, string $collation = 'BINARY', array $filters = []): array
    {
        $entries = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row) || !array_key_exists('option_name', $row)) {
                throw new \InvalidArgumentException('Application option rows require option_name');
            }
            foreach ($filters as $column => $expected) {
                if (!array_key_exists($column, $row) || $row[$column] !== $expected) {
                    continue 2;
                }
            }
            $entries[] = [
                'key' => $row['option_name'],
                'rowid' => is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1,
                'payload' => $row,
            ];
        }

        return (new self($entries, $collation))->range($lowerInclusive, $upperExclusive);
    }

    /**
     * @param array{key:mixed,rowid:int,payload:array<string,mixed>} $left
     * @param array{key:mixed,rowid:int,payload:array<string,mixed>} $right
     */
    private function compareEntries(array $left, array $right): int
    {
        $comparison = SQLiteAffinityComparison::compare($left['key'], $right['key'], 'NONE', 'NONE', $this->collation);
        if ($comparison !== null && $comparison !== 0) {
            return $comparison;
        }

        return $left['rowid'] <=> $right['rowid'];
    }

    private static function normalizeCollation(string $collation): string
    {
        $normalized = strtoupper($collation);
        if (!in_array($normalized, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException("SQLite malformed text cursor collation {$collation} is not supported");
        }

        return $normalized;
    }

    private function assertProbe(mixed $probe): void
    {
        SQLiteAffinityComparison::compare($probe, $probe, 'NONE', 'NONE', $this->collation);
    }

    private static function sign(?int $comparison): ?int
    {
        if ($comparison === null || $comparison === 0) {
            return $comparison;
        }

        return $comparison < 0 ? -1 : 1;
    }

    private static function isValidUtf8(string $value): bool
    {
        return preg_match('//u', $value) === 1;
    }
}
