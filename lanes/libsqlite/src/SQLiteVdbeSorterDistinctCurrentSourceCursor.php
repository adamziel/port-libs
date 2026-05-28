<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVdbeSorterDistinctCurrentSourceCursor
{
    private SQLiteVdbeAggregateDistinctCursor $cursor;

    /**
     * @param list<array<string,mixed>> $rows
     * @param non-empty-list<string>|string $distinctColumns
     * @param list<string>|string $affinities
     * @param list<string> $collations
     */
    public function __construct(
        private array $rows,
        private readonly array|string $distinctColumns,
        private readonly string $valueColumn,
        private readonly ?string $filterColumn = null,
        private readonly array|string $affinities = [],
        private readonly array $collations = [],
        private string $sourceToken = 'initial',
    ) {
        $this->cursor = $this->makeCursor($rows);
    }

    public function sourceToken(): string
    {
        return $this->sourceToken;
    }

    public function eof(): bool
    {
        return $this->cursor->eof();
    }

    public function next(): void
    {
        $this->cursor->next();
    }

    /**
     * @return list<mixed>
     */
    public function currentKey(): array
    {
        return $this->cursor->currentKey();
    }

    public function currentValue(): mixed
    {
        return $this->cursor->currentValue();
    }

    /**
     * @return array<string,mixed>
     */
    public function currentRow(): array
    {
        return $this->cursor->currentRow();
    }

    /**
     * @return list<mixed>
     */
    public function values(): array
    {
        return $this->cursor->values();
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    public function refresh(array $rows, string $sourceToken): bool
    {
        if ($sourceToken === '') {
            throw new \InvalidArgumentException('SQLite VDBE sorter DISTINCT current source token must be non-empty');
        }
        if ($sourceToken === $this->sourceToken) {
            return false;
        }

        $seekKey = $this->cursor->eof() ? null : $this->cursor->currentKey();
        $this->rows = $rows;
        $this->sourceToken = $sourceToken;
        $this->cursor = $this->makeCursor($rows);

        if ($seekKey !== null) {
            $this->seekAtOrAfter($seekKey);
        }

        return true;
    }

    /**
     * @return array{sourceToken:string,eof:bool,currentKey:list<mixed>|null,currentValue:mixed,distinctRows:int,values:list<mixed>}
     */
    public function snapshot(): array
    {
        return [
            'sourceToken' => $this->sourceToken,
            'eof' => $this->cursor->eof(),
            'currentKey' => $this->cursor->eof() ? null : $this->cursor->currentKey(),
            'currentValue' => $this->cursor->eof() ? null : $this->cursor->currentValue(),
            'distinctRows' => count($this->cursor->values()),
            'values' => $this->cursor->values(),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private function makeCursor(array $rows): SQLiteVdbeAggregateDistinctCursor
    {
        return new SQLiteVdbeAggregateDistinctCursor(
            $rows,
            $this->distinctColumns,
            $this->valueColumn,
            $this->filterColumn,
            $this->affinities,
            $this->collations
        );
    }

    /**
     * @param list<mixed> $seekKey
     */
    private function seekAtOrAfter(array $seekKey): void
    {
        while (!$this->cursor->eof()) {
            $comparison = SQLiteVdbeSortCompare::compareRecords(
                $this->cursor->currentKey(),
                $seekKey,
                $this->affinities,
                $this->collations
            );
            if ($comparison >= 0) {
                return;
            }

            $this->cursor->next();
        }
    }
}
