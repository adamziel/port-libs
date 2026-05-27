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
