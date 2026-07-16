<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVdbeSorterCursor
{
    private int $position = 0;

    /**
     * @param list<array<string,mixed>> $rows
     */
    public function __construct(private readonly array $rows)
    {
        if (!array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite VDBE sorter cursor rows must be a list');
        }
    }

    public function eof(): bool
    {
        return $this->position >= count($this->rows);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function current(): ?array
    {
        return $this->rows[$this->position] ?? null;
    }

    public function next(): void
    {
        if (!$this->eof()) {
            $this->position++;
        }
    }

    /**
     * Returns the current row and then advances the cursor, matching the
     * VDBE sorter loop shape where OP_SorterData is followed by OP_SorterNext.
     *
     * @return array<string,mixed>|null
     */
    public function nextRow(): ?array
    {
        $row = $this->current();
        $this->next();

        return $row;
    }

    /**
     * @param list<string> $columns
     * @return list<mixed>|null
     */
    public function currentRecord(array $columns): ?array
    {
        return $this->recordAt($this->position, $columns, 'current');
    }

    /**
     * @param list<string> $columns
     * @return list<mixed>|null
     */
    public function nextRecord(array $columns): ?array
    {
        return $this->recordAt($this->position + 1, $columns, 'next');
    }

    /**
     * @param list<string> $columns
     * @param list<string>|string $affinities
     * @param list<string> $collations
     * @param list<bool> $descending
     * @param list<string|null> $nulls
     */
    public function compareCurrentToNext(
        array $columns,
        array|string $affinities = [],
        array $collations = [],
        array $descending = [],
        array $nulls = []
    ): ?int {
        $current = $this->currentRecord($columns);
        $next = $this->nextRecord($columns);
        if ($current === null || $next === null) {
            return null;
        }

        return SQLiteVdbeSortCompare::compareRecords($current, $next, $affinities, $collations, $descending, $nulls);
    }

    public function currentValue(string $column): mixed
    {
        if ($this->eof()) {
            return null;
        }

        $row = $this->current();
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite VDBE sorter current row is missing column {$column}");
        }

        return $row[$column];
    }

    public function position(): int
    {
        return $this->position;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function remainingRows(): array
    {
        return array_slice($this->rows, $this->position);
    }

    /**
     * @param list<string> $columns
     * @return list<mixed>|null
     */
    private function recordAt(int $position, array $columns, string $label): ?array
    {
        if ($position >= count($this->rows)) {
            return null;
        }
        if ($columns === [] || !array_is_list($columns)) {
            throw new \InvalidArgumentException("SQLite VDBE sorter {$label} record columns must be a non-empty list");
        }

        $row = $this->rows[$position];
        $record = [];
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite VDBE sorter {$label} row is missing column {$column}");
            }
            $record[] = $row[$column];
        }

        return $record;
    }
}
