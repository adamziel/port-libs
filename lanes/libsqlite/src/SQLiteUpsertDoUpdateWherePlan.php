<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUpsertDoUpdateWherePlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $uniqueColumns
     * @param list<list<string>>|null $uniqueConstraints
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param callable(array<string,mixed>,array<string,mixed>):bool|null $where
     * @return array{before:list<array<string,mixed>>,after:list<array<string,mixed>>,inserted_rows:list<array<string,mixed>>,updated_rows:list<array<string,mixed>>,skipped_rows:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,changes:int}
     */
    public static function execute(
        array $rows,
        array $incomingRows,
        array $uniqueColumns,
        array $assignments,
        ?callable $where = null,
        ?array $uniqueConstraints = null,
    ): array {
        self::validateRows($rows, 'target');
        self::validateRows($incomingRows, 'incoming');
        self::validateUniqueColumns($uniqueColumns);
        self::validateAssignments($assignments);
        $uniqueConstraints = self::normalizeUniqueConstraints($uniqueColumns, $uniqueConstraints);

        $before = $rows;
        $inserted = [];
        $updated = [];
        $skipped = [];
        $returning = [];
        $changes = 0;

        foreach ($incomingRows as $incoming) {
            self::ensureColumns($incoming, $uniqueColumns, 'incoming');
            $conflictIndex = self::findConflictIndex($rows, $incoming, $uniqueColumns);
            if ($conflictIndex === null) {
                self::ensureNoUniqueConflict($rows, $incoming, $uniqueConstraints, null, 'insert');
                $rows[] = $incoming;
                $inserted[] = $incoming;
                $returning[] = $incoming;
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
            self::ensureNoUniqueConflict(array_values($otherRows), $updatedRow, $uniqueConstraints, null, 'update');

            $rows[$conflictIndex] = $updatedRow;
            $updated[] = $updatedRow;
            $returning[] = $updatedRow;
            ++$changes;
        }

        return [
            'before' => $before,
            'after' => array_values($rows),
            'inserted_rows' => $inserted,
            'updated_rows' => $updated,
            'skipped_rows' => $skipped,
            'returning_rows' => $returning,
            'changes' => $changes,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string|callable(array<string,mixed>):mixed>|array<string,string|callable(array<string,mixed>):mixed>|null $projection
     * @return list<array<string,mixed>>
     */
    public static function returningRows(array $rows, ?array $projection = null): array
    {
        self::validateRows($rows, 'returning');

        if ($projection === null) {
            return array_map(static fn (array $row): array => $row, $rows);
        }

        $projected = [];
        foreach ($rows as $row) {
            $output = [];
            foreach ($projection as $alias => $expression) {
                if ($expression === '*') {
                    foreach ($row as $column => $value) {
                        $output[$column] = $value;
                    }
                    continue;
                }

                if (is_int($alias)) {
                    if (!is_string($expression) || $expression === '') {
                        throw new \InvalidArgumentException('SQLite UPSERT RETURNING projection columns must be non-empty strings');
                    }
                    if (!array_key_exists($expression, $row)) {
                        throw new \InvalidArgumentException("SQLite UPSERT RETURNING projection column {$expression} is missing");
                    }
                    $output[$expression] = $row[$expression];
                    continue;
                }

                if (!is_string($alias) || $alias === '') {
                    throw new \InvalidArgumentException('SQLite UPSERT RETURNING projection aliases must be non-empty strings');
                }
                if (is_string($expression)) {
                    if ($expression === '') {
                        throw new \InvalidArgumentException('SQLite UPSERT RETURNING projection columns must be non-empty strings');
                    }
                    if (!array_key_exists($expression, $row)) {
                        throw new \InvalidArgumentException("SQLite UPSERT RETURNING projection column {$expression} is missing");
                    }
                    $output[$alias] = $row[$expression];
                    continue;
                }
                if (is_callable($expression)) {
                    $output[$alias] = $expression($row);
                    continue;
                }

                throw new \InvalidArgumentException('SQLite UPSERT RETURNING projection expressions must be column names or callables');
            }
            $projected[] = $output;
        }

        return $projected;
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
     * @param list<string> $conflictTarget
     * @param list<list<string>>|null $uniqueConstraints
     * @return list<list<string>>
     */
    private static function normalizeUniqueConstraints(array $conflictTarget, ?array $uniqueConstraints): array
    {
        if ($uniqueConstraints === null) {
            return [$conflictTarget];
        }
        if ($uniqueConstraints === [] || !array_is_list($uniqueConstraints)) {
            throw new \InvalidArgumentException('SQLite UPSERT unique constraints must be a non-empty list');
        }

        $normalized = [];
        foreach ($uniqueConstraints as $constraint) {
            if (!is_array($constraint)) {
                throw new \InvalidArgumentException('SQLite UPSERT unique constraint must be a column list');
            }
            self::validateUniqueColumns($constraint);
            $normalized[] = $constraint;
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<list<string>> $uniqueConstraints
     */
    private static function ensureNoUniqueConflict(array $rows, array $candidate, array $uniqueConstraints, ?int $ignoreIndex, string $operation): void
    {
        foreach ($uniqueConstraints as $columns) {
            foreach ($rows as $index => $row) {
                if ($ignoreIndex !== null && $index === $ignoreIndex) {
                    continue;
                }
                if (self::rowsConflict($row, $candidate, $columns)) {
                    throw new \InvalidArgumentException("SQLite UPSERT {$operation} produced a unique constraint conflict");
                }
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
