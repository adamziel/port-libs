<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSequenceRecord
{
    public function __construct(
        public readonly string|int|float|null $name,
        public readonly string|int|float|null $sequence,
        public readonly int $rowId,
    ) {
    }

    public static function fromTableRow(SQLiteTableRow $row): self
    {
        $values = $row->values();
        if (count($values) < 2) {
            throw new \InvalidArgumentException('sqlite_sequence row must contain name and seq columns');
        }

        [$name, $sequence] = array_slice($values, 0, 2);
        if (!is_string($name) && !is_int($name) && !is_float($name) && $name !== null) {
            throw new \InvalidArgumentException('sqlite_sequence name must be a SQLite scalar');
        }
        if (!is_string($sequence) && !is_int($sequence) && !is_float($sequence) && $sequence !== null) {
            throw new \InvalidArgumentException('sqlite_sequence seq must be a SQLite scalar');
        }

        return new self($name, $sequence, $row->rowId);
    }

    public function matchesTable(string $tableName): bool
    {
        return is_string($this->name) && $this->name === $tableName;
    }

    public function integerSequence(): ?int
    {
        return is_int($this->sequence) ? $this->sequence : null;
    }

    /**
     * @return array{name:string|int|float|null,seq:string|int|float|null,rowid:int}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'seq' => $this->sequence,
            'rowid' => $this->rowId,
        ];
    }
}
