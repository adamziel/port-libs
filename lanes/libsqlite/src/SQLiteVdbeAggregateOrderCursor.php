<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVdbeAggregateOrderCursor
{
    private SQLiteVdbeSorterCursor $cursor;

    /** @var list<array<string,mixed>> */
    private array $orderedRows;
    private int $inputRowCount;
    private int $filteredRowCount;

    /**
     * @param list<array<string,mixed>> $rows
     * @param non-empty-list<string> $orderColumns
     * @param list<string>|string $orderAffinities
     * @param list<string> $orderCollations
     * @param list<bool> $orderDescending
     * @param list<string|null> $orderNulls
     */
    public function __construct(
        array $rows,
        private readonly string $valueColumn,
        private readonly array $orderColumns,
        private readonly ?string $filterColumn = null,
        private readonly array|string $orderAffinities = [],
        private readonly array $orderCollations = [],
        private readonly array $orderDescending = [],
        private readonly array $orderNulls = [],
    ) {
        if (!array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite VDBE aggregate ORDER BY rows must be a list');
        }
        if ($valueColumn === '') {
            throw new \InvalidArgumentException('SQLite VDBE aggregate ORDER BY value column must be non-empty');
        }
        self::assertColumnList($orderColumns);

        $this->inputRowCount = count($rows);
        $filtered = [];
        foreach ($rows as $row) {
            if ($filterColumn !== null) {
                if (!array_key_exists($filterColumn, $row)) {
                    throw new \InvalidArgumentException("SQLite VDBE aggregate ORDER BY row is missing filter column {$filterColumn}");
                }
                if (!self::isSqlTrue($row[$filterColumn])) {
                    continue;
                }
            }
            if (!array_key_exists($valueColumn, $row)) {
                throw new \InvalidArgumentException("SQLite VDBE aggregate ORDER BY row is missing value column {$valueColumn}");
            }
            $record = [];
            foreach ($orderColumns as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite VDBE aggregate ORDER BY row is missing order column {$column}");
                }
                self::assertOrderValue($row[$column]);
                $record[] = $row[$column];
            }
            SQLiteVdbeSortCompare::compareRecords($record, $record, $orderAffinities, $orderCollations, $orderDescending, $orderNulls);

            $filtered[] = $row;
        }

        $this->filteredRowCount = count($filtered);
        $this->orderedRows = $filtered === []
            ? []
            : SQLiteVdbeSortCompare::sortRows($filtered, $orderColumns, $orderAffinities, $orderCollations, $orderDescending, $orderNulls);
        $this->cursor = new SQLiteVdbeSorterCursor($this->orderedRows);
    }

    public function eof(): bool
    {
        return $this->cursor->eof();
    }

    public function next(): void
    {
        $this->cursor->next();
    }

    public function currentValue(): mixed
    {
        if ($this->cursor->eof()) {
            throw new \OutOfBoundsException('SQLite VDBE aggregate ORDER BY cursor is at EOF');
        }

        return $this->cursor->currentValue($this->valueColumn);
    }

    /**
     * @return array<string,mixed>
     */
    public function currentRow(): array
    {
        $row = $this->cursor->current();
        if ($row === null) {
            throw new \OutOfBoundsException('SQLite VDBE aggregate ORDER BY cursor is at EOF');
        }

        return $row;
    }

    /**
     * @return list<mixed>
     */
    public function values(): array
    {
        return array_map(fn (array $row): mixed => $row[$this->valueColumn], $this->orderedRows);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function remainingRows(): array
    {
        return $this->cursor->remainingRows();
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
     * @return array{inputRows:int,filteredRows:int,orderedRows:int,filter:bool,eof:bool}
     */
    public function summary(): array
    {
        return [
            'inputRows' => $this->inputRowCount,
            'filteredRows' => $this->filteredRowCount,
            'orderedRows' => count($this->orderedRows),
            'filter' => $this->filterColumn !== null,
            'eof' => $this->eof(),
        ];
    }

    /**
     * @param list<string> $columns
     */
    private static function assertColumnList(array $columns): void
    {
        if ($columns === [] || !array_is_list($columns)) {
            throw new \InvalidArgumentException('SQLite VDBE aggregate ORDER BY columns must be a non-empty list');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite VDBE aggregate ORDER BY columns must be non-empty strings');
            }
        }
    }

    private static function assertOrderValue(mixed $value): void
    {
        if (
            $value === null
            || is_bool($value)
            || is_int($value)
            || is_float($value)
            || is_string($value)
            || $value instanceof SQLiteBlobValue
        ) {
            return;
        }

        throw new \InvalidArgumentException('SQLite VDBE aggregate ORDER BY values must be scalar, BLOB, or NULL');
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
