<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRecursiveTriggerReturningSavepointPlan
{
    /**
     * @param list<array<string,mixed>> $savepointRows
     * @param list<array<string,mixed>> $inputRows
     * @param list<array<string,mixed>> $triggers
     * @param list<string> $uniqueColumns
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,int,array<string,mixed>):mixed>|null $returning
     * @param array{recursive_triggers?:bool,max_depth?:int} $options
     * @return array{savepoint:string,rows:list<array<string,mixed>>,current_rows:list<array<string,mixed>>,attempted_rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,ignored:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,attempted_yields:list<array<string,mixed>>,effects:list<array<string,mixed>>,changes:int,conflict_action:string,recursive_triggers:bool,max_depth:int,rolled_back:bool,aborted:bool,rollback_scope:string,rollback_reason:?string,savepoint_preserved:bool,discarded:list<array<string,mixed>>,next_rowid:int,dependencies:list<string>}
     */
    public static function insertRows(
        string $savepoint,
        array $savepointRows,
        array $inputRows,
        array $triggers,
        array $uniqueColumns,
        string $conflictAction = 'rollback',
        ?array $returning = null,
        array $options = [],
    ): array {
        $plan = SQLiteRecursiveTriggerSavepointPlan::insertRows(
            $savepoint,
            $savepointRows,
            $inputRows,
            $triggers,
            $uniqueColumns,
            $conflictAction,
            $options
        );

        $returning ??= ['*'];
        $attemptedYields = self::attemptedYields($plan['effects'], $returning, $savepointRows);
        $returningRows = $plan['rolled_back'] ? [] : array_values(array_map(
            static fn (array $yield): array => $yield['returning'],
            array_values(array_filter($attemptedYields, static fn (array $yield): bool => $yield['status'] === 'changed'))
        ));

        return array_replace($plan, [
            'returning_rows' => $returningRows,
            'attempted_yields' => $attemptedYields,
            'next_rowid' => self::nextRowid($plan['rows']),
            'dependencies' => [
                'sqlite-recursive-trigger-conflict',
                'sqlite-savepoint-current-rollback',
                'sqlite-returning-recursive-yield-current-next50',
            ],
        ]);
    }

    /**
     * @param list<array<string,mixed>> $effects
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,int,array<string,mixed>):mixed> $returning
     * @param list<array<string,mixed>> $savepointRows
     * @return list<array<string,mixed>>
     */
    private static function attemptedYields(array $effects, array $returning, array $savepointRows): array
    {
        $yields = [];
        $nextRowid = self::nextRowid($savepointRows);
        foreach ($effects as $effect) {
            if (($effect['action'] ?? null) !== 'insert') {
                continue;
            }
            $row = $effect['row'] ?? null;
            if (!is_array($row)) {
                continue;
            }
            $result = (string) ($effect['result'] ?? '');
            $status = match ($result) {
                'inserted', 'replaced-conflict' => 'changed',
                'ignored-conflict' => 'ignored',
                'failed-conflict', 'aborted-conflict', 'rolled-back-conflict' => 'constraint-error',
                default => 'diagnostic',
            };
            $yields[] = [
                'ordinal' => count($yields),
                'depth' => (int) ($effect['depth'] ?? 0),
                'status' => $status,
                'event' => $result === 'replaced-conflict' ? 'replace' : 'insert',
                'conflict_action' => (string) ($effect['effective_conflict_action'] ?? ''),
                'current_rowid' => self::rowIdValue($row),
                'next_rowid' => $nextRowid,
                'row' => $status === 'constraint-error' ? null : $row,
                'returning' => $status === 'changed' ? self::projectReturning($row, count($yields), $effect, $returning) : null,
                'result' => $result,
            ];
            $rowid = self::rowIdValue($row);
            if (is_int($rowid) && $rowid >= $nextRowid) {
                $nextRowid = $rowid + 1;
            }
        }

        return $yields;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $effect
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,int,array<string,mixed>):mixed> $returning
     * @return array<string,mixed>
     */
    private static function projectReturning(array $row, int $ordinal, array $effect, array $returning): array
    {
        $projected = [];
        foreach ($returning as $index => $term) {
            if ($term === '*') {
                $projected['*'] = $row;
                continue;
            }
            if (is_callable($term)) {
                $projected['expr' . $index] = $term($row, $ordinal, $effect);
                continue;
            }
            if (is_array($term)) {
                $expr = (string) ($term['expr'] ?? '');
                $alias = (string) ($term['as'] ?? $expr);
                if ($alias === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias) !== 1) {
                    throw new \InvalidArgumentException('SQLite recursive RETURNING alias is malformed');
                }
                $projected[$alias] = self::value($row, $effect, $expr);
                continue;
            }
            if (!is_string($term) || $term === '') {
                throw new \InvalidArgumentException('SQLite recursive RETURNING term is malformed');
            }
            $projected[$term] = self::value($row, $effect, $term);
        }

        return $projected;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $effect
     */
    private static function value(array $row, array $effect, string $expr): mixed
    {
        if (str_starts_with($expr, 'new.')) {
            $expr = substr($expr, 4);
        }
        if ($expr === 'depth') {
            return (int) ($effect['depth'] ?? 0);
        }
        if (!array_key_exists($expr, $row)) {
            throw new \InvalidArgumentException("SQLite recursive RETURNING column {$expr} is missing");
        }

        return $row[$expr];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function nextRowid(array $rows): int
    {
        $max = 0;
        foreach ($rows as $row) {
            $rowid = self::rowIdValue($row);
            if (is_int($rowid) && $rowid > $max) {
                $max = $rowid;
            }
        }

        return $max + 1;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowIdValue(array $row): mixed
    {
        if (array_key_exists('setting_id', $row)) {
            return $row['setting_id'];
        }
        if (array_key_exists('rowid', $row)) {
            return $row['rowid'];
        }
        foreach ($row as $column => $value) {
            if (is_string($column) && str_ends_with($column, '_id')) {
                return $value;
            }
        }

        return null;
    }
}
