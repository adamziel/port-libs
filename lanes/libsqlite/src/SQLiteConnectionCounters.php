<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteConnectionCounters
{
    private int $lastInsertRowId;
    private int $changes;
    private int $totalChanges;

    public function __construct(int $lastInsertRowId = 0, int $changes = 0, int $totalChanges = 0)
    {
        $this->assertNonNegative('last_insert_rowid', $lastInsertRowId);
        $this->assertNonNegative('changes', $changes);
        $this->assertNonNegative('total_changes', $totalChanges);
        if ($changes > $totalChanges) {
            throw new \InvalidArgumentException('SQLite changes counter cannot exceed total_changes');
        }

        $this->lastInsertRowId = $lastInsertRowId;
        $this->changes = $changes;
        $this->totalChanges = $totalChanges;
    }

    public static function initial(): self
    {
        return new self();
    }

    public function recordInsert(int $rowId, int $changedRows = 1): void
    {
        $this->assertPositiveRowId($rowId);
        $this->recordChangeCount($changedRows);
        if ($changedRows > 0) {
            $this->lastInsertRowId = $rowId;
        }
    }

    public function recordUpdate(int $changedRows): void
    {
        $this->recordChangeCount($changedRows);
    }

    public function recordDelete(int $changedRows): void
    {
        $this->recordChangeCount($changedRows);
    }

    public function recordNoOp(): void
    {
        $this->changes = 0;
    }

    public function restoreAfterRollback(self $snapshot): void
    {
        $this->lastInsertRowId = $snapshot->lastInsertRowId;
        $this->changes = $snapshot->changes;
        $this->totalChanges = $snapshot->totalChanges;
    }

    /**
     * @return array{status:string,before:array{last_insert_rowid:int,changes:int,total_changes:int},snapshot:array{last_insert_rowid:int,changes:int,total_changes:int},after:array{last_insert_rowid:int,changes:int,total_changes:int},preserved_current_changes:bool,preserved_last_insert_rowid:bool,preserved_total_changes:bool,snapshot_changes_reused:bool}
     */
    public function preserveAfterSavepointRollback(self $snapshot): array
    {
        $before = $this->toArray();
        $after = $this->toArray();

        return [
            'status' => 'savepoint-rollback-counters-preserved',
            'before' => $before,
            'snapshot' => $snapshot->toArray(),
            'after' => $after,
            'preserved_current_changes' => $after['changes'] === $before['changes'],
            'preserved_last_insert_rowid' => $after['last_insert_rowid'] === $before['last_insert_rowid'],
            'preserved_total_changes' => $after['total_changes'] === $before['total_changes'],
            'snapshot_changes_reused' => $after['changes'] === $snapshot->changes,
        ];
    }

    public function snapshot(): self
    {
        return new self($this->lastInsertRowId, $this->changes, $this->totalChanges);
    }

    public function lastInsertRowId(): int
    {
        return $this->lastInsertRowId;
    }

    public function changes(): int
    {
        return $this->changes;
    }

    public function totalChanges(): int
    {
        return $this->totalChanges;
    }

    /**
     * @param list<mixed> $arguments
     */
    public function sqlFunctionArguments(string $functionName, array $arguments): int
    {
        if ($arguments !== []) {
            throw new \InvalidArgumentException("SQLite {$functionName}() expects no arguments");
        }

        return match (strtolower($functionName)) {
            'last_insert_rowid' => $this->lastInsertRowId(),
            'changes' => $this->changes(),
            'total_changes' => $this->totalChanges(),
            default => throw new \InvalidArgumentException("Unsupported SQLite connection counter function: {$functionName}"),
        };
    }

    /**
     * @return array{last_insert_rowid:int,changes:int,total_changes:int}
     */
    public function toArray(): array
    {
        return [
            'last_insert_rowid' => $this->lastInsertRowId,
            'changes' => $this->changes,
            'total_changes' => $this->totalChanges,
        ];
    }

    private function recordChangeCount(int $changedRows): void
    {
        $this->assertNonNegative('changed rows', $changedRows);
        $this->changes = $changedRows;
        $this->totalChanges += $changedRows;
    }

    private function assertPositiveRowId(int $rowId): void
    {
        if ($rowId <= 0) {
            throw new \InvalidArgumentException('SQLite rowid must be positive for last_insert_rowid() tracking');
        }
    }

    private function assertNonNegative(string $name, int $value): void
    {
        if ($value < 0) {
            throw new \InvalidArgumentException("SQLite {$name} counter cannot be negative");
        }
    }
}
