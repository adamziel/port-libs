<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonAggregateState
{
    /** @var list<mixed> */
    private array $arrayValues = [];

    /** @var list<array{0:mixed,1:mixed}> */
    private array $objectPairs = [];

    public function stepArray(mixed $value): void
    {
        $this->arrayValues[] = $value;
    }

    public function stepObject(mixed $label, mixed $value): void
    {
        $this->objectPairs[] = [$label, $value];
    }

    public function finalizeArray(string $function = 'json_group_array'): string|SQLiteBlobValue
    {
        return SQLiteJsonAggregate::jsonGroupArraySqlFunction($function, $this->arrayValues);
    }

    public function finalizeObject(string $function = 'json_group_object'): string|SQLiteBlobValue
    {
        return SQLiteJsonAggregate::jsonGroupObjectSqlFunction($function, $this->objectPairs);
    }

    /**
     * @return array{arrayRows:int,objectRows:int}
     */
    public function summary(): array
    {
        return [
            'arrayRows' => count($this->arrayValues),
            'objectRows' => count($this->objectPairs),
        ];
    }
}
