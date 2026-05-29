<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerReturningSavepointCurrentNextPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed|callable(array<string,mixed>):mixed> $assignments
     * @param callable(array<string,mixed>):bool $where
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,int,string):mixed> $returning
     * @return array{savepoint:string,status:string,rows:list<array<string,mixed>>,current_rows:list<array<string,mixed>>,attempted_rows:list<array<string,mixed>>,yield_stream:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,skipped:list<array<string,mixed>>,discarded:list<array<string,mixed>>,changes:int,attempted_changes:int,rolled_back:bool,rollback_reason:?string,rollback_at_ordinal:?int,savepoint_preserved:bool,dependencies:list<string>}
     */
    public static function updateRows(
        string $savepoint,
        array $rows,
        array $assignments,
        callable $where,
        array $triggers = [],
        array $returning = ['*'],
    ): array {
        $savepoint = self::identifier($savepoint, 'savepoint');
        self::validateAssignments($assignments);
        $baseRows = array_values($rows);
        $working = array_values($rows);
        $attemptedRows = $working;
        $yieldStream = [];
        $returningRows = [];
        $effects = [];
        $skipped = [];
        $discarded = [];
        $changes = 0;
        $attemptedChanges = 0;
        $rolledBack = false;
        $rollbackReason = null;
        $rollbackAt = null;

        foreach ($working as $index => $old) {
            if (!$where($old)) {
                continue;
            }

            $ordinal = count($yieldStream) + count($skipped);
            $next = self::updatedRow($old, $assignments);

            try {
                $before = self::fireTriggers('before', 'update', $old, $next, $triggers, $ordinal);
            } catch (SQLiteTriggerReturningSavepointCurrentNextSignal $signal) {
                if ($signal->action === 'ignore') {
                    $skipped[] = self::skip($ordinal, $index, $old, $next, 'before', $signal->reason);
                    continue;
                }

                $rolledBack = true;
                $rollbackReason = $signal->reason;
                $rollbackAt = $ordinal;
                break;
            }

            $next = $before['row'];
            $effects = array_merge($effects, $before['effects']);
            $working[$index] = $next;
            $attemptedRows = array_values($working);
            ++$changes;
            ++$attemptedChanges;

            try {
                $after = self::fireTriggers('after', 'update', $old, $next, $triggers, $ordinal);
            } catch (SQLiteTriggerReturningSavepointCurrentNextSignal $signal) {
                $yield = self::yieldRow($ordinal, $index, $old, $next, $returning, 'changed-before-trigger-rollback');
                $yieldStream[] = $yield;
                $returningRows[] = $yield['returning'];
                if ($signal->action === 'ignore') {
                    $skipped[] = self::skip($ordinal, $index, $old, $next, 'after', $signal->reason);
                    continue;
                }

                $rolledBack = true;
                $rollbackReason = $signal->reason;
                $rollbackAt = $ordinal;
                break;
            }

            $next = $after['row'];
            $working[$index] = $next;
            $attemptedRows = array_values($working);
            $effects = array_merge($effects, $after['effects']);
            $yield = self::yieldRow($ordinal, $index, $old, $next, $returning, 'changed');
            $yieldStream[] = $yield;
            $returningRows[] = $yield['returning'];
        }

        if ($rolledBack) {
            $discarded = self::discardedRows($baseRows, $attemptedRows);
            $working = $baseRows;
            $changes = 0;
            $returningRows = [];
            $effects[] = [
                'trigger' => null,
                'timing' => 'savepoint',
                'event' => 'rollback',
                'action' => 'rollback-to-savepoint',
                'ordinal' => $rollbackAt,
                'reason' => $rollbackReason,
                'discarded_count' => count($discarded),
            ];
        }

        return [
            'savepoint' => $savepoint,
            'status' => $rolledBack ? 'rolled-back' : 'commit-ok',
            'rows' => array_values($working),
            'current_rows' => array_values($working),
            'attempted_rows' => array_values($attemptedRows),
            'yield_stream' => array_values($yieldStream),
            'returning_rows' => array_values($returningRows),
            'trigger_effects' => array_values($effects),
            'skipped' => array_values($skipped),
            'discarded' => array_values($discarded),
            'changes' => $changes,
            'attempted_changes' => $attemptedChanges,
            'rolled_back' => $rolledBack,
            'rollback_reason' => $rollbackReason,
            'rollback_at_ordinal' => $rollbackAt,
            'savepoint_preserved' => self::rowsEqual($working, $baseRows),
            'dependencies' => [
                'sqlite-trigger-returning-current-next65',
                'sqlite-trigger-raise-ignore-yield',
                'sqlite-savepoint-rollback-yield-suppression',
            ],
        ];
    }

    /**
     * @param array<string,mixed|callable(array<string,mixed>):mixed> $assignments
     */
    private static function validateAssignments(array $assignments): void
    {
        if ($assignments === []) {
            throw new \InvalidArgumentException('SQLite trigger RETURNING savepoint current-next UPDATE requires assignments');
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
                if (!in_array($raise, ['ignore', 'rollback'], true)) {
                    throw new \InvalidArgumentException('SQLite trigger RETURNING savepoint RAISE action is unsupported');
                }
                throw new SQLiteTriggerReturningSavepointCurrentNextSignal($raise, (string) ($trigger['reason'] ?? 'trigger-raise'));
            }
            if ($action === 'set-new') {
                foreach ((array) ($trigger['set'] ?? []) as $column => $value) {
                    self::identifier((string) $column, 'trigger set column');
                    $next[(string) $column] = self::value($value, $old, $next);
                }
            } elseif ($action !== 'audit') {
                throw new \InvalidArgumentException('SQLite trigger RETURNING savepoint trigger action is unsupported');
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
            throw new \InvalidArgumentException('SQLite trigger RETURNING savepoint WHEN clause is malformed');
        }

        $left = self::value($when[0] ?? $when['left'] ?? null, $old, $next);
        $operator = strtolower((string) ($when[1] ?? $when['operator'] ?? '='));
        $right = self::value($when[2] ?? $when['right'] ?? null, $old, $next);

        return match ($operator) {
            '=', '==' => $left == $right,
            '!=', '<>' => $left != $right,
            'is' => $left === $right,
            'is not' => $left !== $right,
            default => throw new \InvalidArgumentException('SQLite trigger RETURNING savepoint WHEN operator is unsupported'),
        };
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
                throw new \InvalidArgumentException('SQLite trigger RETURNING savepoint RETURNING term is malformed');
            }
            $out[$term] = self::value($term, $old, $next);
        }

        return $out;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,int,string):mixed> $returning
     * @return array<string,mixed>
     */
    private static function yieldRow(int $ordinal, int $index, array $old, array $next, array $returning, string $status): array
    {
        return [
            'ordinal' => $ordinal,
            'row_index' => $index,
            'event' => 'update',
            'status' => $status,
            'changed' => true,
            'current_row' => $old,
            'next_row' => $next,
            'returning' => self::returning($old, $next, $ordinal, $status, $returning),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function skip(int $ordinal, int $index, array $old, array $next, string $timing, string $reason): array
    {
        return [
            'ordinal' => $ordinal,
            'row_index' => $index,
            'event' => 'update',
            'status' => 'skipped',
            'timing' => $timing,
            'changed' => false,
            'current_row' => $old,
            'next_row' => $timing === 'before' ? $old : $next,
            'returning' => null,
            'reason' => $reason,
        ];
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
            if (!array_key_exists($column, $old)) {
                throw new \InvalidArgumentException("SQLite trigger RETURNING savepoint OLD column {$column} is missing");
            }
            return $old[$column];
        }
        if (str_starts_with($expr, 'new.')) {
            $column = substr($expr, 4);
            if (!array_key_exists($column, $next)) {
                throw new \InvalidArgumentException("SQLite trigger RETURNING savepoint NEW column {$column} is missing");
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

    /**
     * @param list<array<string,mixed>> $left
     * @param list<array<string,mixed>> $right
     */
    private static function rowsEqual(array $left, array $right): bool
    {
        return array_values($left) == array_values($right);
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite trigger RETURNING savepoint current-next {$label} is malformed");
        }

        return $value;
    }
}

final class SQLiteTriggerReturningSavepointCurrentNextSignal extends \RuntimeException
{
    public function __construct(public readonly string $action, public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
