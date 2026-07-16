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

    public function autoincrementCounter(): int
    {
        return self::sqliteIntegerify($this->sequence);
    }

    private static function sqliteIntegerify(string|int|float|null $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if ($value === null) {
            return 0;
        }
        if (is_float($value)) {
            if (!is_finite($value)) {
                return 0;
            }
            if ($value >= PHP_INT_MAX) {
                return PHP_INT_MAX;
            }
            if ($value <= PHP_INT_MIN) {
                return PHP_INT_MIN;
            }

            return (int) $value;
        }

        if (preg_match('/^\s*([+-]?)([0-9]+)/', $value, $match) !== 1) {
            return 0;
        }

        $negative = $match[1] === '-';
        $digits = ltrim($match[2], '0');
        if ($digits === '') {
            return 0;
        }

        $limit = $negative ? '9223372036854775808' : '9223372036854775807';
        if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            return $negative ? PHP_INT_MIN : PHP_INT_MAX;
        }
        if ($negative && $digits === '9223372036854775808') {
            return PHP_INT_MIN;
        }

        $integer = 0;
        $length = strlen($digits);
        for ($offset = 0; $offset < $length; $offset++) {
            $integer = ($integer * 10) + ((int) $digits[$offset]);
        }

        return $negative ? -$integer : $integer;
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
