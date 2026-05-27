<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePragmaRowCursor
{
    /** @var list<array<string, int|string|null>> */
    private array $rows;

    private int $position = 0;

    /**
     * @param array{status: string, pragma: string, schema: string, target: string, rows: list<array<string, int|string|null>>} $result
     */
    public function __construct(private readonly array $result)
    {
        $this->rows = array_values($result['rows']);
    }

    /**
     * @return array{status: string, pragma: string, schema: string, target: string, row_count: int, eof: bool, position: int}
     */
    public function metadata(): array
    {
        return [
            'status' => $this->result['status'],
            'pragma' => $this->result['pragma'],
            'schema' => $this->result['schema'],
            'target' => $this->result['target'],
            'row_count' => count($this->rows),
            'eof' => !$this->valid(),
            'position' => $this->position,
        ];
    }

    /**
     * @return array<string, int|string|null>|null
     */
    public function current(): ?array
    {
        return $this->rows[$this->position] ?? null;
    }

    /**
     * @return array<string, int|string|null>|null
     */
    public function next(): ?array
    {
        ++$this->position;

        return $this->current();
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function valid(): bool
    {
        return array_key_exists($this->position, $this->rows);
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    public function remainingRows(): array
    {
        if (!$this->valid()) {
            return [];
        }

        return array_slice($this->rows, $this->position);
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    public function rows(): array
    {
        return $this->rows;
    }
}
