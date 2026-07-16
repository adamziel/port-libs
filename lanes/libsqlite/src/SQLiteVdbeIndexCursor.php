<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVdbeIndexCursor
{
    /** @var list<array{key:list<mixed>, rowid:int, payload:array<string,mixed>, sequence:int}> */
    private array $entries;
    private int $position = 0;

    /**
     * @param list<array{key:list<mixed>,rowid?:int,payload?:array<string,mixed>}> $entries
     * @param list<string>|string $affinities
     * @param list<string> $collations
     * @param list<bool> $descending
     */
    public function __construct(
        array $entries,
        private readonly array|string $affinities = [],
        private readonly array $collations = [],
        private readonly array $descending = []
    ) {
        if (!array_is_list($entries)) {
            throw new \InvalidArgumentException('SQLite VDBE index cursor entries must be a list');
        }

        $normalized = [];
        foreach ($entries as $sequence => $entry) {
            if (!isset($entry['key']) || !array_is_list($entry['key'])) {
                throw new \InvalidArgumentException('SQLite VDBE index cursor entry key must be a list');
            }

            $normalized[] = [
                'key' => $entry['key'],
                'rowid' => (int) ($entry['rowid'] ?? $sequence + 1),
                'payload' => $entry['payload'] ?? [],
                'sequence' => $sequence,
            ];
        }

        usort($normalized, function (array $left, array $right): int {
            $comparison = SQLiteVdbeSortCompare::compareRecords(
                $left['key'],
                $right['key'],
                $this->affinities,
                $this->collations,
                $this->descending
            );

            if ($comparison !== 0) {
                return $comparison;
            }

            $rowidComparison = $left['rowid'] <=> $right['rowid'];

            return $rowidComparison !== 0 ? $rowidComparison : ($left['sequence'] <=> $right['sequence']);
        });

        $this->entries = $normalized;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function eof(): bool
    {
        return $this->position >= count($this->entries);
    }

    public function next(): void
    {
        if (!$this->eof()) {
            $this->position++;
        }
    }

    /**
     * @return null|array{key:list<mixed>,rowid:int,payload:array<string,mixed>,sequence:int}
     */
    public function current(): ?array
    {
        return $this->entries[$this->position] ?? null;
    }

    /**
     * @return list<mixed>
     */
    public function currentKey(): array
    {
        $current = $this->current();
        if ($current === null) {
            throw new \OutOfBoundsException('SQLite VDBE index cursor is at EOF');
        }

        return $current['key'];
    }

    public function currentRowid(): int
    {
        $current = $this->current();
        if ($current === null) {
            throw new \OutOfBoundsException('SQLite VDBE index cursor is at EOF');
        }

        return $current['rowid'];
    }

    public function currentColumn(int $column): mixed
    {
        if ($column < 0) {
            throw new \InvalidArgumentException('SQLite VDBE index cursor column must be non-negative');
        }

        $key = $this->currentKey();
        if (!array_key_exists($column, $key)) {
            throw new \OutOfBoundsException("SQLite VDBE index cursor missing key column {$column}");
        }

        return $key[$column];
    }

    /**
     * @param list<int>|null $columns
     * @return list<mixed>|null
     */
    public function currentRecord(?array $columns = null): ?array
    {
        return $this->recordAt($this->position, $columns, 'current');
    }

    /**
     * @param list<int>|null $columns
     * @return list<mixed>|null
     */
    public function nextRecord(?array $columns = null): ?array
    {
        return $this->recordAt($this->position + 1, $columns, 'next');
    }

    /**
     * @param list<int>|null $columns
     * @param list<string>|string|null $affinities
     * @param list<string>|null $collations
     * @param list<bool>|null $descending
     */
    public function compareCurrentToNext(
        ?array $columns = null,
        array|string|null $affinities = null,
        ?array $collations = null,
        ?array $descending = null
    ): ?int {
        $current = $this->currentRecord($columns);
        $next = $this->nextRecord($columns);
        if ($current === null || $next === null) {
            return null;
        }

        return SQLiteVdbeSortCompare::compareRecords(
            $current,
            $next,
            $affinities ?? $this->affinities,
            $collations ?? $this->collations,
            $descending ?? $this->descending
        );
    }

    /**
     * @param list<mixed> $probe
     */
    public function seekGreaterOrEqual(array $probe): bool
    {
        if (!array_is_list($probe)) {
            throw new \InvalidArgumentException('SQLite VDBE index cursor probe key must be a list');
        }

        foreach ($this->entries as $position => $entry) {
            if ($this->comparePrefix($entry['key'], $probe) >= 0) {
                $this->position = $position;

                return true;
            }
        }

        $this->position = count($this->entries);

        return false;
    }

    /**
     * @param list<mixed> $probe
     * @return list<array{key:list<mixed>,rowid:int,payload:array<string,mixed>,sequence:int}>
     */
    public function yieldEqual(array $probe): array
    {
        if (!$this->seekGreaterOrEqual($probe)) {
            return [];
        }

        $matched = [];
        while (!$this->eof() && $this->comparePrefix($this->currentKey(), $probe) === 0) {
            $matched[] = $this->current();
            $this->next();
        }

        return $matched;
    }

    /**
     * @return list<array{key:list<mixed>,rowid:int,payload:array<string,mixed>,sequence:int}>
     */
    public function remaining(): array
    {
        $rows = [];
        while (!$this->eof()) {
            $rows[] = $this->current();
            $this->next();
        }

        return $rows;
    }

    /**
     * @param list<mixed> $key
     * @param list<mixed> $probe
     */
    private function comparePrefix(array $key, array $probe): int
    {
        if (count($probe) > count($key)) {
            throw new \InvalidArgumentException('SQLite VDBE index cursor probe key is wider than index key');
        }

        return SQLiteVdbeSortCompare::compareRecords(
            array_slice($key, 0, count($probe)),
            $probe,
            $this->affinities,
            $this->collations,
            $this->descending
        );
    }

    /**
     * @param list<int>|null $columns
     * @return list<mixed>|null
     */
    private function recordAt(int $position, ?array $columns, string $label): ?array
    {
        $entry = $this->entries[$position] ?? null;
        if ($entry === null) {
            return null;
        }

        if ($columns === null) {
            return $entry['key'];
        }
        if ($columns === [] || !array_is_list($columns)) {
            throw new \InvalidArgumentException("SQLite VDBE index cursor {$label} record columns must be a non-empty list");
        }

        $record = [];
        foreach ($columns as $column) {
            if (!is_int($column) || $column < 0) {
                throw new \InvalidArgumentException("SQLite VDBE index cursor {$label} record columns must be non-negative integers");
            }
            if (!array_key_exists($column, $entry['key'])) {
                throw new \OutOfBoundsException("SQLite VDBE index cursor {$label} record missing key column {$column}");
            }
            $record[] = $entry['key'][$column];
        }

        return $record;
    }
}
