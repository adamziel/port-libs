<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVdbeSorterDistinctGroupCursor
{
    private SQLiteVdbeSorterCursor $cursor;

    /** @var null|array{key:list<mixed>,rows:list<array<string,mixed>>,distinct:SQLiteVdbeAggregateDistinctCursor} */
    private ?array $group = null;

    /**
     * @param list<array<string,mixed>> $rows
     * @param non-empty-list<string> $groupColumns
     * @param non-empty-list<string>|string $distinctColumns
     * @param list<string>|string $groupAffinities
     * @param list<string> $groupCollations
     * @param list<bool> $groupDescending
     * @param list<string|null> $groupNulls
     * @param list<string>|string $distinctAffinities
     * @param list<string> $distinctCollations
     */
    public function __construct(
        array $rows,
        private readonly array $groupColumns,
        private readonly array|string $distinctColumns,
        private readonly string $valueColumn,
        private readonly ?string $filterColumn = null,
        private readonly array|string $groupAffinities = [],
        private readonly array $groupCollations = [],
        private readonly array $groupDescending = [],
        private readonly array $groupNulls = [],
        private readonly array|string $distinctAffinities = [],
        private readonly array $distinctCollations = [],
    ) {
        self::assertColumnList($groupColumns, 'group');
        if ($valueColumn === '') {
            throw new \InvalidArgumentException('SQLite VDBE sorter DISTINCT group value column must be non-empty');
        }

        $this->cursor = SQLiteVdbeSortCompare::cursor(
            $rows,
            $groupColumns,
            $groupAffinities,
            $groupCollations,
            $groupDescending,
            $groupNulls
        );
        $this->loadCurrentGroup();
    }

    public function eof(): bool
    {
        return $this->group === null;
    }

    public function next(): void
    {
        if ($this->group !== null) {
            $this->loadCurrentGroup();
        }
    }

    /**
     * @return list<mixed>
     */
    public function currentGroupKey(): array
    {
        if ($this->group === null) {
            throw new \OutOfBoundsException('SQLite VDBE sorter DISTINCT group cursor is at EOF');
        }

        return $this->group['key'];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function currentRows(): array
    {
        if ($this->group === null) {
            throw new \OutOfBoundsException('SQLite VDBE sorter DISTINCT group cursor is at EOF');
        }

        return $this->group['rows'];
    }

    public function currentDistinct(): SQLiteVdbeAggregateDistinctCursor
    {
        if ($this->group === null) {
            throw new \OutOfBoundsException('SQLite VDBE sorter DISTINCT group cursor is at EOF');
        }

        return $this->group['distinct'];
    }

    /**
     * @return list<mixed>
     */
    public function currentDistinctValues(): array
    {
        return $this->currentDistinct()->values();
    }

    /**
     * @return list<array{key:list<mixed>,rowCount:int,distinctValues:list<mixed>}>
     */
    public function drainSummaries(): array
    {
        $summaries = [];
        while (!$this->eof()) {
            $summaries[] = [
                'key' => $this->currentGroupKey(),
                'rowCount' => count($this->currentRows()),
                'distinctValues' => $this->currentDistinctValues(),
            ];
            $this->next();
        }

        return $summaries;
    }

    /**
     * @return array{groupKey:list<mixed>,rowCount:int,distinctRows:int,filtered:bool,eof:bool}
     */
    public function currentSummary(): array
    {
        if ($this->group === null) {
            throw new \OutOfBoundsException('SQLite VDBE sorter DISTINCT group cursor is at EOF');
        }

        $summary = $this->group['distinct']->summary(count($this->group['rows']));

        return [
            'groupKey' => $this->group['key'],
            'rowCount' => count($this->group['rows']),
            'distinctRows' => $summary['distinctRows'],
            'filtered' => $summary['filtered'],
            'eof' => $summary['eof'],
        ];
    }

    private function loadCurrentGroup(): void
    {
        if ($this->cursor->eof()) {
            $this->group = null;

            return;
        }

        $key = $this->cursor->currentRecord($this->groupColumns);
        $rows = [];
        while (!$this->cursor->eof()) {
            $currentKey = $this->cursor->currentRecord($this->groupColumns);
            if (SQLiteVdbeSortCompare::compareRecords(
                $key,
                $currentKey,
                $this->groupAffinities,
                $this->groupCollations,
                $this->groupDescending,
                $this->groupNulls
            ) !== 0) {
                break;
            }

            $rows[] = $this->cursor->current();
            $this->cursor->next();
        }

        $this->group = [
            'key' => $key,
            'rows' => $rows,
            'distinct' => new SQLiteVdbeAggregateDistinctCursor(
                $rows,
                $this->distinctColumns,
                $this->valueColumn,
                $this->filterColumn,
                $this->distinctAffinities,
                $this->distinctCollations
            ),
        ];
    }

    /**
     * @param list<string> $columns
     */
    private static function assertColumnList(array $columns, string $label): void
    {
        if ($columns === [] || !array_is_list($columns)) {
            throw new \InvalidArgumentException("SQLite VDBE sorter DISTINCT {$label} columns must be a non-empty list");
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException("SQLite VDBE sorter DISTINCT {$label} columns must be non-empty strings");
            }
        }
    }
}
