<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerReturningNestedSavepointCurrentNextPlan
{
    /**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $insertRows
     * @param array<string,mixed|callable(array<string,mixed>):mixed> $updateAssignments
     * @param callable(array<string,mixed>):bool $updateWhere
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,int,string):mixed> $returning
     * @return array{outer_savepoint:string,released_savepoint:string,rollback_savepoint:string,status:string,base_rows:list<array<string,mixed>>,current_rows:list<array<string,mixed>>,next_rows:list<array<string,mixed>>,release_rows:list<array<string,mixed>>,rollback_attempt_rows:list<array<string,mixed>>,released_yield_stream:list<array<string,mixed>>,rollback_current_yield_stream:list<array<string,mixed>>,next_returning_rows:list<array<string,mixed>>,released_returning_rows:list<array<string,mixed>>,rollback_current_returning_rows:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,discarded:list<array<string,mixed>>,release_changes:int,rollback_attempted_changes:int,next_changes:int,rollback_reason:?string,rollback_at_ordinal:?int,release_preserved_in_outer:bool,rollback_preserved_released_rows:bool,dependencies:list<string>}
     */
    public static function apply(
        string $outerSavepoint,
        string $releasedSavepoint,
        string $rollbackSavepoint,
        array $baseRows,
        array $insertRows,
        array $updateAssignments,
        callable $updateWhere,
        array $triggers,
        array $returning,
    ): array {
        $outerSavepoint = self::identifier($outerSavepoint, 'outer savepoint');
        $releasedSavepoint = self::identifier($releasedSavepoint, 'released savepoint');
        $rollbackSavepoint = self::identifier($rollbackSavepoint, 'rollback savepoint');
        self::validateAssignments($updateAssignments);

        $rows = array_values($baseRows);
        $effects = [];
        $releasedYield = [];
        $releaseChanges = 0;

        foreach (array_values($insertRows) as $row) {
            $old = [];
            $next = $row;
            $ordinal = count($releasedYield);
            $before = self::fireTriggers('before', 'insert', $old, $next, $triggers, $ordinal);
            $next = $before['row'];
            $effects = array_merge($effects, $before['effects']);
            $rows[] = $next;
            ++$releaseChanges;
            $after = self::fireTriggers('after', 'insert', $old, $next, $triggers, $ordinal);
            $next = $after['row'];
            $rows[array_key_last($rows)] = $next;
            $effects = array_merge($effects, $after['effects']);
            $releasedYield[] = self::yieldRow('insert', $ordinal, count($rows) - 1, $old, $next, $returning, 'released-to-outer');
        }

        $releaseRows = array_values($rows);
        $attemptRows = $releaseRows;
        $rollbackYield = [];
        $rollbackAttemptedChanges = 0;
        $rolledBack = false;
        $rollbackReason = null;
        $rollbackAt = null;

        foreach ($attemptRows as $index => $old) {
            if (!$updateWhere($old)) {
                continue;
            }

            $ordinal = count($rollbackYield);
            $next = self::updatedRow($old, $updateAssignments);
            $before = self::fireTriggers('before', 'update', $old, $next, $triggers, $ordinal);
            $next = $before['row'];
            $effects = array_merge($effects, $before['effects']);
            $attemptRows[$index] = $next;
            ++$rollbackAttemptedChanges;

            try {
                $after = self::fireTriggers('after', 'update', $old, $next, $triggers, $ordinal);
            } catch (SQLiteTriggerReturningNestedSavepointCurrentNextSignal $signal) {
                $rollbackYield[] = self::yieldRow('update', $ordinal, $index, $old, $next, $returning, 'current-before-rollback');
                $rolledBack = true;
                $rollbackReason = $signal->reason;
                $rollbackAt = $ordinal;
                break;
            }

            $next = $after['row'];
            $attemptRows[$index] = $next;
            $effects = array_merge($effects, $after['effects']);
            $rollbackYield[] = self::yieldRow('update', $ordinal, $index, $old, $next, $returning, 'current-before-rollback');
        }

        $discarded = self::discardedRows($releaseRows, $attemptRows);
        $nextRows = $rolledBack ? $releaseRows : $attemptRows;
        if ($rolledBack) {
            $effects[] = [
                'trigger' => null,
                'timing' => 'savepoint',
                'event' => 'rollback',
                'action' => 'rollback-child-savepoint',
                'ordinal' => $rollbackAt,
                'reason' => $rollbackReason,
                'discarded_count' => count($discarded),
            ];
        }

        return [
            'outer_savepoint' => $outerSavepoint,
            'released_savepoint' => $releasedSavepoint,
            'rollback_savepoint' => $rollbackSavepoint,
            'status' => $rolledBack ? 'child-rolled-back' : 'commit-ok',
            'base_rows' => array_values($baseRows),
            'current_rows' => $attemptRows,
            'next_rows' => $nextRows,
            'release_rows' => $releaseRows,
            'rollback_attempt_rows' => $attemptRows,
            'released_yield_stream' => $releasedYield,
            'rollback_current_yield_stream' => $rollbackYield,
            'next_returning_rows' => $rolledBack ? array_values(array_map(static fn (array $yield): array => $yield['returning'], $releasedYield)) : array_values(array_merge(
                array_map(static fn (array $yield): array => $yield['returning'], $releasedYield),
                array_map(static fn (array $yield): array => $yield['returning'], $rollbackYield),
            )),
            'released_returning_rows' => array_values(array_map(static fn (array $yield): array => $yield['returning'], $releasedYield)),
            'rollback_current_returning_rows' => array_values(array_map(static fn (array $yield): array => $yield['returning'], $rollbackYield)),
            'trigger_effects' => array_values($effects),
            'discarded' => $discarded,
            'release_changes' => $releaseChanges,
            'rollback_attempted_changes' => $rollbackAttemptedChanges,
            'next_changes' => $rolledBack ? $releaseChanges : $releaseChanges + $rollbackAttemptedChanges,
            'rollback_reason' => $rollbackReason,
            'rollback_at_ordinal' => $rollbackAt,
            'release_preserved_in_outer' => array_slice($nextRows, 0, count($releaseRows)) == $releaseRows,
            'rollback_preserved_released_rows' => $rolledBack && $nextRows === $releaseRows,
            'dependencies' => [
                'sqlite-trigger-returning-nested-savepoint',
                'sqlite-nested-savepoint-release-propagates',
                'sqlite-nested-savepoint-rollback-suppresses-current-returning',
            ],
        ];
    }

    /**
     * @param array<string,mixed|callable(array<string,mixed>):mixed> $assignments
     */
    private static function validateAssignments(array $assignments): void
    {
        if ($assignments === []) {
            throw new \InvalidArgumentException('SQLite trigger RETURNING nested savepoint UPDATE requires assignments');
        }
        foreach (array_keys($assignments) as $column) {
            self::identifier((string) $column, 'assignment column');
        }
    }

    /**
     * @param array<string,mixed|callable(array<string,mixed>):mixed> $assignments
     * @return array<string,mixed>
     */
    private static function updatedRow(array $old, array $assignments): array
    {
        $next = $old;
        foreach ($assignments as $column => $value) {
            $next[(string) $column] = is_callable($value) ? $value($old) : $value;
        }

        return $next;
    }

    /**
     * @param list<array<string,mixed>> $triggers
     * @return array{row:array<string,mixed>,effects:list<array<string,mixed>>}
     */
    private static function fireTriggers(string $timing, string $event, array $old, array $next, array $triggers, int $ordinal): array
    {
        $effects = [];
        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? '')) !== $timing || strtolower((string) ($trigger['event'] ?? '')) !== $event) {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? true, $old, $next)) {
                continue;
            }

            $action = strtolower((string) ($trigger['action'] ?? 'audit'));
            if ($action === 'raise') {
                $raise = strtolower((string) ($trigger['raise'] ?? 'rollback'));
                if ($raise !== 'rollback') {
                    throw new \InvalidArgumentException('SQLite trigger RETURNING nested savepoint RAISE action is unsupported');
                }
                throw new SQLiteTriggerReturningNestedSavepointCurrentNextSignal((string) ($trigger['reason'] ?? 'trigger-raise'));
            }
            if ($action === 'set-new') {
                foreach ((array) ($trigger['set'] ?? []) as $column => $value) {
                    self::identifier((string) $column, 'trigger set column');
                    $next[(string) $column] = self::value($value, $old, $next);
                }
            } elseif ($action !== 'audit') {
                throw new \InvalidArgumentException('SQLite trigger RETURNING nested savepoint trigger action is unsupported');
            }

            $effects[] = [
                'trigger' => (string) ($trigger['name'] ?? ''),
                'timing' => $timing,
                'event' => $event,
                'action' => $action,
                'ordinal' => $ordinal,
                'row' => self::project((array) ($trigger['values'] ?? []), $old, $next),
            ];
        }

        return ['row' => $next, 'effects' => $effects];
    }

    private static function whenMatches(mixed $when, array $old, array $next): bool
    {
        if ($when === true || $when === null) {
            return true;
        }
        if ($when === false) {
            return false;
        }
        if (!is_array($when)) {
            throw new \InvalidArgumentException('SQLite trigger RETURNING nested savepoint WHEN clause is malformed');
        }

        $left = self::value($when[0] ?? $when['left'] ?? null, $old, $next);
        $operator = strtolower((string) ($when[1] ?? $when['operator'] ?? '='));
        $right = self::value($when[2] ?? $when['right'] ?? null, $old, $next);

        return match ($operator) {
            '=', '==' => $left == $right,
            '!=', '<>' => $left != $right,
            'is' => $left === $right,
            'is not' => $left !== $right,
            default => throw new \InvalidArgumentException('SQLite trigger RETURNING nested savepoint WHEN operator is unsupported'),
        };
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,int,string):mixed> $returning
     * @return array<string,mixed>
     */
    private static function yieldRow(string $event, int $ordinal, int $index, array $old, array $next, array $returning, string $status): array
    {
        return [
            'ordinal' => $ordinal,
            'row_index' => $index,
            'event' => $event,
            'status' => $status,
            'current_row' => $old,
            'next_row' => $next,
            'returning' => self::returning($old, $next, $ordinal, $status, $returning),
        ];
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,int,string):mixed> $returning
     * @return array<string,mixed>
     */
    private static function returning(array $old, array $next, int $ordinal, string $status, array $returning): array
    {
        $out = [];
        foreach ($returning as $index => $term) {
            if ($term === '*') {
                $out['*'] = $next;
                continue;
            }
            if (is_callable($term)) {
                $out['expr' . $index] = $term($next, $old, $ordinal, $status);
                continue;
            }
            if (is_array($term)) {
                $expr = (string) ($term['expr'] ?? '');
                $alias = (string) ($term['as'] ?? $expr);
                self::identifier($alias, 'RETURNING alias');
                $out[$alias] = self::value($expr, $old, $next);
                continue;
            }
            if (!is_string($term) || $term === '') {
                throw new \InvalidArgumentException('SQLite trigger RETURNING nested savepoint RETURNING term is malformed');
            }
            $out[$term] = self::value($term, $old, $next);
        }

        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    private static function project(array $projection, array $old, array $next): array
    {
        $row = [];
        foreach ($projection as $column => $expr) {
            self::identifier((string) $column, 'trigger projection column');
            $row[(string) $column] = self::value($expr, $old, $next);
        }

        return $row;
    }

    private static function value(mixed $expr, array $old, array $next): mixed
    {
        if (is_callable($expr)) {
            return $expr($old, $next);
        }
        if (!is_string($expr)) {
            return $expr;
        }
        if (str_starts_with($expr, 'old.')) {
            $column = substr($expr, 4);
            return $old[$column] ?? null;
        }
        if (str_starts_with($expr, 'new.')) {
            $column = substr($expr, 4);
            if (!array_key_exists($column, $next)) {
                throw new \InvalidArgumentException("SQLite trigger RETURNING nested savepoint NEW column {$column} is missing");
            }
            return $next[$column];
        }
        if (array_key_exists($expr, $next)) {
            return $next[$expr];
        }

        return $expr;
    }

    /**
     * @param list<array<string,mixed>> $before
     * @param list<array<string,mixed>> $after
     * @return list<array<string,mixed>>
     */
    private static function discardedRows(array $before, array $after): array
    {
        $discarded = [];
        foreach ($after as $index => $row) {
            if (!array_key_exists($index, $before) || $before[$index] != $row) {
                $discarded[] = ['row_index' => $index, 'row' => $row, 'savepoint_row' => $before[$index] ?? null];
            }
        }

        return $discarded;
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite trigger RETURNING nested savepoint current-next {$label} is malformed");
        }

        return $value;
    }
}

final class SQLiteTriggerReturningNestedSavepointCurrentNextSignal extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
