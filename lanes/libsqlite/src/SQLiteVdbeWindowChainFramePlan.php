<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVdbeWindowChainFramePlan
{
    /** @var array<string,SQLiteVdbeWindowAggregateCursor> */
    private array $windows = [];

    /**
     * @param array<string,SQLiteVdbeWindowAggregateCursor> $windows
     */
    public function __construct(array $windows)
    {
        if ($windows === []) {
            throw new \InvalidArgumentException('SQLite VDBE window chain requires at least one window cursor');
        }
        foreach ($windows as $name => $cursor) {
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('SQLite VDBE window chain names must be non-empty strings');
            }
            if (!$cursor instanceof SQLiteVdbeWindowAggregateCursor) {
                throw new \InvalidArgumentException('SQLite VDBE window chain entries must be window aggregate cursors');
            }
            $this->windows[$name] = $cursor;
        }
    }

    /**
     * @return array<string,array{current:array<string,mixed>,next:array<string,mixed>|null,advanced:bool,frameRowids:list<mixed>,nextFrameRowids:list<mixed>|null,total:float,groupConcat:?string,firstValue:mixed,lastValue:mixed,nthValue:mixed}>
     */
    public function currentNext(string $rowidColumn = 'rowid', mixed $separator = '|', int $nth = 2): array
    {
        if ($nth <= 0) {
            throw new \InvalidArgumentException('SQLite VDBE window chain nth value index must be positive');
        }

        $result = [];
        foreach ($this->windows as $name => $cursor) {
            $before = $cursor->currentRow();
            $summary = $cursor->currentNextSummary();
            $frames = $cursor->currentNextFrameRows(true);
            $after = $cursor->currentRow();
            if ($before !== $after) {
                throw new \RuntimeException('SQLite VDBE window chain cursor advanced during current/next peek');
            }

            $result[$name] = [
                'current' => $summary['current'],
                'next' => $summary['next'],
                'advanced' => $summary['advanced'],
                'frameRowids' => self::rowids($frames['current'], $rowidColumn),
                'nextFrameRowids' => $frames['next'] === null ? null : self::rowids($frames['next'], $rowidColumn),
                'total' => $cursor->total(),
                'groupConcat' => $cursor->groupConcat($separator),
                'firstValue' => $cursor->firstValue(true),
                'lastValue' => $cursor->lastValue(true),
                'nthValue' => $cursor->nthValue($nth, true),
            ];
        }

        return $result;
    }

    public function next(): void
    {
        foreach ($this->windows as $cursor) {
            $cursor->next();
        }
    }

    public function rewind(): void
    {
        foreach ($this->windows as $cursor) {
            $cursor->rewind();
        }
    }

    public function eof(): bool
    {
        foreach ($this->windows as $cursor) {
            if (!$cursor->eof()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array<string,array{current:array<string,mixed>,next:array<string,mixed>|null,advanced:bool,frameRowids:list<mixed>,nextFrameRowids:list<mixed>|null,total:float,groupConcat:?string,firstValue:mixed,lastValue:mixed,nthValue:mixed}>>
     */
    public function drain(string $rowidColumn = 'rowid', mixed $separator = '|', int $nth = 2): array
    {
        $rows = [];
        while (!$this->eof()) {
            $rows[] = $this->currentNext($rowidColumn, $separator, $nth);
            $this->next();
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function rowids(array $rows, string $rowidColumn): array
    {
        return array_map(static fn (array $row): mixed => $row[$rowidColumn] ?? null, $rows);
    }
}
