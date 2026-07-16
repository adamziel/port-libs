<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVdbeAggregateDistinctCursor
{
    /** @var list<array{key:list<mixed>,value:mixed,row:array<string,mixed>,sequence:int}> */
    private array $entries;
    private int $position = 0;

    /**
     * @param list<array<string,mixed>> $rows
     * @param non-empty-list<string>|string $distinctColumns
     * @param list<string>|string $affinities
     * @param list<string> $collations
     */
    public function __construct(
        array $rows,
        array|string $distinctColumns,
        private readonly string $valueColumn,
        private readonly ?string $filterColumn = null,
        private readonly array|string $affinities = [],
        private readonly array $collations = [],
    ) {
        if (!array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite VDBE aggregate DISTINCT rows must be a list');
        }
        if ($valueColumn === '') {
            throw new \InvalidArgumentException('SQLite VDBE aggregate DISTINCT value column must be non-empty');
        }

        $columns = self::distinctColumns($distinctColumns);
        $entries = [];
        foreach ($rows as $sequence => $row) {
            if ($filterColumn !== null) {
                if (!array_key_exists($filterColumn, $row)) {
                    throw new \InvalidArgumentException("SQLite VDBE aggregate DISTINCT row is missing filter column {$filterColumn}");
                }
                if (!self::isSqlTrue($row[$filterColumn])) {
                    continue;
                }
            }
            if (!array_key_exists($valueColumn, $row)) {
                throw new \InvalidArgumentException("SQLite VDBE aggregate DISTINCT row is missing value column {$valueColumn}");
            }

            $key = [];
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite VDBE aggregate DISTINCT row is missing key column {$column}");
                }
                $key[] = $row[$column];
            }

            $entries[] = [
                'key' => $key,
                'value' => $row[$valueColumn],
                'row' => $row,
                'sequence' => $sequence,
            ];
        }

        usort($entries, function (array $left, array $right): int {
            $comparison = SQLiteVdbeSortCompare::compareRecords(
                $left['key'],
                $right['key'],
                $this->affinities,
                $this->collations
            );

            return $comparison !== 0 ? $comparison : ($left['sequence'] <=> $right['sequence']);
        });

        $distinct = [];
        foreach ($entries as $entry) {
            $last = $distinct[array_key_last($distinct)] ?? null;
            if ($last !== null && SQLiteVdbeSortCompare::compareRecords($last['key'], $entry['key'], $this->affinities, $this->collations) === 0) {
                continue;
            }

            $distinct[] = $entry;
        }

        $this->entries = $distinct;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function next(): void
    {
        if (!$this->eof()) {
            $this->position++;
        }
    }

    public function eof(): bool
    {
        return $this->position >= count($this->entries);
    }

    /**
     * @return null|array{key:list<mixed>,value:mixed,row:array<string,mixed>,sequence:int}
     */
    public function current(): ?array
    {
        return $this->entries[$this->position] ?? null;
    }

    /**
     * @return list<mixed>
     */
    public function currentKey(): array
    {
        $current = $this->current();
        if ($current === null) {
            throw new \OutOfBoundsException('SQLite VDBE aggregate DISTINCT cursor is at EOF');
        }

        return $current['key'];
    }

    public function currentValue(): mixed
    {
        $current = $this->current();
        if ($current === null) {
            throw new \OutOfBoundsException('SQLite VDBE aggregate DISTINCT cursor is at EOF');
        }

        return $current['value'];
    }

    /**
     * @return array<string,mixed>
     */
    public function currentRow(): array
    {
        $current = $this->current();
        if ($current === null) {
            throw new \OutOfBoundsException('SQLite VDBE aggregate DISTINCT cursor is at EOF');
        }

        return $current['row'];
    }

    /**
     * @return list<array{key:list<mixed>,value:mixed,row:array<string,mixed>,sequence:int}>
     */
    public function remaining(): array
    {
        $rows = [];
        while (!$this->eof()) {
            $rows[] = $this->current();
            $this->next();
        }

        return $rows;
    }

    /**
     * @return list<mixed>
     */
    public function values(): array
    {
        return array_map(static fn (array $entry): mixed => $entry['value'], $this->entries);
    }

    public function countValue(): int
    {
        return SQLiteNumericAggregate::countValue($this->values());
    }

    public function sum(): int|float|null
    {
        return SQLiteNumericAggregate::sum($this->values());
    }

    public function total(): float
    {
        return SQLiteNumericAggregate::total($this->values());
    }

    public function avg(): ?float
    {
        return SQLiteNumericAggregate::avg($this->values());
    }

    public function groupConcat(int|float|string|SQLiteBlobValue|null $separator = ','): ?string
    {
        return SQLiteTextAggregate::groupConcat($this->values(), $separator);
    }

    /**
     * @return array{inputRows:int,distinctRows:int,filtered:bool,eof:bool}
     */
    public function summary(int $inputRows): array
    {
        if ($inputRows < 0) {
            throw new \InvalidArgumentException('SQLite VDBE aggregate DISTINCT input row count must be non-negative');
        }

        return [
            'inputRows' => $inputRows,
            'distinctRows' => count($this->entries),
            'filtered' => $this->filterColumn !== null,
            'eof' => $this->eof(),
        ];
    }

    /**
     * @return non-empty-list<string>
     */
    private static function distinctColumns(array|string $columns): array
    {
        if (is_string($columns)) {
            if ($columns === '') {
                throw new \InvalidArgumentException('SQLite VDBE aggregate DISTINCT key column must be non-empty');
            }

            return [$columns];
        }
        if (!array_is_list($columns) || $columns === []) {
            throw new \InvalidArgumentException('SQLite VDBE aggregate DISTINCT key columns must be a non-empty list');
        }

        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite VDBE aggregate DISTINCT key columns must be non-empty strings');
            }
        }

        return $columns;
    }

    private static function isSqlTrue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value != 0.0;
        }
        if (is_string($value)) {
            return is_numeric($value) && (float) $value != 0.0;
        }

        return false;
    }
}
