<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUpsertDoUpdateWherePlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param callable(array<string,mixed>,array<string,mixed>):bool|null $where
     * @return array{before:list<array<string,mixed>>,after:list<array<string,mixed>>,inserted_rows:list<array<string,mixed>>,updated_rows:list<array<string,mixed>>,skipped_rows:list<array<string,mixed>>,changes:int}
     */
    public static function execute(
        array $rows,
        array $incomingRows,
        array $uniqueColumns,
        array $assignments,
        ?callable $where = null,
    ): array {
        self::validateRows($rows, 'target');
        self::validateRows($incomingRows, 'incoming');
        self::validateUniqueColumns($uniqueColumns);
        self::validateAssignments($assignments);

        $before = $rows;
        $inserted = [];
        $updated = [];
        $skipped = [];
        $changes = 0;

        foreach ($incomingRows as $incoming) {
            self::ensureColumns($incoming, $uniqueColumns, 'incoming');
            $conflictIndex = self::findConflictIndex($rows, $incoming, $uniqueColumns);
            if ($conflictIndex === null) {
                $rows[] = $incoming;
                $inserted[] = $incoming;
                ++$changes;
                continue;
            }

            $current = $rows[$conflictIndex];
            self::ensureColumns($current, $uniqueColumns, 'target');
            if ($where !== null && !$where($current, $incoming)) {
                $skipped[] = $incoming;
                continue;
            }

            $updatedRow = $current;
            foreach ($assignments as $column => $assignment) {
                $updatedRow[$column] = $assignment($current, $incoming);
            }

            $otherRows = $rows;
            unset($otherRows[$conflictIndex]);
            foreach (array_values($otherRows) as $other) {
                if (self::rowsConflict($other, $updatedRow, $uniqueColumns)) {
                    throw new \InvalidArgumentException('SQLite UPSERT DO UPDATE produced a unique constraint conflict');
                }
            }

            $rows[$conflictIndex] = $updatedRow;
            $updated[] = $updatedRow;
            ++$changes;
        }

        return [
            'before' => $before,
            'after' => array_values($rows),
            'inserted_rows' => $inserted,
            'updated_rows' => $updated,
            'skipped_rows' => $skipped,
            'changes' => $changes,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $uniqueColumns
     */
    private static function findConflictIndex(array $rows, array $incoming, array $uniqueColumns): ?int
    {
        foreach ($rows as $index => $row) {
            if (self::rowsConflict($row, $incoming, $uniqueColumns)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<string> $uniqueColumns
     */
    private static function rowsConflict(array $left, array $right, array $uniqueColumns): bool
    {
        foreach ($uniqueColumns as $column) {
            if (!array_key_exists($column, $left) || !array_key_exists($column, $right)) {
                throw new \InvalidArgumentException("SQLite UPSERT unique column {$column} is missing from a row");
            }
            if ($left[$column] === null || $right[$column] === null || $left[$column] != $right[$column]) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function validateRows(array $rows, string $label): void
    {
        if (!array_is_list($rows)) {
            throw new \InvalidArgumentException("SQLite UPSERT {$label} rows must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite UPSERT {$label} row must be an array");
            }
        }
    }

    /**
     * @param list<string> $uniqueColumns
     */
    private static function validateUniqueColumns(array $uniqueColumns): void
    {
        if ($uniqueColumns === [] || !array_is_list($uniqueColumns)) {
            throw new \InvalidArgumentException('SQLite UPSERT unique columns must be a non-empty list');
        }
        foreach ($uniqueColumns as $column) {
            if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $column)) {
                throw new \InvalidArgumentException('SQLite UPSERT unique column name is malformed');
            }
        }
    }

    /**
     * @param array<string,mixed> $assignments
     */
    private static function validateAssignments(array $assignments): void
    {
        if ($assignments === []) {
            throw new \InvalidArgumentException('SQLite UPSERT DO UPDATE needs at least one assignment');
        }
        foreach ($assignments as $column => $assignment) {
            if (!is_string($column) || !preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $column)) {
                throw new \InvalidArgumentException('SQLite UPSERT assignment column name is malformed');
            }
            if (!is_callable($assignment)) {
                throw new \InvalidArgumentException('SQLite UPSERT assignment must be callable');
            }
        }
    }

    /**
     * @param list<string> $columns
     */
    private static function ensureColumns(array $row, array $columns, string $label): void
    {
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite UPSERT {$label} row is missing unique column {$column}");
            }
        }
    }
}
