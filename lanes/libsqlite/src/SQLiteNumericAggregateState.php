<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteNumericAggregateState
{
    /** @var list<mixed> */
    private array $values = [];

    /** @var list<mixed> */
    private array $distinctValues = [];

    /** @var list<array{0:mixed,1:mixed}> */
    private array $filteredValues = [];

    /** @var list<mixed> */
    private array $windowValues = [];

    public function step(mixed $value): void
    {
        $this->values[] = $value;
    }

    public function stepDistinct(mixed $value): void
    {
        $this->distinctValues[] = $value;
    }

    public function stepFilter(mixed $value, mixed $filter): void
    {
        $this->filteredValues[] = [$value, $filter];
    }

    public function stepWindow(mixed $value): void
    {
        $this->windowValues[] = $value;
    }

    public function countAll(): int
    {
        return SQLiteNumericAggregate::countAll($this->values);
    }

    public function countValue(): int
    {
        return SQLiteNumericAggregate::countValue($this->values);
    }

    public function countDistinct(): int
    {
        return SQLiteNumericAggregate::countDistinct($this->distinctValues);
    }

    public function sum(): int|float|null
    {
        return SQLiteNumericAggregate::sum($this->values);
    }

    public function sumDistinct(): int|float|null
    {
        return SQLiteNumericAggregate::sumDistinct($this->distinctValues);
    }

    public function total(): float
    {
        return SQLiteNumericAggregate::total($this->values);
    }

    public function totalDistinct(): float
    {
        return SQLiteNumericAggregate::totalDistinct($this->distinctValues);
    }

    public function avg(): ?float
    {
        return SQLiteNumericAggregate::avg($this->values);
    }

    public function avgDistinct(): ?float
    {
        return SQLiteNumericAggregate::avgDistinct($this->distinctValues);
    }

    public function min(): mixed
    {
        return SQLiteNumericAggregate::min($this->values);
    }

    public function max(): mixed
    {
        return SQLiteNumericAggregate::max($this->values);
    }

    public function sumFiltered(): int|float|null
    {
        return SQLiteNumericAggregate::sumFilter($this->filteredValues);
    }

    /**
     * @return list<float>
     */
    public function totalWindowed(int $preceding, int $following = 0): array
    {
        return SQLiteNumericAggregate::totalWindow($this->windowValues, $preceding, $following);
    }

    /**
     * @return array{rows:int,distinctRows:int,filteredRows:int,windowRows:int}
     */
    public function summary(): array
    {
        return [
            'rows' => count($this->values),
            'distinctRows' => count($this->distinctValues),
            'filteredRows' => count($this->filteredValues),
            'windowRows' => count($this->windowValues),
        ];
    }
}
