<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $triggers
     * @return array{savepoint:string,status:string,rows:list<array<string,mixed>>,current_source_rows:list<array<string,mixed>>,next_source_rows:list<array<string,mixed>>,attempted_rows:list<array<string,mixed>>,deleted:list<array<string,mixed>>,skipped:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,source_trace:list<array<string,mixed>>,discarded:list<array<string,mixed>>,changes:int,attempted_changes:int,rolled_back:bool,rollback_reason:?string,rollback_at_ordinal:?int,rollback_scope:string,savepoint_preserved:bool,dependencies:list<string>}
     */
    public static function deleteRows(string $savepoint, array $rows, callable $where, array $triggers = []): array
    {
        $savepoint = self::identifier($savepoint, 'savepoint');
        $baseRows = array_values($rows);
        $working = array_values($rows);
        $attemptedRows = $working;
        $deleted = [];
        $skipped = [];
        $effects = [];
        $sourceTrace = [];
        $rolledBack = false;
        $rollbackReason = null;
        $rollbackAt = null;
        $ordinal = 0;

        for ($index = 0; $index < count($working); ++$index) {
            $old = $working[$index];
            if (!$where($old)) {
                continue;
            }

            try {
                $before = self::fireTriggers('before', 'delete', $old, null, $triggers, $ordinal);
            } catch (SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextSignal $signal) {
                if ($signal->action === 'ignore') {
                    $skipped[] = self::skip('delete', $ordinal, $index, $old, 'before', $signal->reason);
                    ++$ordinal;
                    continue;
                }
                $rolledBack = true;
                $rollbackReason = $signal->reason;
                $rollbackAt = $ordinal;
                break;
            }
            $effects = array_merge($effects, $before);

            $sourceTrace[] = [
                'ordinal' => $ordinal,
                'event' => 'delete',
                'row_index' => $index,
                'current_source_count' => count($working),
                'current_source_names' => self::column($working, 'option_name'),
                'row' => $old,
            ];

            array_splice($working, $index, 1);
            --$index;
            $attemptedRows = array_values($working);
            $deleted[] = $old;

            try {
                $after = self::fireTriggers('after', 'delete', $old, null, $triggers, $ordinal);
            } catch (SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextSignal $signal) {
                $sourceTrace[array_key_last($sourceTrace)]['next_source_count'] = count($working);
                $sourceTrace[array_key_last($sourceTrace)]['next_source_names'] = self::column($working, 'option_name');
                if ($signal->action === 'ignore') {
                    $skipped[] = self::skip('delete', $ordinal, $index + 1, $old, 'after', $signal->reason);
                    ++$ordinal;
                    continue;
                }
                $rolledBack = true;
                $rollbackReason = $signal->reason;
                $rollbackAt = $ordinal;
                break;
            }
            $effects = array_merge($effects, $after);
            $sourceTrace[array_key_last($sourceTrace)]['next_source_count'] = count($working);
            $sourceTrace[array_key_last($sourceTrace)]['next_source_names'] = self::column($working, 'option_name');
            ++$ordinal;
        }

        return self::finish($savepoint, 'delete', $baseRows, $working, $attemptedRows, $deleted, $skipped, $effects, $sourceTrace, $rolledBack, $rollbackReason, $rollbackAt);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed|callable(array<string,mixed>):mixed> $assignments
     * @param list<array<string,mixed>> $triggers
     * @return array{savepoint:string,status:string,rows:list<array<string,mixed>>,current_source_rows:list<array<string,mixed>>,next_source_rows:list<array<string,mixed>>,attempted_rows:list<array<string,mixed>>,deleted:list<array<string,mixed>>,skipped:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,source_trace:list<array<string,mixed>>,discarded:list<array<string,mixed>>,changes:int,attempted_changes:int,rolled_back:bool,rollback_reason:?string,rollback_at_ordinal:?int,rollback_scope:string,savepoint_preserved:bool,dependencies:list<string>}
     */
    public static function updateRows(string $savepoint, array $rows, array $assignments, callable $where, array $triggers = []): array
    {
        $savepoint = self::identifier($savepoint, 'savepoint');
        if ($assignments === []) {
            throw new \InvalidArgumentException('SQLite transaction savepoint trigger rollback current-source UPDATE requires assignments');
        }
        foreach (array_keys($assignments) as $column) {
            self::identifier((string) $column, 'assignment column');
        }

        $baseRows = array_values($rows);
        $working = array_values($rows);
        $attemptedRows = $working;
        $updated = [];
        $skipped = [];
        $effects = [];
        $sourceTrace = [];
        $rolledBack = false;
        $rollbackReason = null;
        $rollbackAt = null;
        $ordinal = 0;

        foreach ($working as $index => $old) {
            if (!$where($old)) {
                continue;
            }
            $next = self::updatedRow($old, $assignments);

            try {
                $before = self::fireTriggers('before', 'update', $old, $next, $triggers, $ordinal);
            } catch (SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextSignal $signal) {
                if ($signal->action === 'ignore') {
                    $skipped[] = self::skip('update', $ordinal, $index, $old, 'before', $signal->reason);
                    ++$ordinal;
                    continue;
                }
                $rolledBack = true;
                $rollbackReason = $signal->reason;
                $rollbackAt = $ordinal;
                break;
            }
            $next = $before['row'];
            $effects = array_merge($effects, $before['effects']);

            $sourceTrace[] = [
                'ordinal' => $ordinal,
                'event' => 'update',
                'row_index' => $index,
                'current_source_count' => count($working),
                'current_source_names' => self::column($working, 'option_name'),
                'row' => $old,
            ];

            $working[$index] = $next;
            $attemptedRows = array_values($working);
            $updated[] = ['old' => $old, 'new' => $next, 'row_index' => $index];

            try {
                $after = self::fireTriggers('after', 'update', $old, $next, $triggers, $ordinal);
            } catch (SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextSignal $signal) {
                $sourceTrace[array_key_last($sourceTrace)]['next_source_count'] = count($working);
                $sourceTrace[array_key_last($sourceTrace)]['next_source_names'] = self::column($working, 'option_name');
                if ($signal->action === 'ignore') {
                    $skipped[] = self::skip('update', $ordinal, $index, $old, 'after', $signal->reason);
                    ++$ordinal;
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
            $sourceTrace[array_key_last($sourceTrace)]['next_source_count'] = count($working);
            $sourceTrace[array_key_last($sourceTrace)]['next_source_names'] = self::column($working, 'option_name');
            ++$ordinal;
        }

        $result = self::finish($savepoint, 'update', $baseRows, $working, $attemptedRows, [], $skipped, $effects, $sourceTrace, $rolledBack, $rollbackReason, $rollbackAt);
        $result['updated'] = $rolledBack ? [] : $updated;

        return $result;
    }

    /**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $working
     * @param list<array<string,mixed>> $attemptedRows
     * @param list<array<string,mixed>> $deleted
     * @param list<array<string,mixed>> $skipped
     * @param list<array<string,mixed>> $effects
     * @param list<array<string,mixed>> $sourceTrace
     * @return array<string,mixed>
     */
    private static function finish(
        string $savepoint,
        string $event,
        array $baseRows,
        array $working,
        array $attemptedRows,
        array $deleted,
        array $skipped,
        array $effects,
        array $sourceTrace,
        bool $rolledBack,
        ?string $rollbackReason,
        ?int $rollbackAt,
    ): array {
        $discarded = $rolledBack ? self::discardedRows($baseRows, $attemptedRows) : [];
        if ($rolledBack) {
            $working = $baseRows;
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
            'current_source_rows' => array_values($working),
            'next_source_rows' => array_values($attemptedRows),
            'attempted_rows' => array_values($attemptedRows),
            'deleted' => $rolledBack ? [] : $deleted,
            'skipped' => array_values($skipped),
            'trigger_effects' => array_values($effects),
            'source_trace' => array_values($sourceTrace),
            'discarded' => $discarded,
            'changes' => $rolledBack ? 0 : count($sourceTrace),
            'attempted_changes' => count($sourceTrace),
            'rolled_back' => $rolledBack,
            'rollback_reason' => $rollbackReason,
            'rollback_at_ordinal' => $rollbackAt,
            'rollback_scope' => $rolledBack ? 'transaction-savepoint' : 'none',
            'savepoint_preserved' => array_values($working) == array_values($baseRows),
            'dependencies' => [
                'sqlite-trigger-raise-rollback-current-source-next106',
                'sqlite-savepoint-transaction-current-source-rollback',
                'sqlite-application-trigger-rollback-import',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $triggers
     * @return array{row:array<string,mixed>|null,effects:list<array<string,mixed>>}|list<array<string,mixed>>
     */
    private static function fireTriggers(string $timing, string $event, array $old, ?array $next, array $triggers, int $ordinal): array
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
                    throw new \InvalidArgumentException('SQLite transaction savepoint trigger rollback current-source RAISE action is unsupported');
                }
                throw new SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextSignal($raise, (string) ($trigger['reason'] ?? 'trigger-raise'));
            }
            if ($action === 'set-new') {
                if ($next === null) {
                    throw new \InvalidArgumentException('SQLite transaction savepoint trigger rollback current-source DELETE cannot set NEW values');
                }
                foreach ((array) ($trigger['set'] ?? []) as $column => $value) {
                    self::identifier((string) $column, 'trigger set column');
                    $next[(string) $column] = self::value($value, $old, $next);
                }
            } elseif ($action !== 'audit') {
                throw new \InvalidArgumentException('SQLite transaction savepoint trigger rollback current-source trigger action is unsupported');
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

        return $event === 'update' ? ['row' => $next, 'effects' => $effects] : $effects;
    }

    private static function whenMatches(mixed $when, array $old, ?array $next): bool
    {
        if ($when === true || $when === null) {
            return true;
        }
        if ($when === false) {
            return false;
        }
        if (!is_array($when)) {
            throw new \InvalidArgumentException('SQLite transaction savepoint trigger rollback current-source WHEN clause is malformed');
        }

        $left = self::value($when[0] ?? $when['left'] ?? null, $old, $next);
        $operator = strtolower((string) ($when[1] ?? $when['operator'] ?? '='));
        $right = self::value($when[2] ?? $when['right'] ?? null, $old, $next);

        return match ($operator) {
            '=', '==' => $left == $right,
            '!=', '<>' => $left != $right,
            'is' => $left === $right,
            'is not' => $left !== $right,
            default => throw new \InvalidArgumentException('SQLite transaction savepoint trigger rollback current-source WHEN operator is unsupported'),
        };
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
     * @return array<string,mixed>
     */
    private static function skip(string $event, int $ordinal, int $index, array $old, string $timing, string $reason): array
    {
        return [
            'ordinal' => $ordinal,
            'row_index' => $index,
            'event' => $event,
            'timing' => $timing,
            'row' => $old,
            'reason' => $reason,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function project(array $projection, array $old, ?array $next): array
    {
        $row = [];
        foreach ($projection as $column => $expr) {
            self::identifier((string) $column, 'trigger projection column');
            $row[(string) $column] = self::value($expr, $old, $next);
        }

        return $row;
    }

    private static function value(mixed $expr, array $old, ?array $next): mixed
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
                throw new \InvalidArgumentException("SQLite transaction savepoint trigger rollback OLD column {$column} is missing");
            }
            return $old[$column];
        }
        if (str_starts_with($expr, 'new.')) {
            if ($next === null) {
                throw new \InvalidArgumentException('SQLite transaction savepoint trigger rollback NEW values are unavailable for DELETE');
            }
            $column = substr($expr, 4);
            if (!array_key_exists($column, $next)) {
                throw new \InvalidArgumentException("SQLite transaction savepoint trigger rollback NEW column {$column} is missing");
            }
            return $next[$column];
        }
        if ($next !== null && array_key_exists($expr, $next)) {
            return $next[$expr];
        }
        if (array_key_exists($expr, $old)) {
            return $old[$expr];
        }

        return $expr;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function column(array $rows, string $column): array
    {
        return array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows);
    }

    /**
     * @param list<array<string,mixed>> $before
     * @param list<array<string,mixed>> $after
     * @return list<array<string,mixed>>
     */
    private static function discardedRows(array $before, array $after): array
    {
        $discarded = [];
        $afterByIdentity = [];
        foreach ($after as $row) {
            $afterByIdentity[self::rowIdentity($row)] = $row;
        }
        foreach ($before as $index => $row) {
            $identity = self::rowIdentity($row);
            if (!array_key_exists($identity, $afterByIdentity) || $afterByIdentity[$identity] != $row) {
                $discarded[] = ['row_index' => $index, 'savepoint_row' => $row, 'attempted_row' => $afterByIdentity[$identity] ?? null];
            }
        }

        return $discarded;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowIdentity(array $row): string
    {
        if (array_key_exists('option_id', $row)) {
            return 'option_id:' . (string) $row['option_id'];
        }
        if (array_key_exists('rowid', $row)) {
            return 'rowid:' . (string) $row['rowid'];
        }

        return 'row:' . json_encode($row, JSON_THROW_ON_ERROR);
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite transaction savepoint trigger rollback current-source {$label} is malformed");
        }

        return $value;
    }
}

final class SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextSignal extends \RuntimeException
{
    public function __construct(public readonly string $action, public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
