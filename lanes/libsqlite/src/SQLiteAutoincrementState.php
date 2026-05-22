<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAutoincrementState
{
    private const MAX_ROWID = PHP_INT_MAX;

    private int $counter;
    private ?int $largestTableRowId;
    private ?int $sequenceRowId;
    private int $largestSequenceRowId;
    private bool $sequenceRowCreated = false;
    private bool $sequenceDirty = false;

    private function __construct(
        public readonly string $tableName,
        public readonly ?SQLiteSequenceRecord $initialSequenceRecord,
        ?int $largestTableRowId,
        int $largestSequenceRowId,
    ) {
        $this->counter = $initialSequenceRecord?->autoincrementCounter() ?? 0;
        $this->largestTableRowId = $largestTableRowId;
        $this->sequenceRowId = $initialSequenceRecord?->rowId;
        $this->largestSequenceRowId = $largestSequenceRowId;
    }

    public static function fromDatabaseState(
        string $tableName,
        ?SQLiteSequenceRecord $sequenceRecord,
        ?int $largestTableRowId,
        int $largestSequenceRowId,
    ): self {
        return new self($tableName, $sequenceRecord, $largestTableRowId, $largestSequenceRowId);
    }

    public function peekNextRowId(): int
    {
        return $this->nextRowIdCandidate();
    }

    public function allocateRowId(): int
    {
        $rowId = $this->nextRowIdCandidate();
        $this->recordInsertedRowId($rowId);

        return $rowId;
    }

    public function recordInsertedRowId(int $rowId): void
    {
        if ($rowId > $this->counter) {
            $this->counter = $rowId;
            $this->touchSequenceRow();
        }
        if ($this->largestTableRowId === null || $rowId > $this->largestTableRowId) {
            $this->largestTableRowId = $rowId;
        }
    }

    public function currentSequenceRecord(): ?SQLiteSequenceRecord
    {
        if ($this->sequenceRowId === null) {
            return null;
        }

        return new SQLiteSequenceRecord($this->tableName, $this->counter, $this->sequenceRowId);
    }

    public function currentCounter(): int
    {
        return $this->counter;
    }

    public function largestTableRowId(): ?int
    {
        return $this->largestTableRowId;
    }

    public function sequenceRowCreated(): bool
    {
        return $this->sequenceRowCreated;
    }

    public function sequenceDirty(): bool
    {
        return $this->sequenceDirty;
    }

    /**
     * @return array{
     *   table:string,
     *   largestTableRowId:int|null,
     *   currentCounter:int,
     *   nextRowId:int|null,
     *   sequenceRowCreated:bool,
     *   sequenceDirty:bool,
     *   initialSequence:array{name:string|int|float|null,seq:string|int|float|null,rowid:int}|null,
     *   currentSequence:array{name:string|int|float|null,seq:string|int|float|null,rowid:int}|null
     * }
     */
    public function toArray(): array
    {
        return [
            'table' => $this->tableName,
            'largestTableRowId' => $this->largestTableRowId,
            'currentCounter' => $this->counter,
            'nextRowId' => $this->canAllocateRowId() ? $this->nextRowIdCandidate() : null,
            'sequenceRowCreated' => $this->sequenceRowCreated,
            'sequenceDirty' => $this->sequenceDirty,
            'initialSequence' => $this->initialSequenceRecord?->toArray(),
            'currentSequence' => $this->currentSequenceRecord()?->toArray(),
        ];
    }

    private function nextRowIdCandidate(): int
    {
        if (!$this->canAllocateRowId()) {
            throw new \OverflowException('SQLite AUTOINCREMENT sequence is at the maximum rowid');
        }

        $candidate = $this->largestTableRowId === null ? 1 : $this->largestTableRowId + 1;
        $minimum = max(1, $this->counter + 1);
        if ($candidate < $minimum) {
            $candidate = $minimum;
        }

        return $candidate;
    }

    private function canAllocateRowId(): bool
    {
        return $this->counter < self::MAX_ROWID
            && ($this->largestTableRowId === null || $this->largestTableRowId < self::MAX_ROWID);
    }

    private function touchSequenceRow(): void
    {
        if ($this->sequenceRowId === null) {
            $this->largestSequenceRowId++;
            $this->sequenceRowId = $this->largestSequenceRowId;
            $this->sequenceRowCreated = true;
        }

        $this->sequenceDirty = true;
    }
}
