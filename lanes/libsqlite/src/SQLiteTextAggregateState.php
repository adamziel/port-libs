<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTextAggregateState
{
    /** @var list<mixed> */
    private array $values = [];

    /** @var list<mixed> */
    private array $distinctValues = [];

    /** @var list<array{0:mixed,1:mixed}> */
    private array $orderedValues = [];

    /** @var list<array{0:mixed,1:mixed}> */
    private array $distinctOrderedValues = [];

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

    public function stepOrderBy(mixed $value, mixed $orderKey): void
    {
        $this->orderedValues[] = [$value, $orderKey];
    }

    public function stepDistinctOrderBy(mixed $value, mixed $orderKey): void
    {
        $this->distinctOrderedValues[] = [$value, $orderKey];
    }

    public function stepFilter(mixed $value, mixed $filter): void
    {
        $this->filteredValues[] = [$value, $filter];
    }

    public function stepWindow(mixed $value): void
    {
        $this->windowValues[] = $value;
    }

    public function finalize(mixed $separator = ','): ?string
    {
        return SQLiteTextAggregate::groupConcat($this->values, $separator);
    }

    public function finalizeDistinct(mixed $separator = ','): ?string
    {
        return SQLiteTextAggregate::groupConcatDistinct($this->distinctValues, $separator);
    }

    public function finalizeOrdered(mixed $separator = ','): ?string
    {
        return SQLiteTextAggregate::groupConcatOrderBy($this->orderedValues, $separator);
    }

    public function finalizeDistinctOrdered(mixed $separator = ','): ?string
    {
        return SQLiteTextAggregate::groupConcatDistinctOrderBy($this->distinctOrderedValues, $separator);
    }

    public function finalizeFiltered(mixed $separator = ','): ?string
    {
        return SQLiteTextAggregate::groupConcatFilter($this->filteredValues, $separator);
    }

    /**
     * @return list<?string>
     */
    public function finalizeWindowed(int $preceding, int $following = 0, mixed $separator = ','): array
    {
        return SQLiteTextAggregate::groupConcatWindow($this->windowValues, $preceding, $following, $separator);
    }

    /**
     * @return array{rows:int,distinctRows:int,orderedRows:int,distinctOrderedRows:int,filteredRows:int,windowRows:int}
     */
    public function summary(): array
    {
        return [
            'rows' => count($this->values),
            'distinctRows' => count($this->distinctValues),
            'orderedRows' => count($this->orderedValues),
            'distinctOrderedRows' => count($this->distinctOrderedValues),
            'filteredRows' => count($this->filteredValues),
            'windowRows' => count($this->windowValues),
        ];
    }
}
