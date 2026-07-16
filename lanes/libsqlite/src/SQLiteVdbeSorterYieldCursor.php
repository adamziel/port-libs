<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVdbeSorterYieldCursor
{
    /** @var list<array{row:array<string,mixed>,record:list<mixed>,sequence:int,previousSequence:int|null,comparison:int|null,stableTie:bool,steps:list<array<string,mixed>>}> */
    private array $trace;
    private int $position = 0;

    /**
     * @param list<array<string,mixed>> $rows
     * @param non-empty-list<string> $columns
     * @param list<string>|string $affinities
     * @param list<string> $collations
     * @param list<bool> $descending
     * @param list<string|null> $nulls
     */
    public function __construct(
        array $rows,
        private readonly array $columns,
        array|string $affinities = [],
        array $collations = [],
        array $descending = [],
        array $nulls = []
    ) {
        if (!array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite VDBE sorter yield rows must be a list');
        }
        $this->assertColumnList($columns);
        $this->trace = SQLiteVdbeSortCompare::sortedRowTrace(
            $rows,
            $columns,
            $affinities,
            $collations,
            $descending,
            $nulls
        );
    }

    public function eof(): bool
    {
        return !isset($this->trace[$this->position]);
    }

    public function next(): void
    {
        if (!$this->eof()) {
            $this->position++;
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function current(): ?array
    {
        return $this->entry()['row'] ?? null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function nextRow(): ?array
    {
        $row = $this->current();
        $this->next();

        return $row;
    }

    /**
     * @return list<mixed>|null
     */
    public function currentRecord(): ?array
    {
        return $this->entry()['record'] ?? null;
    }

    public function currentValue(string $column): mixed
    {
        if ($this->eof()) {
            return null;
        }

        $row = $this->current();
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite VDBE sorter yielded row is missing column {$column}");
        }

        return $row[$column];
    }

    public function sequence(): ?int
    {
        return $this->entry()['sequence'] ?? null;
    }

    public function previousSequence(): ?int
    {
        return $this->entry()['previousSequence'] ?? null;
    }

    public function comparisonFromPrevious(): ?int
    {
        return $this->entry()['comparison'] ?? null;
    }

    public function stableTieFromPrevious(): bool
    {
        return $this->entry()['stableTie'] ?? false;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function comparisonStepsFromPrevious(): array
    {
        return $this->entry()['steps'] ?? [];
    }

    public function position(): int
    {
        return $this->position;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function remainingRows(): array
    {
        $rows = [];
        foreach (array_slice($this->trace, $this->position) as $entry) {
            $rows[] = $entry['row'];
        }

        return $rows;
    }

    /**
     * @return array{position:int,sequence:int|null,previousSequence:int|null,record:list<mixed>|null,comparison:int|null,stableTie:bool,eof:bool,decidingIndex:int|null,decidingCollation:string|null,decidingNulls:string|null,decidingDescending:bool|null}
     */
    public function currentSummary(): array
    {
        $entry = $this->entry();
        $deciding = null;
        foreach ($entry['steps'] ?? [] as $step) {
            if (($step['decided'] ?? false) === true) {
                $deciding = $step;
                break;
            }
        }

        return [
            'position' => $this->position,
            'sequence' => $entry['sequence'] ?? null,
            'previousSequence' => $entry['previousSequence'] ?? null,
            'record' => $entry['record'] ?? null,
            'comparison' => $entry['comparison'] ?? null,
            'stableTie' => $entry['stableTie'] ?? false,
            'eof' => $this->eof(),
            'decidingIndex' => $deciding['index'] ?? null,
            'decidingCollation' => $deciding['collation'] ?? null,
            'decidingNulls' => $deciding['nulls'] ?? null,
            'decidingDescending' => $deciding['descending'] ?? null,
        ];
    }

    /**
     * @return list<array{position:int,sequence:int|null,previousSequence:int|null,record:list<mixed>|null,comparison:int|null,stableTie:bool,eof:bool,decidingIndex:int|null,decidingCollation:string|null,decidingNulls:string|null,decidingDescending:bool|null}>
     */
    public function drainSummaries(): array
    {
        $summaries = [];
        while (!$this->eof()) {
            $summaries[] = $this->currentSummary();
            $this->next();
        }

        return $summaries;
    }

    /**
     * @return array{row:array<string,mixed>,record:list<mixed>,sequence:int,previousSequence:int|null,comparison:int|null,stableTie:bool,steps:list<array<string,mixed>>}|null
     */
    private function entry(): ?array
    {
        return $this->trace[$this->position] ?? null;
    }

    /**
     * @param list<string> $columns
     */
    private function assertColumnList(array $columns): void
    {
        if ($columns === [] || !array_is_list($columns)) {
            throw new \InvalidArgumentException('SQLite VDBE sorter yield columns must be a non-empty list');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite VDBE sorter yield columns must be non-empty strings');
            }
        }
    }
}
