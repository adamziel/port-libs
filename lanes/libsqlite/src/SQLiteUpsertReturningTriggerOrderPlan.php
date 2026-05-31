<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteUpsertReturningTriggerOrderPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param null|callable(array<string,mixed>,array<string,mixed>):bool $where
     * @param list<string> $returning
     * @return array{before:list<array<string,mixed>>,after:list<array<string,mixed>>,inserted:list<array<string,mixed>>,updated:list<array<string,mixed>>,skipped:list<array<string,mixed>>,returning:list<array<string,mixed>>,audit:list<array<string,mixed>>,changes:int}
     */
    public static function execute(
        array $rows,
        array $incomingRows,
        array $uniqueColumns,
        array $assignments,
        ?callable $where,
        array $returning,
    ): array {
        self::assertList($rows, 'base rows');
        self::assertList($incomingRows, 'incoming rows');
        self::assertIdentifiers($uniqueColumns, 'unique column');
        self::assertIdentifiers($returning, 'RETURNING column');
        if ($uniqueColumns === []) {
            throw new InvalidArgumentException('SQLite UPSERT trigger order unique columns cannot be empty');
        }

        $after = array_values($rows);
        $before = $after;
        $inserted = [];
        $updated = [];
        $skipped = [];
        $returningRows = [];
        $audit = [];

        foreach ($incomingRows as $ordinal => $incoming) {
            if (!is_array($incoming)) {
                throw new InvalidArgumentException('SQLite UPSERT trigger order incoming row must be an array');
            }
            $audit[] = self::audit('before-insert', $ordinal, null, $incoming);
            $conflictIndex = self::conflictIndex($after, $incoming, $uniqueColumns);
            if ($conflictIndex === null) {
                $after[] = $incoming;
                $inserted[] = $incoming;
                $audit[] = self::audit('after-insert', $ordinal, null, $incoming);
                $returningRows[] = self::project($incoming, $returning);
                continue;
            }

            $old = $after[$conflictIndex];
            if ($where !== null && !$where($old, $incoming)) {
                $skipped[] = $incoming;
                continue;
            }

            $new = $old;
            foreach ($assignments as $column => $assignment) {
                self::identifier((string) $column, 'assignment column');
                $new[(string) $column] = $assignment($old, $incoming);
            }

            $audit[] = self::audit('before-update', $ordinal, $old, $new);
            $after[$conflictIndex] = $new;
            $updated[] = $new;
            $audit[] = self::audit('after-update', $ordinal, $old, $new);
            $returningRows[] = self::project($new, $returning);
        }

        return [
            'before' => $before,
            'after' => array_values($after),
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'returning' => $returningRows,
            'audit' => $audit,
            'changes' => count($inserted) + count($updated),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $uniqueColumns
     */
    private static function conflictIndex(array $rows, array $incoming, array $uniqueColumns): ?int
    {
        foreach ($rows as $index => $row) {
            foreach ($uniqueColumns as $column) {
                if (!array_key_exists($column, $row) || !array_key_exists($column, $incoming)) {
                    throw new InvalidArgumentException("SQLite UPSERT trigger order unique column {$column} is missing");
                }
                if ($row[$column] === null || $incoming[$column] === null || $row[$column] != $incoming[$column]) {
                    continue 2;
                }
            }

            return $index;
        }

        return null;
    }

    /**
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private static function project(array $row, array $columns): array
    {
        $projected = [];
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                throw new InvalidArgumentException("SQLite UPSERT trigger order RETURNING column {$column} is missing");
            }
            $projected[$column] = $row[$column];
        }

        return $projected;
    }

    /** @return array{phase:string,ordinal:int,old:array<string,mixed>|null,new:array<string,mixed>} */
    private static function audit(string $phase, int $ordinal, ?array $old, array $new): array
    {
        return [
            'phase' => $phase,
            'ordinal' => $ordinal,
            'old' => $old,
            'new' => $new,
        ];
    }

    private static function assertList(array $rows, string $label): void
    {
        if (!array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite UPSERT trigger order {$label} must be a list");
        }
    }

    /** @param list<string> $identifiers */
    private static function assertIdentifiers(array $identifiers, string $label): void
    {
        if (!array_is_list($identifiers)) {
            throw new InvalidArgumentException("SQLite UPSERT trigger order {$label} list is malformed");
        }
        foreach ($identifiers as $identifier) {
            self::identifier($identifier, $label);
        }
    }

    private static function identifier(string $identifier, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidArgumentException("SQLite UPSERT trigger order {$label} is malformed");
        }

        return $identifier;
    }
}
