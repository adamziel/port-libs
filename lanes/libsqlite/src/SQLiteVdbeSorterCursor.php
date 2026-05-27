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
        if ($this->eof()) {
            return null;
        }
        if ($columns === [] || !array_is_list($columns)) {
            throw new \InvalidArgumentException('SQLite VDBE sorter current record columns must be a non-empty list');
        }

        $row = $this->current();
        $record = [];
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite VDBE sorter current row is missing column {$column}");
            }
            $record[] = $row[$column];
        }

        return $record;
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
}
