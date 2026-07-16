<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonTableCursor
{
    /** @var list<array<string,mixed>> */
    private array $rows;
    private int $position = 0;

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     */
    public function __construct(
        private readonly string $function,
        private readonly array $constraints,
        private readonly array $plan,
    ) {
        $this->rows = $plan['runnable'] ? SQLiteJsonTablePlan::filteredRows($function, $constraints) : [];
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     */
    public static function open(string $function, array $constraints): self
    {
        $plan = SQLiteJsonTablePlan::validatedPlan($function, $constraints);

        return new self($plan['function'], $constraints, $plan);
    }

    public function functionName(): string
    {
        return $this->function;
    }

    /**
     * @return array<string,mixed>
     */
    public function plan(): array
    {
        return $this->plan;
    }

    public function eof(): bool
    {
        return !array_key_exists($this->position, $this->rows);
    }

    public function next(): void
    {
        if (!$this->eof()) {
            $this->position++;
        }
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function rowid(): ?int
    {
        if ($this->eof()) {
            return null;
        }

        $rowid = $this->rows[$this->position]['id'] ?? null;
        if (!is_int($rowid)) {
            throw new \InvalidArgumentException('SQLite JSON table cursor rowid is not an integer');
        }

        return $rowid;
    }

    public function column(string $column): mixed
    {
        if ($this->eof()) {
            throw new \OutOfBoundsException('SQLite JSON table cursor is at EOF');
        }

        $column = strtolower($column);
        if ($column === 'rowid' || $column === '_rowid_' || $column === 'oid') {
            return $this->rowid();
        }
        if (!array_key_exists($column, $this->rows[$this->position])) {
            throw new \InvalidArgumentException("SQLite JSON table cursor column {$column} is not available");
        }

        return $this->rows[$this->position][$column];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function row(): ?array
    {
        return $this->eof() ? null : $this->rows[$this->position];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function all(): array
    {
        return $this->rows;
    }

    public function count(): int
    {
        return count($this->rows);
    }
}
