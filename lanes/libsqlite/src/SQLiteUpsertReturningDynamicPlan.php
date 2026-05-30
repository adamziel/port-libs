<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteUpsertReturningDynamicPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $columns
     * @param list<string> $conflictTarget
     * @param array<string,mixed> $defaults
     * @param array<string,string|int|float|null> $updateAssignments
     * @param list<string>|string $returning
     * @param null|callable(array<string,mixed>):bool $partialIndex
     * @param list<list<string>> $additionalConflictTargets
     * @return array{before:list<array<string,mixed>>,after:list<array<string,mixed>>,inserted_rows:list<array<string,mixed>>,updated_rows:list<array<string,mixed>>,skipped_rows:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,decisions:list<array<string,mixed>>,changes:int}
     */
    public static function execute(
        array $rows,
        array $incomingRows,
        array $columns,
        array $conflictTarget,
        array $defaults = [],
        array $updateAssignments = [],
        array|string $returning = '*',
        ?callable $partialIndex = null,
        bool $doNothing = false,
        string $rowidColumn = 'id',
        array $additionalConflictTargets = [],
    ): array {
        self::assertList($rows, 'base rows');
        self::assertList($incomingRows, 'incoming rows');
        self::assertIdentifiers($columns, 'columns');
        self::assertIdentifiers($conflictTarget, 'conflict target');
        if ($conflictTarget === []) {
            throw new InvalidArgumentException('SQLite UPSERT conflict target cannot be empty');
        }
        foreach ($conflictTarget as $column) {
            if (!in_array($column, $columns, true)) {
                throw new InvalidArgumentException("SQLite UPSERT conflict target column {$column} is not in the table");
            }
        }
        foreach ($additionalConflictTargets as $additionalTarget) {
            self::assertIdentifiers($additionalTarget, 'additional conflict target');
            if ($additionalTarget === []) {
                throw new InvalidArgumentException('SQLite UPSERT additional conflict target cannot be empty');
            }
            foreach ($additionalTarget as $column) {
                if (!in_array($column, $columns, true)) {
                    throw new InvalidArgumentException("SQLite UPSERT additional conflict target column {$column} is not in the table");
                }
            }
        }
        self::assertIdentifier($rowidColumn, 'rowid column');

        $projection = self::returningProjection($returning);
        $after = array_map(static fn (array $row): array => self::completeRow($row, $columns, $defaults), $rows);
        $before = $after;
        $inserted = [];
        $updated = [];
        $skipped = [];
        $returningRows = [];
        $decisions = [];
        $nextRowid = self::nextRowid($after, $rowidColumn);

        foreach ($incomingRows as $incomingIndex => $incoming) {
            if (!is_array($incoming)) {
                throw new InvalidArgumentException('SQLite UPSERT incoming row must be an array');
            }

            $candidate = self::completeRow($incoming, $columns, $defaults);
            if (($candidate[$rowidColumn] ?? null) === null && in_array($rowidColumn, $columns, true)) {
                $candidate[$rowidColumn] = $nextRowid++;
            }

            $matchedTarget = null;
            $conflictIndex = self::conflictIndex($after, $candidate, $conflictTarget, $partialIndex);
            if ($conflictIndex !== null) {
                $matchedTarget = $conflictTarget;
            } elseif ($doNothing) {
                foreach ($additionalConflictTargets as $additionalTarget) {
                    $conflictIndex = self::conflictIndex($after, $candidate, $additionalTarget, null);
                    if ($conflictIndex !== null) {
                        $matchedTarget = $additionalTarget;
                        break;
                    }
                }
            }
            $sequence = $incomingIndex + 1;
            if ($conflictIndex === null) {
                $after[] = $candidate;
                $inserted[] = $candidate;
                $row = self::projectReturning($projection, $candidate, null, 'insert', $sequence);
                $returningRows[] = $row;
                $decisions[] = self::decision($sequence, 'insert', $candidate, null, $conflictTarget);
                continue;
            }

            $old = $after[$conflictIndex];
            if ($doNothing) {
                $skipped[] = $candidate;
                $decisions[] = self::decision($sequence, 'skip', $candidate, $old, $matchedTarget ?? $conflictTarget);
                continue;
            }

            if ($updateAssignments === []) {
                throw new InvalidArgumentException('SQLite UPSERT DO UPDATE assignments cannot be empty');
            }

            $new = $old;
            foreach ($updateAssignments as $column => $source) {
                self::assertIdentifier((string) $column, 'assignment column');
                if (!in_array((string) $column, $columns, true)) {
                    throw new InvalidArgumentException("SQLite UPSERT assignment column {$column} is not in the table");
                }
                $new[(string) $column] = self::assignmentValue($source, $old, $candidate);
            }

            $after[$conflictIndex] = $new;
            $updated[] = $new;
            $row = self::projectReturning($projection, $new, $old, 'update', $sequence);
            $returningRows[] = $row;
            $decisions[] = self::decision($sequence, 'update', $candidate, $old, $conflictTarget);
        }

        return [
            'before' => $before,
            'after' => array_values($after),
            'inserted_rows' => $inserted,
            'updated_rows' => $updated,
            'skipped_rows' => $skipped,
            'returning_rows' => $returningRows,
            'decisions' => $decisions,
            'changes' => count($inserted) + count($updated),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $conflictTarget
     */
    private static function conflictIndex(array $rows, array $candidate, array $conflictTarget, ?callable $partialIndex): ?int
    {
        if ($partialIndex !== null && !$partialIndex($candidate)) {
            return null;
        }

        foreach ($rows as $index => $row) {
            if ($partialIndex !== null && !$partialIndex($row)) {
                continue;
            }
            $matches = true;
            foreach ($conflictTarget as $column) {
                if (($candidate[$column] ?? null) === null || ($row[$column] ?? null) === null || $candidate[$column] !== $row[$column]) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                return $index;
            }
        }

        return null;
    }

    /** @param list<string> $columns @param array<string,mixed> $defaults */
    private static function completeRow(array $row, array $columns, array $defaults): array
    {
        $out = [];
        foreach ($columns as $column) {
            $out[$column] = array_key_exists($column, $row) ? $row[$column] : ($defaults[$column] ?? null);
        }

        return $out;
    }

    /** @param list<array<string,mixed>> $rows */
    private static function nextRowid(array $rows, string $rowidColumn): int
    {
        $max = 0;
        foreach ($rows as $row) {
            if (isset($row[$rowidColumn]) && is_int($row[$rowidColumn]) && $row[$rowidColumn] > $max) {
                $max = $row[$rowidColumn];
            }
        }

        return $max + 1;
    }

    private static function assignmentValue(mixed $source, array $old, array $candidate): mixed
    {
        if (is_string($source)) {
            if (str_starts_with($source, 'excluded.')) {
                $column = substr($source, 9);
                self::assertIdentifier($column, 'excluded column');
                if (!array_key_exists($column, $candidate)) {
                    throw new InvalidArgumentException("SQLite UPSERT excluded column {$column} is missing");
                }

                return $candidate[$column];
            }
            if (str_starts_with($source, 'old.')) {
                $column = substr($source, 4);
                self::assertIdentifier($column, 'old column');
                if (!array_key_exists($column, $old)) {
                    throw new InvalidArgumentException("SQLite UPSERT old column {$column} is missing");
                }

                return $old[$column];
            }
        }

        return $source;
    }

    /** @return list<string> */
    private static function returningProjection(array|string $returning): array
    {
        if ($returning === '*') {
            return ['*'];
        }
        if (!is_array($returning) || !array_is_list($returning) || $returning === []) {
            throw new InvalidArgumentException('SQLite RETURNING projection must be * or a non-empty list');
        }
        foreach ($returning as $column) {
            self::assertIdentifier($column, 'RETURNING column');
        }

        return $returning;
    }

    /** @param list<string> $projection */
    private static function projectReturning(array $projection, array $row, ?array $old, string $action, int $sequence): array
    {
        $out = [];
        if ($projection === ['*']) {
            $out = $row;
        } else {
            foreach ($projection as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new InvalidArgumentException("SQLite RETURNING column {$column} is missing");
                }
                $out[$column] = $row[$column];
            }
        }

        $out['_upsert_action'] = $action;
        $out['_statement_sequence'] = $sequence;
        if ($old !== null) {
            $out['_old'] = $old;
        }

        return $out;
    }

    /** @param list<string> $conflictTarget */
    private static function decision(int $sequence, string $action, array $candidate, ?array $old, array $conflictTarget): array
    {
        return [
            'sequence' => $sequence,
            'action' => $action,
            'candidate_key' => array_intersect_key($candidate, array_flip($conflictTarget)),
            'conflict_key' => $old === null ? null : array_intersect_key($old, array_flip($conflictTarget)),
        ];
    }

    /** @param list<mixed> $rows */
    private static function assertList(array $rows, string $label): void
    {
        if (!array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite UPSERT {$label} must be a list");
        }
    }

    /** @param list<string> $identifiers */
    private static function assertIdentifiers(array $identifiers, string $label): void
    {
        foreach ($identifiers as $identifier) {
            self::assertIdentifier($identifier, $label);
        }
    }

    private static function assertIdentifier(mixed $identifier, string $label): void
    {
        if (!is_string($identifier) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidArgumentException("SQLite UPSERT invalid {$label}");
        }
    }
}
